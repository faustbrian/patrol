<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    // Clear all cache before each test
    Cache::flush();
});

afterEach(function (): void {
    // Clean up after each test
    Cache::flush();
});

describe('PatrolClearCacheCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('clears default cache store successfully', function (): void {
            // Arrange
            Cache::put('patrol:test:key', 'test-value', 60);
            expect(Cache::has('patrol:test:key'))->toBeTrue();

            // Act & Assert
            $this->artisan('patrol:clear-cache')
                ->expectsOutputToContain('Clearing Patrol cache')
                ->expectsOutputToContain('Patrol cache cleared successfully')
                ->assertExitCode(0);

            expect(Cache::has('patrol:test:key'))->toBeFalse();
        });

        test('displays success message after clearing cache', function (): void {
            // Arrange
            Cache::put('patrol:policies:user:123', 'cached-policies', 60);

            // Act & Assert
            $this->artisan('patrol:clear-cache')
                ->expectsOutputToContain('Patrol cache cleared successfully')
                ->assertSuccessful();
        });

        test('clears cache even when cache is empty', function (): void {
            // Arrange - cache is already empty from beforeEach

            // Act & Assert
            $this->artisan('patrol:clear-cache')
                ->expectsOutputToContain('Clearing Patrol cache')
                ->expectsOutputToContain('Patrol cache cleared successfully')
                ->assertSuccessful();
        });

        test('clears cache with multiple patrol entries', function (): void {
            // Arrange
            Cache::put('patrol:policies:user:1', 'policy-1', 60);
            Cache::put('patrol:policies:user:2', 'policy-2', 60);
            Cache::put('patrol:delegations:user:1', 'delegation-1', 60);

            expect(Cache::has('patrol:policies:user:1'))->toBeTrue();
            expect(Cache::has('patrol:policies:user:2'))->toBeTrue();
            expect(Cache::has('patrol:delegations:user:1'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert
            expect(Cache::has('patrol:policies:user:1'))->toBeFalse();
            expect(Cache::has('patrol:policies:user:2'))->toBeFalse();
            expect(Cache::has('patrol:delegations:user:1'))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception for non-existent cache store', function (): void {
            // Arrange - try to clear a non-existent cache store

            // Act & Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Cache store [nonexistent] is not defined.');

            $this->artisan('patrol:clear-cache', ['--store' => 'nonexistent']);
        });

        test('throws exception when invalid store option provided', function (): void {
            // Arrange - invalid store name

            // Act & Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Cache store [invalid-store-name] is not defined.');

            $this->artisan('patrol:clear-cache', ['--store' => 'invalid-store-name']);
        });
    });

    describe('Edge Cases', function (): void {
        test('clears specific cache store when store option provided', function (): void {
            // Arrange
            $arrayCache = Cache::store('array');
            $arrayCache->put('patrol:test:array', 'array-value', 60);

            expect($arrayCache->has('patrol:test:array'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache', ['--store' => 'array'])
                ->expectsOutputToContain('Clearing Patrol cache')
                ->expectsOutputToContain('Patrol cache cleared successfully')
                ->assertSuccessful();

            // Assert
            expect($arrayCache->has('patrol:test:array'))->toBeFalse();
        });

        test('handles empty string store option by using default', function (): void {
            // Arrange
            Cache::put('patrol:test:default', 'default-value', 60);
            expect(Cache::has('patrol:test:default'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache', ['--store' => ''])
                ->assertSuccessful();

            // Assert - empty string should trigger default behavior
            expect(Cache::has('patrol:test:default'))->toBeFalse();
        });

        test('clears file cache store when specified', function (): void {
            // Arrange
            $fileCache = Cache::store('file');
            $fileCache->put('patrol:test:file', 'file-value', 60);

            expect($fileCache->has('patrol:test:file'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache', ['--store' => 'file'])
                ->assertSuccessful();

            // Assert
            expect($fileCache->has('patrol:test:file'))->toBeFalse();
        });

        test('handles null store option as default cache', function (): void {
            // Arrange
            Cache::put('patrol:test:null', 'null-value', 60);
            expect(Cache::has('patrol:test:null'))->toBeTrue();

            // Act - no store option means use default
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert
            expect(Cache::has('patrol:test:null'))->toBeFalse();
        });

        test('clears cache with large number of keys', function (): void {
            // Arrange
            for ($i = 0; $i < 100; ++$i) {
                Cache::put('patrol:test:key:'.$i, 'value-'.$i, 60);
            }

            // Verify some keys exist
            expect(Cache::has('patrol:test:key:0'))->toBeTrue();
            expect(Cache::has('patrol:test:key:50'))->toBeTrue();
            expect(Cache::has('patrol:test:key:99'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert all keys are cleared
            expect(Cache::has('patrol:test:key:0'))->toBeFalse();
            expect(Cache::has('patrol:test:key:50'))->toBeFalse();
            expect(Cache::has('patrol:test:key:99'))->toBeFalse();
        });

        test('clears cache containing complex data structures', function (): void {
            // Arrange
            $complexData = [
                'policies' => [
                    ['subject' => 'user:1', 'action' => 'read', 'effect' => 'allow'],
                    ['subject' => 'user:2', 'action' => 'write', 'effect' => 'deny'],
                ],
                'metadata' => [
                    'timestamp' => Date::now()->getTimestamp(),
                    'version' => '1.0.0',
                ],
            ];

            Cache::put('patrol:complex:data', $complexData, 60);
            expect(Cache::has('patrol:complex:data'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert
            expect(Cache::has('patrol:complex:data'))->toBeFalse();
        });

        test('command can be called multiple times consecutively', function (): void {
            // Arrange
            Cache::put('patrol:test:repeat', 'repeat-value', 60);

            // Act - call command multiple times
            $this->artisan('patrol:clear-cache')->assertSuccessful();
            $this->artisan('patrol:clear-cache')->assertSuccessful();
            $this->artisan('patrol:clear-cache')->assertSuccessful();

            // Assert - no errors on repeated calls
            expect(Cache::has('patrol:test:repeat'))->toBeFalse();
        });

        test('clears cache with special characters in keys', function (): void {
            // Arrange
            Cache::put('patrol:test:user@example.com', 'email-value', 60);
            Cache::put('patrol:test:user-123_456', 'hyphen-underscore', 60);
            Cache::put('patrol:test:key.with.dots', 'dot-value', 60);

            // Act
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert
            expect(Cache::has('patrol:test:user@example.com'))->toBeFalse();
            expect(Cache::has('patrol:test:user-123_456'))->toBeFalse();
            expect(Cache::has('patrol:test:key.with.dots'))->toBeFalse();
        });

        test('handles cache store with long expiration times', function (): void {
            // Arrange - cache with very long TTL
            Cache::put('patrol:test:long-ttl', 'long-lived-value', 86_400 * 365); // 1 year
            expect(Cache::has('patrol:test:long-ttl'))->toBeTrue();

            // Act
            $this->artisan('patrol:clear-cache')
                ->assertSuccessful();

            // Assert
            expect(Cache::has('patrol:test:long-ttl'))->toBeFalse();
        });
    });
});
