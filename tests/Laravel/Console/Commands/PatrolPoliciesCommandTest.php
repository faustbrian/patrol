<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('PatrolPoliciesCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('lists all policies from database', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 10,
                    'domain' => 'default',
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 5,
                    'domain' => 'admin',
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 2 policy rule(s):')
                ->expectsOutputToContain('user:123')
                ->expectsOutputToContain('user:789')
                ->assertSuccessful();
        });

        test('displays policies in priority order descending', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:1',
                    'resource' => 'doc:1',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:2',
                    'resource' => 'doc:2',
                    'action' => 'write',
                    'effect' => 'allow',
                    'priority' => 100,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:3',
                    'resource' => 'doc:3',
                    'action' => 'delete',
                    'effect' => 'deny',
                    'priority' => 50,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 3 policy rule(s):')
                ->assertSuccessful();
        });

        test('shows ALLOW effect in green color', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('ALLOW')
                ->assertSuccessful();
        });

        test('shows DENY effect in red color', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'delete',
                'effect' => 'deny',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('DENY')
                ->assertSuccessful();
        });

        test('displays table headers correctly', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->assertSuccessful();
        });
    });

    describe('Sad Paths', function (): void {
        test('shows warning when no policies found', function (): void {
            // Arrange - no policies in database

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('No policies found')
                ->assertSuccessful();
        });

        test('shows warning when filtered by subject with no results', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--subject' => 'user:999'])
                ->expectsOutputToContain('No policies found')
                ->assertSuccessful();
        });

        test('shows warning when filtered by resource with no results', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--resource' => 'file:999'])
                ->expectsOutputToContain('No policies found')
                ->assertSuccessful();
        });

        test('throws exception for invalid table name', function (): void {
            // Arrange - use non-existent table name

            // Act & Assert
            $this->expectException(QueryException::class);

            $this->artisan('patrol:policies', ['--table' => 'nonexistent_table']);
        });
    });

    describe('Edge Cases', function (): void {
        test('filters policies by subject option', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--subject' => 'user:123'])
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->expectsOutputToContain('user:123')
                ->assertSuccessful();
        });

        test('filters policies by resource option', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--resource' => 'file:999'])
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->expectsOutputToContain('user:789')
                ->assertSuccessful();
        });

        test('filters policies by both subject and resource options', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'deny',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', [
                '--subject' => 'user:123',
                '--resource' => 'document:456',
            ])
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->expectsOutputToContain('user:123')
                ->assertSuccessful();
        });

        test('displays wildcard resource as asterisk', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:admin',
                'resource' => null,
                'action' => 'manage',
                'effect' => 'allow',
                'priority' => 100,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('*')
                ->assertSuccessful();
        });

        test('displays dash for null domain', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('-')
                ->assertSuccessful();
        });

        test('displays actual domain value when present', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => 'admin',
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('admin')
                ->assertSuccessful();
        });

        test('uses custom table name from option', function (): void {
            // Arrange - create custom table
            DB::statement('
                CREATE TABLE custom_policies (
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

            DB::table('custom_policies')->insert([
                'subject' => 'user:custom',
                'resource' => 'document:custom',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--table' => 'custom_policies'])
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->expectsOutputToContain('user:custom')
                ->assertSuccessful();

            // Cleanup
            DB::statement('DROP TABLE custom_policies');
        });

        test('handles special characters in subject identifiers', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:test@example.com',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('user:test@example.com')
                ->assertSuccessful();
        });

        test('handles special characters in resource identifiers', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'file:my-file_name.pdf',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('file:my-file_name.pdf')
                ->assertSuccessful();
        });

        test('handles actions with hyphens and underscores', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read-write_access',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('read-write_access')
                ->assertSuccessful();
        });

        test('handles large number of policies', function (): void {
            // Arrange - insert 100 policies
            $policies = [];

            for ($i = 1; $i <= 100; ++$i) {
                $policies[] = [
                    'subject' => 'user:'.$i,
                    'resource' => 'document:'.$i,
                    'action' => 'read',
                    'effect' => $i % 2 === 0 ? 'allow' : 'deny',
                    'priority' => $i,
                    'domain' => null,
                ];
            }

            DB::table('patrol_policies')->insert($policies);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 100 policy rule(s):')
                ->assertSuccessful();
        });

        test('handles long subject identifiers without breaking output', function (): void {
            // Arrange
            $longSubject = 'user:'.str_repeat('a', 200);

            DB::table('patrol_policies')->insert([
                'subject' => $longSubject,
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->assertSuccessful();
        });

        test('handles long resource identifiers without breaking output', function (): void {
            // Arrange
            $longResource = 'document:'.str_repeat('b', 200);

            DB::table('patrol_policies')->insert([
                'subject' => 'user:123',
                'resource' => $longResource,
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->assertSuccessful();
        });

        test('handles negative priority values', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => -10,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 5,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 2 policy rule(s):')
                ->assertSuccessful();
        });

        test('handles very high priority values', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:admin',
                'resource' => 'system:all',
                'action' => 'manage',
                'effect' => 'allow',
                'priority' => 999_999,
                'domain' => 'superadmin',
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('999999')
                ->assertSuccessful();
        });

        test('filters with empty string subject option treats as no filter', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--subject' => ''])
                ->expectsOutputToContain('Found 2 policy rule(s):')
                ->assertSuccessful();
        });

        test('filters with empty string resource option treats as no filter', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 1,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:789',
                    'resource' => 'file:999',
                    'action' => 'write',
                    'effect' => 'deny',
                    'priority' => 1,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies', ['--resource' => ''])
                ->expectsOutputToContain('Found 2 policy rule(s):')
                ->assertSuccessful();
        });

        test('displays policies with mixed allow and deny effects', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'allow',
                    'priority' => 10,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'delete',
                    'effect' => 'deny',
                    'priority' => 20,
                    'domain' => null,
                ],
                [
                    'subject' => 'user:123',
                    'resource' => 'document:789',
                    'action' => 'write',
                    'effect' => 'allow',
                    'priority' => 15,
                    'domain' => null,
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 3 policy rule(s):')
                ->expectsOutputToContain('ALLOW')
                ->expectsOutputToContain('DENY')
                ->assertSuccessful();
        });

        test('handles unicode characters in identifiers', function (): void {
            // Arrange
            DB::table('patrol_policies')->insert([
                'subject' => 'user:用户123',
                'resource' => 'document:文档456',
                'action' => 'read',
                'effect' => 'allow',
                'priority' => 1,
                'domain' => null,
            ]);

            // Act & Assert
            $this->artisan('patrol:policies')
                ->expectsOutputToContain('Found 1 policy rule(s):')
                ->assertSuccessful();
        });
    });
});
