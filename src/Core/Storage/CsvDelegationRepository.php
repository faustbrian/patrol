<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use League\Csv\Reader;
use League\Csv\Writer;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;
use function dirname;
use function explode;
use function glob;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function throw_unless;

/**
 * CSV file-based delegation repository with versioning and caching.
 *
 * Persists delegations as CSV files with support for create, read, update,
 * and delete operations. Maintains version history and provides in-memory caching.
 *
 * CSV structure: id,delegator_id,delegate_id,resources,actions,domain,created_at,expires_at,is_transitive,state,metadata,revoked_at
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FileStorageBase For versioning and path management
 */
final readonly class CsvDelegationRepository extends FileStorageBase implements DelegationRepositoryInterface
{
    /**
     * Create a new CSV delegation repository.
     *
     * @param string               $basePath          Base storage directory path
     * @param FileMode             $fileMode          File organization mode (single/multiple)
     * @param null|string          $version           Specific version or null for latest
     * @param bool                 $versioningEnabled Whether versioning is active
     * @param array<string, mixed> $cache             In-memory cache for file contents
     * @param int                  $retentionDays     Number of days to retain delegations
     */
    public function __construct(
        string $basePath,
        FileMode $fileMode,
        ?string $version = null,
        bool $versioningEnabled = true,
        array $cache = [],
        private int $retentionDays = 90,
    ) {
        parent::__construct($basePath, $fileMode, $version, $versioningEnabled, $cache);
    }

    /**
     * Persist a new delegation to CSV storage.
     *
     * Writes delegation to file(s) based on file mode. Creates new version
     * directory if auto-bump is enabled in configuration.
     *
     * @param Delegation $delegation The delegation to persist
     */
    #[Override()]
    public function create(Delegation $delegation): void
    {
        $data = $this->serializeDelegation($delegation);

        if ($this->fileMode === FileMode::Single) {
            $this->appendToSingleFile($data);
        } else {
            $this->writeToMultipleFile($delegation->id, $data);
        }
    }

    /**
     * Retrieve a delegation by ID.
     *
     * @param  string          $id The delegation identifier
     * @return null|Delegation The delegation if found
     */
    #[Override()]
    public function findById(string $id): ?Delegation
    {
        $delegations = $this->loadDelegations();

        foreach ($delegations as $data) {
            if (!array_key_exists('id', $data)) {
                continue;
            }

            if ($data['id'] === $id) {
                return $this->hydrateDelegation($data);
            }
        }

        return null;
    }

    /**
     * Find all active delegations for a delegate.
     *
     * Filters delegations by delegate ID, active state, and expiration time.
     * Excludes expired delegations based on current timestamp comparison.
     *
     * @param  string            $delegateId The delegate subject identifier
     * @return array<Delegation> Active, non-expired delegations for the delegate
     */
    #[Override()]
    public function findActiveForDelegate(string $delegateId): array
    {
        $delegations = $this->loadDelegations();
        $now = CarbonImmutable::now();
        $active = [];

        foreach ($delegations as $data) {
            if ($data['delegate_id'] !== $delegateId) {
                continue;
            }

            if ($data['state'] !== DelegationState::Active->value) {
                continue;
            }

            if ($data['expires_at'] !== null && is_string($data['expires_at'])) {
                $expiresAt = new DateTimeImmutable($data['expires_at']);

                if ($expiresAt <= $now) {
                    continue;
                }
            }

            $active[] = $this->hydrateDelegation($data);
        }

        return $active;
    }

    /**
     * Revoke a delegation by ID.
     *
     * Sets delegation state to Revoked and records the revocation timestamp.
     * Does not delete the delegation to maintain audit trail.
     *
     * @param string $id The delegation identifier to revoke
     */
    #[Override()]
    public function revoke(string $id): void
    {
        $delegations = $this->loadDelegations();

        foreach ($delegations as &$data) {
            if ($data['id'] === $id) {
                $data['state'] = DelegationState::Revoked->value;
                $data['revoked_at'] = CarbonImmutable::now()->format('Y-m-d H:i:s');

                break;
            }
        }

        $this->saveDelegations($delegations);
    }

    /**
     * Remove old expired and revoked delegations.
     *
     * Implements retention policy to remove delegations older than the configured
     * retention period (default 90 days). Preserves all active delegations regardless
     * of age. Updates storage only if delegations were actually removed.
     *
     * @return int Number of delegations removed during cleanup
     */
    #[Override()]
    public function cleanup(): int
    {
        $delegations = $this->loadDelegations();
        $cutoff = CarbonImmutable::now()->modify(sprintf('-%d days', $this->retentionDays));

        $original = count($delegations);

        $delegations = array_filter($delegations, static function (array $data) use ($cutoff): bool {
            if ($data['state'] === DelegationState::Active->value) {
                return true;
            }

            if ($data['state'] === DelegationState::Expired->value && is_string($data['expires_at'])) {
                $expiresAt = new DateTimeImmutable($data['expires_at']);

                return $expiresAt >= $cutoff;
            }

            if ($data['state'] === DelegationState::Revoked->value && $data['revoked_at'] !== null && is_string($data['revoked_at'])) {
                $revokedAt = new DateTimeImmutable($data['revoked_at']);

                return $revokedAt >= $cutoff;
            }

            // @codeCoverageIgnoreStart
            return true;
            // @codeCoverageIgnoreEnd
        });

        $removed = $original - count($delegations);

        if ($removed > 0) {
            $this->saveDelegations(array_values($delegations));
        }

        return $removed;
    }

    /**
     * Serialize delegation to array for CSV storage.
     *
     * Converts delegation object and nested value objects to associative array.
     * Formats timestamps as 'Y-m-d H:i:s' strings. Arrays are encoded as JSON
     * or pipe-delimited strings for CSV compatibility.
     *
     * @param  Delegation           $delegation The delegation to serialize
     * @return array<string, mixed> Serializable delegation data
     */
    private function serializeDelegation(Delegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'delegator_id' => $delegation->delegatorId,
            'delegate_id' => $delegation->delegateId,
            'resources' => implode('|', $delegation->scope->resources),
            'actions' => implode('|', $delegation->scope->actions),
            'domain' => $delegation->scope->domain ?? '',
            'created_at' => $delegation->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $delegation->expiresAt?->format('Y-m-d H:i:s') ?? '',
            'is_transitive' => $delegation->isTransitive ? '1' : '0',
            'state' => $delegation->status->value,
            'metadata' => json_encode($delegation->metadata),
            'revoked_at' => '',
        ];
    }

    /**
     * Hydrate delegation from array storage.
     *
     * Reconstructs delegation object from stored data including nested scope
     * and datetime objects. Handles optional fields gracefully. Parses pipe-delimited
     * strings and JSON for complex fields.
     *
     * @param  array<string, mixed> $data Raw delegation data from CSV
     * @return Delegation           Fully hydrated delegation object
     */
    private function hydrateDelegation(array $data): Delegation
    {
        $resources = array_key_exists('resources', $data) && is_string($data['resources']) && $data['resources'] !== ''
            ? explode('|', $data['resources'])
            : [];

        $actions = array_key_exists('actions', $data) && is_string($data['actions']) && $data['actions'] !== ''
            ? explode('|', $data['actions'])
            : [];

        $metadataJson = $data['metadata'] ?? '{}';
        $metadata = is_string($metadataJson) ? json_decode($metadataJson, true) : [];

        // @codeCoverageIgnoreStart
        if (!is_array($metadata)) {
            $metadata = [];
        }

        // @codeCoverageIgnoreEnd

        /** @var array<string, mixed> $metadata */

        return new Delegation(
            id: is_string($data['id']) ? $data['id'] : throw new InvalidArgumentException('Invalid delegation id'),
            delegatorId: is_string($data['delegator_id']) ? $data['delegator_id'] : throw new InvalidArgumentException('Invalid delegator_id'),
            delegateId: is_string($data['delegate_id']) ? $data['delegate_id'] : throw new InvalidArgumentException('Invalid delegate_id'),
            scope: new DelegationScope(
                resources: $resources,
                actions: $actions,
                domain: array_key_exists('domain', $data) && is_string($data['domain']) && $data['domain'] !== '' ? $data['domain'] : null,
            ),
            createdAt: new DateTimeImmutable(is_string($data['created_at']) ? $data['created_at'] : throw new InvalidArgumentException('Invalid created_at')),
            expiresAt: array_key_exists('expires_at', $data) && is_string($data['expires_at']) && $data['expires_at'] !== '' ? new DateTimeImmutable($data['expires_at']) : null,
            isTransitive: (array_key_exists('is_transitive', $data) && in_array($data['is_transitive'], ['1', 1, true], true)),
            status: DelegationState::from(is_int($data['state']) || is_string($data['state']) ? $data['state'] : throw new InvalidArgumentException('Invalid state')),
            metadata: $metadata,
        );
    }

    /**
     * Load all delegations from CSV storage with caching.
     *
     * Checks in-memory cache first to avoid repeated file I/O. Delegates to
     * single-file or multi-file loader based on configured file mode.
     *
     * @return array<int, array<string, mixed>> Raw delegation data arrays
     */
    private function loadDelegations(): array
    {
        $cacheKey = $this->getCacheKey();

        if (array_key_exists($cacheKey, $this->cache)) {
            // Cache stores properly typed arrays but PHPStan cannot infer from array<string, mixed>
            // @phpstan-ignore-next-line return.type
            return $this->cache[$cacheKey];
        }

        if ($this->fileMode === FileMode::Single) {
            return $this->loadSingleFile();
        }

        return $this->loadMultipleFiles();
    }

    /**
     * Load delegations from single CSV file.
     *
     * Returns empty array if file doesn't exist or is unreadable. Header row is
     * automatically detected and used for associative array mapping.
     *
     * @return array<int, array<string, mixed>> Delegation data or empty array
     */
    private function loadSingleFile(): array
    {
        $filePath = $this->buildPath('delegations', 'delegations', 'csv');

        if (!File::exists($filePath)) {
            return [];
        }

        $content = File::get($filePath);

        // @codeCoverageIgnoreStart
        // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
        if ($content === false) {
            return [];
        }

        // @codeCoverageIgnoreEnd

        try {
            $csv = Reader::createFromString($content);
            $csv->setHeaderOffset(0);

            $records = [];

            foreach ($csv->getRecords() as $record) {
                /** @var array<string, mixed> $record */
                $records[] = $record;
            }

            return $records;
            // @codeCoverageIgnoreStart
        } catch (Exception) {
            return [];
        }

        // @codeCoverageIgnoreEnd
    }

    /**
     * Load delegations from multiple CSV files.
     *
     * Scans directory for .csv files and aggregates delegation data. Skips
     * files that fail to read or parse to enable partial recovery from corruption.
     *
     * @return array<int, array<string, mixed>> Aggregated delegation data
     */
    private function loadMultipleFiles(): array
    {
        $dirPath = $this->buildDirectoryPath('delegations');
        $delegations = [];

        if (!File::isDirectory($dirPath)) {
            return [];
        }

        $files = glob(sprintf('%s/*.csv', $dirPath));

        // @codeCoverageIgnoreStart
        if ($files === false) {
            return [];
        }

        // @codeCoverageIgnoreEnd

        foreach ($files as $filePath) {
            if (!File::isFile($filePath)) {
                continue;
            }

            try {
                $content = File::get($filePath);

                // @codeCoverageIgnoreStart
                // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
                if ($content === false) {
                    continue; // @codeCoverageIgnoreEnd
                }

                $csv = Reader::createFromString($content);
                $csv->setHeaderOffset(0);

                foreach ($csv->getRecords() as $record) {
                    /** @var array<string, mixed> $record */
                    $delegations[] = $record;
                }

                // @codeCoverageIgnoreStart
            } catch (Exception) {
                continue;
            }

            // @codeCoverageIgnoreEnd
        }

        return $delegations;
    }

    /**
     * Save delegations back to storage.
     *
     * Writes to single file or multiple files based on configured file mode.
     * Creates parent directories with 0755 permissions if needed.
     *
     * @param array<int, array<string, mixed>> $delegations Delegation data to persist
     */
    private function saveDelegations(array $delegations): void
    {
        if ($this->fileMode === FileMode::Single) {
            $filePath = $this->buildPath('delegations', 'delegations', 'csv');
            $dir = dirname($filePath);

            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0o755, true);
            }

            $csv = Writer::createFromString();
            $csv->insertOne(['id', 'delegator_id', 'delegate_id', 'resources', 'actions', 'domain', 'created_at', 'expires_at', 'is_transitive', 'state', 'metadata', 'revoked_at']);

            foreach ($delegations as $data) {
                $csv->insertOne([
                    $data['id'] ?? '',
                    $data['delegator_id'] ?? '',
                    $data['delegate_id'] ?? '',
                    $data['resources'] ?? '',
                    $data['actions'] ?? '',
                    $data['domain'] ?? '',
                    $data['created_at'] ?? '',
                    $data['expires_at'] ?? '',
                    $data['is_transitive'] ?? '',
                    $data['state'] ?? '',
                    $data['metadata'] ?? '',
                    $data['revoked_at'] ?? '',
                ]);
            }

            File::put($filePath, $csv->toString());
        } else {
            foreach ($delegations as $data) {
                $id = $data['id'] ?? throw new InvalidArgumentException('Delegation missing id');
                throw_unless(is_string($id), InvalidArgumentException::class, 'Delegation id must be string');

                $this->writeToMultipleFile($id, $data);
            }
        }
    }

    /**
     * Append delegation to single file.
     *
     * Loads existing delegations, appends new delegation, and rewrites the entire
     * file. Creates parent directories with 0755 permissions if needed.
     *
     * @param array<string, mixed> $data Delegation data to append
     */
    private function appendToSingleFile(array $data): void
    {
        $delegations = $this->loadDelegations();
        $delegations[] = $data;

        $this->saveDelegations($delegations);
    }

    /**
     * Write delegation to individual file.
     *
     * Creates or overwrites file named by delegation ID. Creates parent
     * directories with 0755 permissions if needed.
     *
     * @param string               $id   Delegation identifier used as filename
     * @param array<string, mixed> $data Delegation data to write
     */
    private function writeToMultipleFile(string $id, array $data): void
    {
        $filePath = $this->buildPath('delegations', $id, 'csv');
        $dir = dirname($filePath);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $csv = Writer::createFromString();
        $csv->insertOne(['id', 'delegator_id', 'delegate_id', 'resources', 'actions', 'domain', 'created_at', 'expires_at', 'is_transitive', 'state', 'metadata', 'revoked_at']);
        $csv->insertOne([
            $data['id'] ?? '',
            $data['delegator_id'] ?? '',
            $data['delegate_id'] ?? '',
            $data['resources'] ?? '',
            $data['actions'] ?? '',
            $data['domain'] ?? '',
            $data['created_at'] ?? '',
            $data['expires_at'] ?? '',
            $data['is_transitive'] ?? '',
            $data['state'] ?? '',
            $data['metadata'] ?? '',
            $data['revoked_at'] ?? '',
        ]);

        File::put($filePath, $csv->toString());
    }

    /**
     * Build directory path for multiple file mode.
     *
     * Constructs path including version subdirectory if versioning is enabled.
     * Used for glob operations and directory creation.
     *
     * @param  string $type Storage type (e.g., 'delegations')
     * @return string Absolute directory path
     */
    private function buildDirectoryPath(string $type): string
    {
        $parts = [$this->basePath, $type];

        if ($this->versioningEnabled) {
            $version = $this->resolveVersion($type);

            // @codeCoverageIgnoreStart
            if ($version !== null) {
                $parts[] = $version;
            }

            // @codeCoverageIgnoreEnd
        }

        return implode('/', $parts);
    }

    /**
     * Generate cache key for delegation storage.
     *
     * Combines version and file mode to ensure cache invalidation when
     * configuration changes or version switches occur.
     *
     * @return string Unique cache identifier
     */
    private function getCacheKey(): string
    {
        return sprintf(
            'delegations:%s:%s',
            $this->resolveVersion('delegations') ?? 'latest',
            $this->fileMode->value,
        );
    }
}
