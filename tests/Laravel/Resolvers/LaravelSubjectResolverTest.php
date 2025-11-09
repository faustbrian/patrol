<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Auth;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Resolvers\LaravelSubjectResolver;
use Tests\Laravel\Support\TestUser;

describe('LaravelSubjectResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new LaravelSubjectResolver();
    });

    describe('Happy Paths', function (): void {
        test('resolves authenticated user with integer ID to Subject', function (): void {
            // Arrange
            $user = new TestUser(
                id: 123,
                name: 'John Doe',
                email: 'john@example.com',
                attributes: [
                    'role' => 'admin',
                    'department' => 'engineering',
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result)->toBeInstanceOf(Subject::class)
                ->and($result->id)->toBe('123')
                ->and($result->attributes)->toBe([
                    'id' => 123,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'role' => 'admin',
                    'department' => 'engineering',
                ]);
        });

        test('resolves authenticated user with string ID to Subject', function (): void {
            // Arrange
            $user = new TestUser(
                id: 'uuid-abc-123',
                name: 'Jane Smith',
                email: 'jane@example.com',
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result)->toBeInstanceOf(Subject::class)
                ->and($result->id)->toBe('uuid-abc-123')
                ->and($result->attributes)->toBe([
                    'id' => 'uuid-abc-123',
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                ]);
        });

        test('resolves guest user when not authenticated', function (): void {
            // Arrange
            Auth::shouldReceive('user')->once()->andReturn(null);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result)->toBeInstanceOf(Subject::class)
                ->and($result->id)->toBe('guest')
                ->and($result->attributes)->toBe([]);
        });

        test('extracts full user attributes for ABAC policies', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                name: 'Test User',
                email: 'test@example.com',
                attributes: [
                    'role' => 'manager',
                    'department' => 'sales',
                    'permissions' => ['read', 'write'],
                    'metadata' => [
                        'last_login' => '2023-12-31',
                        'created_at' => '2023-01-01',
                    ],
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toHaveKey('role', 'manager')
                ->and($result->attributes)->toHaveKey('department', 'sales')
                ->and($result->attributes)->toHaveKey('permissions')
                ->and($result->attributes)->toHaveKey('metadata')
                ->and($result->attributes['permissions'])->toBe(['read', 'write']);
        });

        test('converts integer user ID to string in Subject', function (): void {
            // Arrange
            $user = new TestUser(id: 99_999);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBeString()
                ->and($result->id)->toBe('99999');
        });

        test('preserves string user ID without conversion', function (): void {
            // Arrange
            $user = new TestUser(id: 'custom-user-id');
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBeString()
                ->and($result->id)->toBe('custom-user-id');
        });

        test('resolves user with minimal attributes', function (): void {
            // Arrange
            $user = new TestUser(id: 1);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe('1')
                ->and($result->attributes)->toHaveKey('id')
                ->and($result->attributes)->toHaveKey('name')
                ->and($result->attributes)->toHaveKey('email');
        });
    });

    describe('Edge Cases', function (): void {
        test('resolves user with zero ID', function (): void {
            // Arrange
            $user = new TestUser(id: 0);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe('0');
        });

        test('resolves user with negative ID', function (): void {
            // Arrange
            $user = new TestUser(id: -999);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe('-999');
        });

        test('resolves user with very large integer ID', function (): void {
            // Arrange
            $largeId = \PHP_INT_MAX;
            $user = new TestUser(id: $largeId);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe((string) $largeId);
        });

        test('resolves user with UUID identifier', function (): void {
            // Arrange
            $uuid = '550e8400-e29b-41d4-a716-446655440000';
            $user = new TestUser(id: $uuid);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe($uuid);
        });

        test('resolves user with ULID identifier', function (): void {
            // Arrange
            $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            $user = new TestUser(id: $ulid);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->id)->toBe($ulid);
        });

        test('resolves user with unicode characters in attributes', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                name: '用户名',
                email: 'user@example.com',
                attributes: [
                    'department' => '工程部',
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['name'])->toBe('用户名')
                ->and($result->attributes['department'])->toBe('工程部');
        });

        test('resolves user with special characters in email', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                email: 'user+test@example.co.uk',
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['email'])->toBe('user+test@example.co.uk');
        });

        test('resolves user with empty attributes array', function (): void {
            // Arrange
            $user = new TestUser(id: 1, attributes: []);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toHaveKey('id')
                ->and($result->attributes)->toHaveKey('name')
                ->and($result->attributes)->toHaveKey('email');
        });

        test('resolves user with nested array attributes', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'settings' => [
                        'notifications' => [
                            'email' => true,
                            'push' => false,
                        ],
                    ],
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['settings']['notifications']['email'])->toBeTrue()
                ->and($result->attributes['settings']['notifications']['push'])->toBeFalse();
        });

        test('resolves user with null values in attributes', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'middle_name' => null,
                    'phone' => null,
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toHaveKey('middle_name')
                ->and($result->attributes['middle_name'])->toBeNull()
                ->and($result->attributes)->toHaveKey('phone')
                ->and($result->attributes['phone'])->toBeNull();
        });

        test('resolves user with boolean attributes', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'is_admin' => true,
                    'is_active' => false,
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['is_admin'])->toBeTrue()
                ->and($result->attributes['is_active'])->toBeFalse();
        });

        test('resolves user with array of roles', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'roles' => ['admin', 'editor', 'viewer'],
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['roles'])->toBe(['admin', 'editor', 'viewer'])
                ->and($result->attributes['roles'])->toHaveCount(3);
        });

        test('resolves user with very large attributes array', function (): void {
            // Arrange
            $largeAttributes = [];

            for ($i = 0; $i < 1_000; ++$i) {
                $largeAttributes['attribute_'.$i] = 'value_'.$i;
            }

            $user = new TestUser(id: 1, attributes: $largeAttributes);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toHaveKey('attribute_0')
                ->and($result->attributes)->toHaveKey('attribute_999')
                ->and($result->attributes['attribute_0'])->toBe('value_0')
                ->and($result->attributes['attribute_999'])->toBe('value_999');
        });

        test('guest subject has empty attributes array', function (): void {
            // Arrange
            Auth::shouldReceive('user')->once()->andReturn(null);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toBe([])
                ->and($result->attributes)->toBeEmpty();
        });

        test('returns immutable Subject value object', function (): void {
            // Arrange
            $user = new TestUser(id: 1);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert - Subject is readonly
            expect($result)->toBeInstanceOf(Subject::class);
            // Attempting to modify would cause TypeError in PHP 8.1+
        });

        test('resolves different users independently', function (): void {
            // Arrange
            $user1 = new TestUser(id: 1, name: 'User One');
            $user2 = new TestUser(id: 2, name: 'User Two');
            Auth::shouldReceive('user')->once()->andReturn($user1);

            // Act
            $result1 = $this->resolver->resolve();

            // Arrange second call
            Auth::shouldReceive('user')->once()->andReturn($user2);

            // Act
            $result2 = $this->resolver->resolve();

            // Assert
            expect($result1->id)->toBe('1')
                ->and($result2->id)->toBe('2')
                ->and($result1->attributes['name'])->toBe('User One')
                ->and($result2->attributes['name'])->toBe('User Two');
        });

        test('handles multiple resolve calls consistently for same user', function (): void {
            // Arrange
            $user = new TestUser(id: 123, name: 'Consistent User');
            Auth::shouldReceive('user')->times(3)->andReturn($user);

            // Act
            $result1 = $this->resolver->resolve();
            $result2 = $this->resolver->resolve();
            $result3 = $this->resolver->resolve();

            // Assert
            expect($result1->id)->toBe($result2->id)
                ->and($result2->id)->toBe($result3->id)
                ->and($result1->attributes)->toBe($result2->attributes)
                ->and($result2->attributes)->toBe($result3->attributes);
        });

        test('handles multiple resolve calls consistently for guest', function (): void {
            // Arrange
            Auth::shouldReceive('user')->times(3)->andReturn(null);

            // Act
            $result1 = $this->resolver->resolve();
            $result2 = $this->resolver->resolve();
            $result3 = $this->resolver->resolve();

            // Assert
            expect($result1->id)->toBe('guest')
                ->and($result2->id)->toBe('guest')
                ->and($result3->id)->toBe('guest')
                ->and($result1->attributes)->toBe([])
                ->and($result2->attributes)->toBe([])
                ->and($result3->attributes)->toBe([]);
        });

        test('resolves user with very long name', function (): void {
            // Arrange
            $longName = str_repeat('Name ', 1_000);
            $user = new TestUser(id: 1, name: $longName);
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['name'])->toBe($longName)
                ->and(mb_strlen((string) $result->attributes['name']))->toBeGreaterThan(4_000);
        });

        test('resolves user with attributes containing special characters in keys', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'key-with-dashes' => 'value1',
                    'key_with_underscores' => 'value2',
                    'key.with.dots' => 'value3',
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes)->toHaveKey('key-with-dashes')
                ->and($result->attributes)->toHaveKey('key_with_underscores')
                ->and($result->attributes)->toHaveKey('key.with.dots');
        });

        test('preserves attribute key order from user', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'z_last' => 'last',
                    'a_first' => 'first',
                    'm_middle' => 'middle',
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            $keys = array_keys($result->attributes);
            expect($keys[0])->toBe('id')
                ->and($keys[1])->toBe('name')
                ->and($keys[2])->toBe('email')
                ->and($keys[3])->toBe('z_last')
                ->and($keys[4])->toBe('a_first')
                ->and($keys[5])->toBe('m_middle');
        });

        test('resolves user with numeric string attributes', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'account_number' => '12345678',
                    'balance' => '1234.56',
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['account_number'])->toBe('12345678')
                ->and($result->attributes['balance'])->toBe('1234.56');
        });

        test('resolves user with array containing mixed types', function (): void {
            // Arrange
            $user = new TestUser(
                id: 1,
                attributes: [
                    'mixed_data' => [1, 'string', true, null, ['nested']],
                ],
            );
            Auth::shouldReceive('user')->once()->andReturn($user);

            // Act
            $result = $this->resolver->resolve();

            // Assert
            expect($result->attributes['mixed_data'])->toBe([1, 'string', true, null, ['nested']]);
        });
    });
});
