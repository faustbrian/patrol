<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

use function is_string;

/**
 * Artisan command to clear cached policies.
 *
 * Flushes the Patrol policy cache, forcing policies to be reloaded from the
 * underlying repository on the next authorization check. Use after policy
 * updates to ensure changes take immediate effect.
 *
 * Usage:
 *   php artisan patrol:clear-cache
 *   php artisan patrol:clear-cache --store=redis
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolClearCacheCommand extends Command
{
    /**
     * The command signature with options.
     *
     * @var string
     */
    protected $signature = 'patrol:clear-cache
                            {--store= : The cache store to clear (defaults to default)}';

    /**
     * The command description shown in artisan list.
     *
     * @var string
     */
    protected $description = 'Clear cached Patrol policies';

    /**
     * Execute the cache clearing command.
     *
     * Flushes the entire cache store (default or specified via --store option).
     * This ensures all cached policies are removed and will be reloaded on the
     * next authorization check.
     *
     * @return int Command exit code (always SUCCESS)
     */
    public function handle(): int
    {
        $storeOption = $this->option('store');

        $this->components->task('Clearing Patrol cache', function () use ($storeOption): true {
            if (is_string($storeOption) && $storeOption !== '') {
                Cache::store($storeOption)->getStore()->flush();
            } else {
                Cache::getStore()->flush();
            }

            return true;
        });

        $this->components->success('Patrol cache cleared successfully');

        return self::SUCCESS;
    }
}
