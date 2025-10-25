<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use InvalidArgumentException;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\StorageDriver;

/**
 * Manages storage driver lifecycle and runtime driver switching.
 *
 * Provides Laravel-style driver management with fluent API for switching storage
 * backends at runtime. Supports separate drivers for policies and delegations,
 * version specification, and file mode configuration for file-based storage.
 *
 * ```php
 * // Use default configured driver
 * $policies = $manager->policy()->getPoliciesFor($subject, $resource);
 *
 * // Switch to YAML storage for auditing
 * $policies = $manager->driver(StorageDriver::Yaml)
 *     ->version('1.2.0')
 *     ->policy()
 *     ->getPoliciesFor($subject, $resource);
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class StorageManager
{
    /**
     * Create a new storage manager.
     *
     * @param StorageDriver           $driver  Default storage driver from config
     * @param array<string, mixed>    $config  Storage configuration array
     * @param StorageFactoryInterface $factory Factory for creating storage instances
     */
    public function __construct(
        private StorageDriver $driver,
        private array $config,
        private StorageFactoryInterface $factory,
    ) {}

    /**
     * Switch to a different storage driver.
     *
     * Creates a new instance with the specified driver active. Chainable with
     * version() and fileMode() for full configuration control.
     *
     * @param  StorageDriver $driver The storage driver to use
     * @return self          New instance with driver configured
     */
    public function driver(StorageDriver $driver): self
    {
        return new self(
            driver: $driver,
            config: $this->config,
            factory: $this->factory,
        );
    }

    /**
     * Set specific version for file-based storage.
     *
     * Overrides automatic version detection to load a specific historical version.
     * Useful for auditing and compliance scenarios requiring access to past policies.
     *
     * @param  string $version Semantic version string (e.g., '1.2.0')
     * @return self   New instance with version configured
     */
    public function version(string $version): self
    {
        $config = $this->config;
        $config['version'] = $version;

        return new self(
            driver: $this->driver,
            config: $config,
            factory: $this->factory,
        );
    }

    /**
     * Set file mode for file-based storage.
     *
     * Switches between single-file and multiple-file storage modes at runtime.
     * Only applicable to file-based drivers (JSON, YAML, XML, TOML, Serialized).
     *
     * @param  FileMode $mode The file mode to use
     * @return self     New instance with file mode configured
     */
    public function fileMode(FileMode $mode): self
    {
        $config = $this->config;
        $config['file_mode'] = $mode;

        return new self(
            driver: $this->driver,
            config: $config,
            factory: $this->factory,
        );
    }

    /**
     * Get policy repository for current driver configuration.
     *
     * Creates a policy repository instance using the active driver, version,
     * and file mode settings. Returns cached instance if available.
     *
     * @throws InvalidArgumentException If driver doesn't support policy storage
     *
     * @return PolicyRepositoryInterface Policy repository for current configuration
     */
    public function policy(): PolicyRepositoryInterface
    {
        return $this->factory->createPolicyRepository(
            driver: $this->driver,
            config: $this->config,
        );
    }

    /**
     * Get delegation repository for current driver configuration.
     *
     * Creates a delegation repository instance using the active driver, version,
     * and file mode settings. Returns cached instance if available.
     *
     * @throws InvalidArgumentException If driver doesn't support delegation storage
     *
     * @return DelegationRepositoryInterface Delegation repository for current configuration
     */
    public function delegation(): DelegationRepositoryInterface
    {
        return $this->factory->createDelegationRepository(
            driver: $this->driver,
            config: $this->config,
        );
    }
}
