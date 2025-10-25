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
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\ValueObjects\Delegation;

use function assert;
use function is_string;
use function sprintf;

/**
 * Artisan command to revoke a delegation by its ID.
 *
 * Marks the specified delegation as revoked, immediately removing it from
 * active authorization checks. The delegation record is retained for audit
 * purposes until cleanup processes remove it based on retention policies.
 * Requires the unique delegation identifier.
 *
 * Usage:
 *   php artisan patrol:delegation:revoke abc123-def456-789
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolDelegationRevokeCommand extends Command
{
    /**
     * The console command signature defining the command name and arguments.
     *
     * @var string
     */
    protected $signature = 'patrol:delegation:revoke
                            {id : The delegation ID to revoke}';

    /**
     * The console command description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'Revoke a delegation by its ID';

    /**
     * Execute the delegation revocation command.
     *
     * Validates the delegation exists, then marks it as revoked through the delegation
     * manager. The revocation is performed by a system subject to ensure proper audit
     * trail. Returns success/failure status code for shell script integration.
     *
     * @param  DelegationManager             $manager    The delegation manager for performing revocation operations
     * @param  DelegationRepositoryInterface $repository The delegation repository for finding existing delegations
     * @return int                           self::SUCCESS (0) if revocation succeeds, self::FAILURE (1) if delegation not found
     */
    public function handle(
        DelegationManager $manager,
        DelegationRepositoryInterface $repository,
    ): int {
        $delegationIdRaw = $this->argument('id');

        assert(is_string($delegationIdRaw), 'Delegation ID argument must be a string');

        $delegationId = $delegationIdRaw;

        // Check if delegation exists
        $delegation = $repository->findById($delegationId);

        if (!$delegation instanceof Delegation) {
            $this->components->error(sprintf('Delegation not found: %s', $delegationId));

            return self::FAILURE;
        }

        $this->components->task('Revoking delegation', function () use ($manager, $delegationId): true {
            $manager->revoke($delegationId);

            return true;
        });

        $this->components->success(sprintf('Delegation %s revoked successfully', $delegationId));

        return self::SUCCESS;
    }
}
