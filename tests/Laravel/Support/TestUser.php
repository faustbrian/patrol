<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Laravel\Support;

use Illuminate\Contracts\Auth\Authenticatable;

use function array_merge;

/**
 * Test user fixture for resolver testing.
 *
 * Provides a minimal implementation of Laravel's Authenticatable interface
 * for testing resource and subject resolution without requiring a full
 * Eloquent model setup.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class TestUser implements Authenticatable
{
    public function __construct(
        public int|string $id,
        public string $name = 'Test User',
        public string $email = 'test@example.com',
        public array $attributes = [],
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return 'password';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // No-op for testing
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function toArray(): array
    {
        return array_merge([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ], $this->attributes);
    }
}
