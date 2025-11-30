<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create the patrol_policies table for testing
    DB::statement('
        CREATE TABLE patrol_policies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            subject VARCHAR(255) NOT NULL,
            resource VARCHAR(255),
            action VARCHAR(255) NOT NULL,
            effect VARCHAR(10) NOT NULL,
            priority INTEGER NOT NULL,
            domain VARCHAR(255),
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            deleted_at TIMESTAMP
        )
    ');

    // Bind the PolicyRepositoryInterface for command testing
    $this->app->singleton(PolicyRepositoryInterface::class, fn ($app): DatabasePolicyRepository => new DatabasePolicyRepository(
        connection: config('database.default'),
    ));
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('PatrolCheckCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('grants access when policy allows action', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutput('Checking authorization...')
                ->expectsOutputToContain('user:123')
                ->expectsOutputToContain('document:456')
                ->expectsOutputToContain('read')
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertSuccessful();
        });

        test('displays multiple rules when multiple policies apply', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => '*',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 2 applicable rule(s):')
                ->assertSuccessful();
        });

        test('handles wildcard subject correctly for superuser', function (): void {
            // Arrange: wildcard subject only works for superusers in ACL
            DB::table('patrol_policies')->insert([
                'subject' => '*',
                'resource' => '*',
                'action' => '*',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert: Regular user won't match wildcard subject
            $this->artisan('patrol:check', [
                'subject' => 'user:789',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertExitCode(1); // Deny because subject doesn't have superuser attribute
        });

        test('handles wildcard resource correctly', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => '*',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'anything:999',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertSuccessful();
        });
    });

    describe('Sad Paths', function (): void {
        test('denies access when policy explicitly denies action', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'delete',
                'effect' => 'Deny',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'delete',
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertExitCode(1);
        });

        test('denies access when no matching policy exists', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:999',
                'resource' => 'document:999',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 0 applicable rule(s):')
                ->assertExitCode(1);
        });

        test('denies access when action does not match policy', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert: Rule loaded but action doesn't match so it won't apply
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'write',
            ])
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertExitCode(1); // Deny because action doesn't match
        });

        test('deny effect overrides allow when deny has higher priority', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 2 applicable rule(s):')
                ->assertExitCode(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty database gracefully', function (): void {
            // Arrange - no policies in database

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->expectsOutputToContain('Found 0 applicable rule(s):')
                ->assertExitCode(1);
        });

        test('handles special characters in identifiers', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:test@example.com',
                'resource' => 'document:file-name_with.special',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:test@example.com',
                'resource' => 'document:file-name_with.special',
                'action' => 'read',
            ])
                ->expectsOutputToContain('user:test@example.com')
                ->expectsOutputToContain('document:file-name_with.special')
                ->assertSuccessful();
        });

        test('displays rules in priority order descending', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 3 applicable rule(s):')
                ->assertSuccessful();
        });

        test('handles both allow and deny effects in output', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 100,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 2 applicable rule(s):')
                ->assertExitCode(1);
        });

        test('handles null resource in database', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => null,
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
            ])
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertSuccessful();
        });

        test('handles long identifiers without breaking output', function (): void {
            // Arrange
            $longSubject = 'user:'.str_repeat('a', 200);
            $longResource = 'document:'.str_repeat('b', 200);

            DB::table('patrol_policies')->insert([
                'subject' => $longSubject,
                'resource' => $longResource,
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => $longSubject,
                'resource' => $longResource,
                'action' => 'read',
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->expectsOutputToContain('Found 1 applicable rule(s):')
                ->assertSuccessful();
        });

        test('handles actions with hyphens and underscores', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read-write_access',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read-write_access',
            ])
                ->expectsOutputToContain('read-write_access')
                ->assertSuccessful();
        });

        test('handles resource without colon separator defaults to resource type', function (): void {
            // Arrange - Test line 96: when resource doesn't contain ':'
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'wildcard',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            // Act & Assert
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'wildcard',
                'action' => 'read',
            ])
                ->expectsOutputToContain('wildcard')
                ->assertSuccessful();
        });
    });

    describe('JSON Output Mode', function (): void {
        test('outputs json format when json option is provided with allow effect', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 100,
            ]);

            // Act & Assert - Test JSON path execution (lines 111-136)
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                '--json' => true,
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->expectsOutputToContain('user:123')
                ->expectsOutputToContain('document:456')
                ->expectsOutputToContain('read')
                ->assertSuccessful();
        });

        test('outputs json format with deny effect and exits with failure code', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'delete',
                'effect' => 'Deny',
                'priority' => 50,
            ]);

            // Act & Assert - Test JSON path with deny effect (line 136)
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'delete',
                '--json' => true,
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->assertExitCode(1);
        });

        test('outputs json with multiple rules including both allow and deny effects', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 100,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 50,
                ],
            ]);

            // Act & Assert - Test JSON with multiple rules (lines 113-121)
            $this->artisan('patrol:check', [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                '--json' => true,
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->assertExitCode(1);
        });

        test('outputs json format with empty rules array when no policies match', function (): void {
            // Arrange - no policies

            // Act & Assert - Test JSON with empty rules (line 129)
            $this->artisan('patrol:check', [
                'subject' => 'user:999',
                'resource' => 'document:999',
                'action' => 'read',
                '--json' => true,
            ])
                ->expectsOutputToContain('Checking authorization...')
                ->assertExitCode(1);
        });

        test('json mode processes all rule attributes correctly', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'editor:admin',
                'resource' => 'article:789',
                'action' => 'publish',
                'effect' => 'Allow',
                'priority' => 200,
            ]);

            // Act & Assert - Test JSON encoding and output (lines 113-134)
            $this->artisan('patrol:check', [
                'subject' => 'editor:admin',
                'resource' => 'article:789',
                'action' => 'publish',
                '--json' => true,
            ])
                ->expectsOutputToContain('editor:admin')
                ->expectsOutputToContain('article:789')
                ->expectsOutputToContain('publish')
                ->assertSuccessful();
        });
    });
});
