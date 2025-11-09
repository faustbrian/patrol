<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function assert;
use function is_string;
use function sprintf;

/**
 * Artisan command to list policies from the database.
 *
 * Displays all policy rules stored in the policies table, optionally filtered
 * by subject or resource identifier. Useful for auditing, debugging, and
 * understanding the current authorization configuration.
 *
 * Usage:
 *   php artisan patrol:policies
 *   php artisan patrol:policies --subject=user:123
 *   php artisan patrol:policies --resource=document:456
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolPoliciesCommand extends Command
{
    /**
     * The console command signature with options for filtering policies.
     *
     * @var string
     */
    protected $signature = 'patrol:policies
                            {--subject= : Filter by subject identifier}
                            {--resource= : Filter by resource identifier}
                            {--table=patrol_policies : The database table name}';

    /**
     * The console command description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'List all policies or filter by subject/resource';

    /**
     * Execute the policies listing command.
     *
     * Queries the database for policy rules and displays them in a formatted table.
     * Supports optional filtering by subject or resource identifier. Uses color coding
     * to distinguish between ALLOW and DENY effects for better readability.
     *
     * @return int self::SUCCESS (0) always, even when no policies are found
     */
    public function handle(): int
    {
        $tableOption = $this->option('table');
        $subjectOption = $this->option('subject');
        $resourceOption = $this->option('resource');

        assert(is_string($tableOption), 'Table option must be a string');
        $table = $tableOption;

        $query = DB::table($table);

        if (is_string($subjectOption) && $subjectOption !== '') {
            $query->where('subject', $subjectOption);
        }

        if (is_string($resourceOption) && $resourceOption !== '') {
            $query->where('resource', $resourceOption);
        }

        $policies = $query->orderBy('priority', 'desc')->get();

        if ($policies->isEmpty()) {
            $this->components->warn('No policies found');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d policy rule(s):', $policies->count()));
        $this->newLine();

        $headers = ['ID', 'Subject', 'Resource', 'Action', 'Effect', 'Priority', 'Domain'];
        $rows = $policies->map(fn ($policy): array => [
            $policy->id,
            $policy->subject,
            $policy->resource ?? '*',
            $policy->action,
            $policy->effect === 'allow' ? '<fg=green>ALLOW</>' : '<fg=red>DENY</>',
            $policy->priority,
            $policy->domain ?? '-',
        ]);

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
