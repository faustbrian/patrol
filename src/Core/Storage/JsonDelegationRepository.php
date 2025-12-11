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
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;

use const JSON_PRETTY_PRINT;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;
use function dirname;
use function glob;
use function implode;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function throw_unless;

/**
 * JSON file-based delegation repository with versioning and caching.
 *
 * Persists delegations as JSON files with support for create, read, update,
 * and delete operations. Maintains version history and provides in-memory caching.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FileStorageBase For versioning and path management
 */
final readonly class JsonDelegationRepository extends FileStorageBase implements DelegationRepositoryInterface
{
    /**
     * Create a new JSON delegation repository.
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
     * Persist a new delegation to JSON storage.
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
            // Keep active delegations
            if ($data['state'] === DelegationState::Active->value) {
                return true;
            }

            // Remove expired delegations older than retention
            if ($data['state'] === DelegationState::Expired->value && is_string($data['expires_at'])) {
                $expiresAt = new DateTimeImmutable($data['expires_at']);

                return $expiresAt >= $cutoff;
            }

            // Remove revoked delegations older than retention
            if ($data['state'] === DelegationState::Revoked->value && $data['revoked_at'] !== null && is_string($data['revoked_at'])) {
                $revokedAt = new DateTimeImmutable($data['revoked_at']);

                return $revokedAt >= $cutoff;
            }

            return true;
        });

        $removed = $original - count($delegations);

        if ($removed > 0) {
            $this->saveDelegations(array_values($delegations));
        }

        return $removed;
    }

    /**
     * Serialize delegation to array for JSON storage.
     *
     * Converts delegation object and nested value objects to associative array.
     * Formats timestamps as 'Y-m-d H:i:s' strings for human readability.
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
            'scope' => [
                'resources' => $delegation->scope->resources,
                'actions' => $delegation->scope->actions,
                'domain' => $delegation->scope->domain,
            ],
            'created_at' => $delegation->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $delegation->expiresAt?->format('Y-m-d H:i:s'),
            'is_transitive' => $delegation->isTransitive,
            'state' => $delegation->status->value,
            'metadata' => $delegation->metadata,
            'revoked_at' => null,
        ];
    }

    /**
     * Hydrate delegation from array storage.
     *
     * Reconstructs delegation object from stored data including nested scope
     * and datetime objects. Handles optional fields gracefully.
     *
     * @param  array<string, mixed> $data Raw delegation data from JSON
     * @return Delegation           Fully hydrated delegation object
     */
    private function hydrateDelegation(array $data): Delegation
    {
        $scope = is_array($data['scope'] ?? null) ? $data['scope'] : [];
        $resources = is_array($scope['resources'] ?? null) ? $scope['resources'] : [];
        $actions = is_array($scope['actions'] ?? null) ? $scope['actions'] : [];

        /** @var array<string> $resources */
        /** @var array<string> $actions */

        return new Delegation(
            id: is_string($data['id']) ? $data['id'] : throw new InvalidArgumentException('Invalid delegation id'),
            delegatorId: is_string($data['delegator_id']) ? $data['delegator_id'] : throw new InvalidArgumentException('Invalid delegator_id'),
            delegateId: is_string($data['delegate_id']) ? $data['delegate_id'] : throw new InvalidArgumentException('Invalid delegate_id'),
            scope: new DelegationScope(
                resources: $resources,
                actions: $actions,
                domain: array_key_exists('domain', $scope) && is_string($scope['domain']) ? $scope['domain'] : null,
            ),
            createdAt: new DateTimeImmutable(is_string($data['created_at']) ? $data['created_at'] : throw new InvalidArgumentException('Invalid created_at')),
            expiresAt: array_key_exists('expires_at', $data) && is_string($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            isTransitive: is_bool($data['is_transitive'] ?? null) ? $data['is_transitive'] : throw new InvalidArgumentException('Invalid is_transitive'),
            status: DelegationState::from(is_int($data['state']) || is_string($data['state']) ? $data['state'] : throw new InvalidArgumentException('Invalid state')),
            metadata: (function () use ($data): array { // @phpstan-ignore argument.type
                $metadata = $data['metadata'] ?? [];

                if (!is_array($metadata)) {
                    return [];
                }

                return $metadata;
            })(),
        );
    }

    /**
     * Load all delegations from JSON storage with caching.
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
     * Load delegations from single JSON file.
     *
     * Returns empty array if file doesn't exist or is unreadable. Handles
     * JSON decode errors gracefully.
     *
     * @return array<int, array<string, mixed>> Delegation data or empty array
     */
    private function loadSingleFile(): array
    {
        $filePath = $this->buildPath('delegations', 'delegations', 'json');

        if (!File::exists($filePath)) {
            return [];
        }

        $content = File::get($filePath);

        // @codeCoverageIgnoreStart
        // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
        if ($content === false) {
            return [];
        }

        /** @codeCoverageIgnoreEnd */
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        return $data; // @phpstan-ignore return.type
    }

    /**
     * Load delegations from multiple JSON files.
     *
     * Scans directory for .json files and aggregates delegation data. Skips
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

        $files = glob(sprintf('%s/*.json', $dirPath));

        if ($files === false) {
            return [];
        }

        foreach ($files as $filePath) {
            if (!File::exists($filePath)) {
                continue;
            }

            $content = File::get($filePath);

            // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);

            if (!is_array($data)) {
                continue;
            }

            /** @var array<string, mixed> $data */
            $delegations[] = $data;
        }

        return $delegations;
    }

    /**
     * Save delegations back to storage.
     *
     * Writes to single file or multiple files based on configured file mode.
     * Creates parent directories with 0755 permissions if needed. Formats JSON
     * with pretty-print for human readability.
     *
     * @param array<int, array<string, mixed>> $delegations Delegation data to persist
     */
    private function saveDelegations(array $delegations): void
    {
        if ($this->fileMode === FileMode::Single) {
            $filePath = $this->buildPath('delegations', 'delegations', 'json');
            $dir = dirname($filePath);

            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0o755, true);
            }

            $encoded = json_encode($delegations, JSON_PRETTY_PRINT);

            if ($encoded !== false) {
                File::put($filePath, $encoded);
            }
        } else {
            // Write each delegation to its own file
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

        $filePath = $this->buildPath('delegations', 'delegations', 'json');
        $dir = dirname($filePath);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $encoded = json_encode($delegations, JSON_PRETTY_PRINT);

        if ($encoded === false) {
            return;
        }

        File::put($filePath, $encoded);
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
        $filePath = $this->buildPath('delegations', $id, 'json');
        $dir = dirname($filePath);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT);

        if ($encoded === false) {
            return;
        }

        File::put($filePath, $encoded);
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

            if ($version !== null) {
                $parts[] = $version;
            }
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
