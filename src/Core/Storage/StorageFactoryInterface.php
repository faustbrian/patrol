<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\StorageDriver;

/**
 * Factory contract for creating storage repository instances.
 *
 * Implementations handle driver-specific instantiation logic, dependency
 * resolution, and configuration mapping. Supports both policy and delegation
 * repositories across all storage drivers.
 *
 * @see StorageManager For usage context
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface StorageFactoryInterface
{
    /**
     * Create a policy repository for the specified driver.
     *
     * Instantiates and configures a policy repository implementation matching
     * the storage driver. Handles driver-specific dependencies and configuration
     * requirements.
     *
     * @param  StorageDriver             $driver Storage driver to create repository for
     * @param  array<string, mixed>      $config Driver-specific configuration
     * @return PolicyRepositoryInterface Configured policy repository instance
     */
    public function createPolicyRepository(
        StorageDriver $driver,
        array $config,
    ): PolicyRepositoryInterface;

    /**
     * Create a delegation repository for the specified driver.
     *
     * Instantiates and configures a delegation repository implementation matching
     * the storage driver. Handles driver-specific dependencies and configuration
     * requirements.
     *
     * @param  StorageDriver                 $driver Storage driver to create repository for
     * @param  array<string, mixed>          $config Driver-specific configuration
     * @return DelegationRepositoryInterface Configured delegation repository instance
     */
    public function createDelegationRepository(
        StorageDriver $driver,
        array $config,
    ): DelegationRepositoryInterface;
}
