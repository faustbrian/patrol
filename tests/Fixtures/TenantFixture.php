<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

/**
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TenantFixture
{
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {}

    public static function create(string $id = 'tenant-1', array $attributes = []): self
    {
        return new self($id, $attributes);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self(
            $this->id,
            [...$this->attributes, $key => $value],
        );
    }
}
