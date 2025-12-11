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
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;

use const INI_SCANNER_TYPED;

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
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function parse_ini_string;
use function sprintf;
use function throw_unless;

/**
 * INI file-based delegation repository.
 *
 * Uses PHP's native INI parsing for delegation storage. Each delegation
 * is stored as a section with pipe-delimited arrays and JSON metadata.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FileStorageBase For versioning and path management
 */
final readonly class IniDelegationRepository extends FileStorageBase implements DelegationRepositoryInterface
{
    /**
     * Create a new INI delegation repository.
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

    #[Override()]
    public function findById(string $id): ?Delegation
    {
        $delegations = $this->loadDelegations();

        foreach ($delegations as $data) {
            /** @var array<string, mixed> $data */
            if ($data['id'] === $id) {
                return $this->hydrateDelegation($data);
            }
        }

        return null;
    }

    #[Override()]
    public function findActiveForDelegate(string $delegateId): array
    {
        $delegations = $this->loadDelegations();
        $now = CarbonImmutable::now();
        $active = [];

        foreach ($delegations as $data) {
            /** @var array<string, mixed> $data */
            if ($data['delegate_id'] !== $delegateId) {
                continue;
            }

            if ($data['state'] !== DelegationState::Active->value) {
                continue;
            }

            if ($data['expires_at'] !== null && is_string($data['expires_at']) && $data['expires_at'] !== '') {
                $expiresAt = new DateTimeImmutable($data['expires_at']);

                if ($expiresAt <= $now) {
                    continue;
                }
            }

            $active[] = $this->hydrateDelegation($data);
        }

        return $active;
    }

    #[Override()]
    public function revoke(string $id): void
    {
        $delegations = $this->loadDelegations();

        foreach ($delegations as &$data) {
            /** @var array<string, mixed> $data */
            if ($data['id'] === $id) {
                $data['state'] = DelegationState::Revoked->value;
                $data['revoked_at'] = CarbonImmutable::now()->format('Y-m-d H:i:s');

                break;
            }
        }

        $this->saveDelegations($delegations);
    }

    #[Override()]
    public function cleanup(): int
    {
        $delegations = $this->loadDelegations();
        $cutoff = CarbonImmutable::now()->modify(sprintf('-%d days', $this->retentionDays));

        $original = count($delegations);

        /** @var array<int, array<string, mixed>> $delegations */
        $delegations = array_filter($delegations, static function (array $data) use ($cutoff): bool {
            if ($data['state'] === DelegationState::Active->value) {
                return true;
            }

            if ($data['state'] === DelegationState::Expired->value && is_string($data['expires_at']) && $data['expires_at'] !== '') {
                $expiresAt = new DateTimeImmutable($data['expires_at']);

                return $expiresAt >= $cutoff;
            }

            if ($data['state'] === DelegationState::Revoked->value && array_key_exists('revoked_at', $data) && is_string($data['revoked_at']) && $data['revoked_at'] !== '') {
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
     * @return array<string, mixed>
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
     * @param array<string, mixed> $data
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

        if (!is_array($metadata)) {
            $metadata = [];
        }

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
     * @return array<int, array<string, mixed>>
     */
    private function loadDelegations(): array
    {
        $cacheKey = $this->getCacheKey();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey]; // @phpstan-ignore return.type
        }

        if ($this->fileMode === FileMode::Single) {
            return $this->loadSingleFile();
        }

        return $this->loadMultipleFiles();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSingleFile(): array
    {
        $filePath = $this->buildPath('delegations', 'delegations', 'ini');

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
            $data = parse_ini_string($content, true, INI_SCANNER_TYPED);

            // @codeCoverageIgnoreStart
            if (!is_array($data)) {
                return [];
            }

            // @codeCoverageIgnoreEnd

            /** @var array<int, array<string, mixed>> */
            return array_values($data);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadMultipleFiles(): array
    {
        $dirPath = $this->buildDirectoryPath('delegations');

        /** @var array<int, array<string, mixed>> $delegations */
        $delegations = [];

        if (!File::isDirectory($dirPath)) {
            return [];
        }

        $files = glob(sprintf('%s/*.ini', $dirPath));

        // @codeCoverageIgnoreStart
        if ($files === false) {
            return [];
        }

        // @codeCoverageIgnoreEnd

        foreach ($files as $filePath) {
            // @codeCoverageIgnoreStart
            if (!File::isFile($filePath)) {
                continue; // @codeCoverageIgnoreEnd
            }

            $content = File::get($filePath);

            // @codeCoverageIgnoreStart
            // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
            if ($content === false) {
                continue; // @codeCoverageIgnoreEnd
            }

            try {
                $data = parse_ini_string($content, true, INI_SCANNER_TYPED);

                if (is_array($data)) {
                    foreach ($data as $section) {
                        if (!is_array($section)) {
                            continue;
                        }

                        /** @var array<string, mixed> $section */
                        $delegations[] = $section;
                    }
                }
            } catch (Exception) {
                continue;
            }
        }

        // @codeCoverageIgnoreEnd

        return $delegations;
    }

    /**
     * @param array<int, array<string, mixed>> $delegations
     */
    private function saveDelegations(array $delegations): void
    {
        if ($this->fileMode === FileMode::Single) {
            $filePath = $this->buildPath('delegations', 'delegations', 'ini');
            $dir = dirname($filePath);

            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0o755, true);
            }

            $ini = '';

            foreach ($delegations as $index => $data) {
                /** @var array<string, mixed> $data */
                $ini .= $this->encodeSection('delegation_'.$index, $data);
            }

            File::put($filePath, $ini);
        } else {
            foreach ($delegations as $data) {
                $id = $data['id'] ?? throw new InvalidArgumentException('Delegation missing id');
                throw_unless(is_string($id), InvalidArgumentException::class, 'Delegation id must be string');

                $this->writeToMultipleFile($id, $data);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function appendToSingleFile(array $data): void
    {
        $delegations = $this->loadDelegations();
        $delegations[] = $data;

        $this->saveDelegations($delegations);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeToMultipleFile(string $id, array $data): void
    {
        $filePath = $this->buildPath('delegations', $id, 'ini');
        $dir = dirname($filePath);

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0o755, true);
        }

        $ini = $this->encodeSection($id, $data);
        File::put($filePath, $ini);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeSection(string $sectionName, array $data): string
    {
        $ini = sprintf("[%s]\n", $sectionName);

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $ini .= sprintf("%s = \"%s\"\n", $key, $value);
            } elseif (is_scalar($value)) {
                $ini .= sprintf("%s = %s\n", $key, (string) $value);
            }
        }

        return $ini."\n";
    }

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

    private function getCacheKey(): string
    {
        return sprintf(
            'delegations:%s:%s',
            $this->resolveVersion('delegations') ?? 'latest',
            $this->fileMode->value,
        );
    }
}
