<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use InvalidArgumentException;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\StorageDriver;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

use function function_exists;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;
use function storage_path;
use function sys_get_temp_dir;

/**
 * Factory for creating storage repository instances.
 *
 * Maps storage drivers to concrete repository implementations with proper
 * configuration and dependency injection. Handles both policy and delegation
 * repositories across all supported storage backends.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class StorageFactory implements StorageFactoryInterface
{
    /**
     * Create policy repository for specified driver.
     *
     * Instantiates appropriate repository implementation based on driver type.
     * Applies configuration for file path, versioning, and file mode from config
     * array. Falls back to sensible defaults if config keys are missing.
     *
     * @param  StorageDriver             $driver Storage driver to instantiate
     * @param  array<string, mixed>      $config Driver-specific configuration array
     * @return PolicyRepositoryInterface Configured repository instance
     */
    #[Override()]
    public function createPolicyRepository(
        StorageDriver $driver,
        array $config,
    ): PolicyRepositoryInterface {
        return match ($driver) {
            StorageDriver::Eloquent => new DatabasePolicyRepository(
                connection: is_string($config['connection'] ?? null) ? $config['connection'] : 'default',
            ),
            StorageDriver::Json => new JsonPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Yaml => new YamlPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Xml => new XmlPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Toml => new TomlPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Csv => new CsvPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Ini => new IniPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Json5 => new Json5PolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
            StorageDriver::Serialized => new SerializedPolicyRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
            ),
        };
    }

    /**
     * Create delegation repository for specified driver.
     *
     * Instantiates appropriate repository implementation based on driver type.
     * Currently supports Eloquent and JSON drivers. Other drivers throw
     * InvalidArgumentException until implemented.
     *
     * @param StorageDriver        $driver Storage driver to instantiate
     * @param array<string, mixed> $config Driver-specific configuration array
     *
     * @throws InvalidArgumentException If driver doesn't support delegations yet
     *
     * @return DelegationRepositoryInterface Configured repository instance
     */
    #[Override()]
    public function createDelegationRepository(
        StorageDriver $driver,
        array $config,
    ): DelegationRepositoryInterface {
        return match ($driver) {
            StorageDriver::Eloquent => new DatabaseDelegationRepository(
                connection: is_string($config['connection'] ?? null) ? $config['connection'] : 'default',
            ),
            StorageDriver::Json => new JsonDelegationRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
                retentionDays: is_int($config['retention_days'] ?? null) ? $config['retention_days'] : 90,
            ),
            StorageDriver::Csv => new CsvDelegationRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
                retentionDays: is_int($config['retention_days'] ?? null) ? $config['retention_days'] : 90,
            ),
            StorageDriver::Ini => new IniDelegationRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
                retentionDays: is_int($config['retention_days'] ?? null) ? $config['retention_days'] : 90,
            ),
            StorageDriver::Json5 => new Json5DelegationRepository(
                basePath: is_string($config['path'] ?? null) ? $config['path'] : $this->getDefaultPath(),
                fileMode: ($config['file_mode'] ?? null) instanceof FileMode ? $config['file_mode'] : FileMode::Multiple,
                version: is_string($config['version'] ?? null) ? $config['version'] : null,
                versioningEnabled: is_array($config['versioning'] ?? null) && is_bool($config['versioning']['enabled'] ?? null) ? $config['versioning']['enabled'] : true,
                retentionDays: is_int($config['retention_days'] ?? null) ? $config['retention_days'] : 90,
            ),
            default => throw new InvalidArgumentException(
                sprintf('Delegation storage not yet implemented for driver: %s', $driver->value),
            ),
        };
    }

    /**
     * Get default storage path.
     *
     * Uses Laravel's storage_path() helper if available, otherwise falls back to temp dir.
     *
     * @return string Default base path for file storage
     */
    private function getDefaultPath(): string
    {
        if (function_exists('storage_path')) {
            return storage_path('patrol');
        }

        // @codeCoverageIgnoreStart
        return sys_get_temp_dir().'/patrol';
        // @codeCoverageIgnoreEnd
    }
}
