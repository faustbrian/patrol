<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Patrol\PatrolServiceProvider;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PatrolServiceProvider::class,
        ];
    }
}
