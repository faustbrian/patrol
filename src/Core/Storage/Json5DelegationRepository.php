<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Carbon\CarbonImmutable;
use ColinODell\Json5\Json5Decoder;
use DateTimeImmutable;
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
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function is_array;
use function is_bool;
use function is_dir;
use function is_int;
use function is_string;
use function json_encode;
use function mkdir;
use function sprintf;
use function throw_unless;

/**
 * JSON5 file-based delegation repository.
 *
 * Uses colinodell/json5 for parsing JSON5 files with human-friendly features
 * like comments and trailing commas. Writes standard JSON for compatibility.
 *
 * @see FileStorageBase For versioning and path management
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Json5DelegationRepository extends FileStorageBase implements DelegationRepositoryInterface
{
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

    #[Override()]
    public function cleanup(): int
    {
        $delegations = $this->loadDelegations();
        $retentionDays = 90;
        $cutoff = CarbonImmutable::now()->modify(sprintf('-%d days', $retentionDays));

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
     * @param array<string, mixed> $data
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
     * @return array<int, array<string, mixed>>
     */
    private function loadDelegations(): array
    {
        $cacheKey = $this->getCacheKey();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
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
        $filePath = $this->buildPath('delegations', 'delegations', 'json5');

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        // @codeCoverageIgnoreStart
        if ($content === false) {
            return [];
        }

        /** @codeCoverageIgnoreEnd */

        // @phpstan-ignore class.notFound (ColinODell\Json5\Json5Decoder is an optional dependency)
        $data = Json5Decoder::decode($content, true);

        if (!is_array($data)) {
            return [];
        }

        return $data; // @phpstan-ignore return.type
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadMultipleFiles(): array
    {
        $dirPath = $this->buildDirectoryPath('delegations');
        $delegations = [];

        if (!is_dir($dirPath)) {
            return [];
        }

        $files = glob(sprintf('%s/*.json5', $dirPath));

        if ($files === false) {
            return [];
        }

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);

            // @codeCoverageIgnoreStart
            if ($content === false) {
                continue;
            }

            // @phpstan-ignore class.notFound (ColinODell\Json5\Json5Decoder is an optional dependency)
            $data = Json5Decoder::decode($content, true);

            if (is_array($data)) {
                /** @var array<string, mixed> $data */
                $delegations[] = $data;
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
            $filePath = $this->buildPath('delegations', 'delegations', 'json5');
            $dir = dirname($filePath);

            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }

            file_put_contents($filePath, json_encode($delegations, JSON_PRETTY_PRINT));
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

        $filePath = $this->buildPath('delegations', 'delegations', 'json5');
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($filePath, json_encode($delegations, JSON_PRETTY_PRINT));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeToMultipleFile(string $id, array $data): void
    {
        $filePath = $this->buildPath('delegations', $id, 'json5');
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
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
