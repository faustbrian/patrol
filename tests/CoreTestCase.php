<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for framework-agnostic core tests.
 *
 * NO Laravel dependencies allowed in tests extending this class.
 *
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class CoreTestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
