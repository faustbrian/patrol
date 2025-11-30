<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Laravel\Support;

use function array_merge;

/**
 * Test model fixture for resolver testing.
 *
 * Provides a minimal model-like object with toArray() method
 * for testing resource resolution without requiring full Eloquent setup.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestModel
{
    public function __construct(
        public int|string $id,
        public array $attributes = [],
    ) {}

    public function toArray(): array
    {
        return array_merge([
            'id' => $this->id,
        ], $this->attributes);
    }
}
