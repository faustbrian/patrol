<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\ValueObjects\Subject;

use function array_map;
use function assert;
use function count;
use function implode;
use function is_string;
use function sprintf;

/**
 * Artisan command to list active delegations for a user.
 *
 * Displays all active delegations where the specified user is the delegate
 * (receiver of delegated permissions). Shows key delegation details including
 * delegator, resources, actions, and expiration time. Useful for auditing,
 * debugging, and understanding the current delegation state.
 *
 * Usage:
 *   php artisan patrol:delegation:list user:123
 *   php artisan patrol:delegation:list role:editor
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolDelegationListCommand extends Command
{
    /**
     * The command signature with arguments.
     *
     * @var string
     */
    protected $signature = 'patrol:delegation:list
                            {user : The user ID to query}';

    /**
     * The command description shown in artisan list.
     *
     * @var string
     */
    protected $description = 'List active delegations for a user';

    /**
     * Execute the delegation listing command.
     *
     * Retrieves and displays all active delegations for the specified user subject.
     * Shows delegation details in a table format including ID, delegator, resources,
     * actions, and expiration date. Returns success even if no delegations are found.
     *
     * @param  DelegationManager $manager The delegation manager for querying active delegations
     * @return int               Command exit code (always SUCCESS)
     */
    public function handle(DelegationManager $manager): int
    {
        $userIdRaw = $this->argument('user');

        assert(is_string($userIdRaw), 'User argument must be a string');

        $userId = $userIdRaw;
        $subject = new Subject($userId);

        $delegations = $manager->findActiveDelegations($subject);

        if ($delegations === []) {
            $this->components->warn(sprintf('No active delegations found for %s', $userId));

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d active delegation(s) for %s:', count($delegations), $userId));
        $this->newLine();

        $headers = ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'];
        $rows = array_map(fn ($delegation): array => [
            $delegation->id,
            $delegation->delegatorId,
            implode(', ', $delegation->scope->resources),
            implode(', ', $delegation->scope->actions),
            $delegation->expiresAt?->format('Y-m-d H:i:s') ?? 'Never',
        ], $delegations);

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
