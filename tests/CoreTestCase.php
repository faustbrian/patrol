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
 * Base test case for core tests with Laravel environment.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class CoreTestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PatrolServiceProvider::class,
        ];
    }

    protected function createSubject(string $id = 'user-1', array $attributes = []): object
    {
        return new readonly class($id, $attributes)
        {
            public function __construct(
                public string $id,
                public array $attributes = [],
            ) {}
        };
    }

    protected function createResource(
        string $id = 'resource-1',
        string $type = 'document',
        array $attributes = [],
    ): object {
        return new readonly class($id, $type, $attributes)
        {
            public function __construct(
                public string $id,
                public string $type,
                public array $attributes = [],
            ) {}
        };
    }
}
