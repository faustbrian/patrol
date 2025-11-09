<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Patrol\Core\Contracts\DelegationRepositoryInterface;

use function sprintf;

/**
 * Artisan command to remove expired and revoked delegations.
 *
 * Performs housekeeping by cleaning up delegation records that are no longer
 * needed for active authorization checks. Removes expired delegations and old
 * revoked delegations based on the configured retention period. This prevents
 * unbounded growth of the delegations table and improves query performance.
 *
 * Usage:
 *   php artisan patrol:delegation:cleanup
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolDelegationCleanupCommand extends Command
{
    /**
     * The command signature.
     *
     * @var string
     */
    protected $signature = 'patrol:delegation:cleanup';

    /**
     * The command description shown in artisan list.
     *
     * @var string
     */
    protected $description = 'Remove expired and revoked delegations older than retention period';

    /**
     * Execute the delegation cleanup command.
     *
     * Invokes the repository's cleanup method to remove stale delegation records.
     * Displays the count of removed delegations upon completion. This should be
     * run periodically (e.g., via scheduled task) to maintain database hygiene.
     *
     * @param  DelegationRepositoryInterface $repository The delegation repository
     * @return int                           Command exit code (always SUCCESS)
     */
    public function handle(DelegationRepositoryInterface $repository): int
    {
        $this->components->task('Cleaning up delegations', function () use ($repository, &$count): true {
            $count = $repository->cleanup();

            return true;
        });

        $this->components->success(sprintf('Removed %d delegation(s)', $count));

        return self::SUCCESS;
    }
}
