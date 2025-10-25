<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

/**
 * Pure PHP fixture for testing Subject behavior.
 * NO Laravel dependencies.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SubjectFixture
{
    public function __construct(
        public string $id,
        public array $attributes = [],
        public array $roles = [],
    ) {}

    public static function user(string $id = 'user-1', array $attributes = []): self
    {
        return new self($id, $attributes);
    }

    public static function admin(string $id = 'admin-1'): self
    {
        return new self($id, ['superuser' => true], ['admin']);
    }

    public function withRole(string $role): self
    {
        return new self(
            $this->id,
            $this->attributes,
            [...$this->roles, $role],
        );
    }

    public function withAttribute(string $key, mixed $value): self
    {
        return new self(
            $this->id,
            [...$this->attributes, $key => $value],
            $this->roles,
        );
    }
}
