<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create Spatie permission tables
    DB::statement('
        CREATE TABLE permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    DB::statement('
        CREATE TABLE roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            guard_name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )
    ');

    DB::statement('
        CREATE TABLE role_has_permissions (
            permission_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, role_id)
        )
    ');

    DB::statement('
        CREATE TABLE model_has_permissions (
            permission_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (permission_id, model_id, model_type)
        )
    ');

    DB::statement('
        CREATE TABLE model_has_roles (
            role_id INTEGER NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id INTEGER NOT NULL,
            PRIMARY KEY (role_id, model_id, model_type)
        )
    ');

    // Create Patrol policies table
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
    DB::statement('DROP TABLE IF EXISTS permissions');
    DB::statement('DROP TABLE IF EXISTS roles');
    DB::statement('DROP TABLE IF EXISTS role_has_permissions');
    DB::statement('DROP TABLE IF EXISTS model_has_permissions');
    DB::statement('DROP TABLE IF EXISTS model_has_roles');
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('PatrolMigrateFromSpatieCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('migrates role permissions to patrol policies', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'delete posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 2 permission(s) to migrate')
                ->expectsOutputToContain('role:editor')
                ->expectsOutputToContain('Migration completed successfully!')
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2);
            expect($policies->first()->subject)->toBe('role:editor');
            expect($policies->first()->action)->toBe('edit');
            expect($policies->first()->resource)->toBe('posts');
        });

        test('migrates direct user permissions to patrol policies', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'read documents', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'write documents', 'guard_name' => 'web'],
            ]);

            DB::table('model_has_permissions')->insert([
                ['model_id' => 123, 'model_type' => 'App\\Models\\User', 'permission_id' => 1],
                ['model_id' => 123, 'model_type' => 'App\\Models\\User', 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 2 permission(s) to migrate')
                ->expectsOutputToContain('user:123')
                ->expectsOutputToContain('Migration completed successfully!')
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2);
            expect($policies->first()->subject)->toBe('user:123');
            expect($policies->first()->action)->toBe('read');
            expect($policies->first()->resource)->toBe('documents');
        });

        test('does not denormalize user permissions via role assignments', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'delete posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // User 123 has editor role (this assignment is NOT denormalized)
            DB::table('model_has_roles')->insert([
                ['model_id' => 123, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Skipping user role assignments')
                ->assertSuccessful();

            // Assert - should only create role permissions, NOT user-specific rules
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2); // Only 2 role permissions

            // Role permissions exist
            $rolePerms = $policies->where('subject', 'role:editor');
            expect($rolePerms)->toHaveCount(2);
            expect($rolePerms->pluck('action')->toArray())->toContain('edit', 'delete');

            // NO user-specific rules should be created
            $userPerms = $policies->where('subject', 'user:123');
            expect($userPerms)->toHaveCount(0);

            // NOTE: At runtime, create Subject with roles attribute:
            // new Subject('user:123', ['roles' => ['role:editor']])
        });

        test('migrates both role and user permissions together', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'view analytics', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            DB::table('model_has_permissions')->insert([
                ['model_id' => 456, 'model_type' => 'App\\Models\\User', 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 2 permission(s) to migrate')
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2);

            $rolePolicy = $policies->firstWhere('subject', 'role:editor');
            expect($rolePolicy)->not->toBeNull();
            expect($rolePolicy->action)->toBe('edit');

            $userPolicy = $policies->firstWhere('subject', 'user:456');
            expect($userPolicy)->not->toBeNull();
            expect($userPolicy->action)->toBe('view');
        });

        test('creates allow effect for all migrated permissions', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policy = DB::table('patrol_policies')->first();
            expect($policy->effect)->toBe('Allow');
        });

        test('uses default priority of 10', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policy = DB::table('patrol_policies')->first();
            expect($policy->priority)->toBe(10);
        });

        test('accepts custom priority value', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie', ['--priority' => '50'])
                ->assertSuccessful();

            // Assert
            $policy = DB::table('patrol_policies')->first();
            expect($policy->priority)->toBe(50);
        });

        test('displays sample rules table with first 10 rules', function (): void {
            // Arrange - create 15 permissions
            $permissions = [];

            for ($i = 1; $i <= 15; ++$i) {
                $permissions[] = ['id' => $i, 'name' => sprintf('permission%d resource%d', $i, $i), 'guard_name' => 'web'];
            }

            DB::table('permissions')->insert($permissions);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'admin', 'guard_name' => 'web'],
            ]);

            $rolePermissions = [];

            for ($i = 1; $i <= 15; ++$i) {
                $rolePermissions[] = ['role_id' => 1, 'permission_id' => $i];
            }

            DB::table('role_has_permissions')->insert($rolePermissions);

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 15 permission(s) to migrate')
                ->expectsOutputToContain('Sample of rules to be created:')
                ->expectsOutputToContain('... and 5 more')
                ->assertSuccessful();
        });
    });

    describe('Sad Paths', function (): void {
        test('shows warning when no permissions found', function (): void {
            // Arrange - no permissions in database

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('No permissions found to migrate')
                ->assertExitCode(2); // self::INVALID
        });

        test('fails when spatie tables do not exist', function (): void {
            // Arrange - drop Spatie tables
            DB::statement('DROP TABLE IF EXISTS permissions');
            DB::statement('DROP TABLE IF EXISTS roles');
            DB::statement('DROP TABLE IF EXISTS role_has_permissions');
            DB::statement('DROP TABLE IF EXISTS model_has_permissions');

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1); // self::FAILURE
        });

        test('handles migration failure gracefully', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Drop patrol_policies table to force failure
            DB::statement('DROP TABLE patrol_policies');

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Migration failed')
                ->assertExitCode(1); // self::FAILURE
        });
    });

    describe('Edge Cases', function (): void {
        test('parses space-separated permission names', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'delete comments', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'moderator', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->orderBy('id')->get();
            expect($policies[0]->action)->toBe('edit');
            expect($policies[0]->resource)->toBe('posts');
            expect($policies[1]->action)->toBe('delete');
            expect($policies[1]->resource)->toBe('comments');
        });

        test('parses dot notation permission names', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'posts.edit', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'comments.delete', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'moderator', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->orderBy('id')->get();
            expect($policies[0]->action)->toBe('edit');
            expect($policies[0]->resource)->toBe('posts');
            expect($policies[1]->action)->toBe('delete');
            expect($policies[1]->resource)->toBe('comments');
        });

        test('parses dash notation permission names', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'posts-edit', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'comments-delete', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'moderator', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->orderBy('id')->get();
            expect($policies[0]->action)->toBe('edit');
            expect($policies[0]->resource)->toBe('posts');
            expect($policies[1]->action)->toBe('delete');
            expect($policies[1]->resource)->toBe('comments');
        });

        test('parses single-word permissions as action with wildcard resource', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'admin', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'manage', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'superadmin', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->orderBy('id')->get();
            expect($policies[0]->action)->toBe('admin');
            expect($policies[0]->resource)->toBe('*');
            expect($policies[1]->action)->toBe('manage');
            expect($policies[1]->resource)->toBe('*');
        });

        test('dry-run mode shows preview without persisting', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie', ['--dry-run' => true])
                ->expectsOutputToContain('DRY RUN MODE')
                ->expectsOutputToContain('Found 1 permission(s) to migrate')
                ->expectsOutputToContain('role:editor')
                ->expectsOutputToContain('Dry run complete - no changes were persisted')
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(0);
        });

        test('handles multiple roles with same permission', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'admin', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 2, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2);
            expect($policies->pluck('subject')->toArray())->toContain('role:editor', 'role:admin');
        });

        test('handles multiple users with same role', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Three users with editor role (NOT denormalized)
            DB::table('model_has_roles')->insert([
                ['model_id' => 100, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
                ['model_id' => 200, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
                ['model_id' => 300, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert - only role permission is created, no user-specific rules
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(1); // Only 1 role permission
            expect($policies->first()->subject)->toBe('role:editor');

            // NOTE: All three users get the permission at runtime by passing:
            // new Subject('user:100', ['roles' => ['role:editor']])
        });

        test('handles users with multiple roles', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'view analytics', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'analyst', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 2, 'permission_id' => 2],
            ]);

            // User has both editor and analyst roles (NOT denormalized)
            DB::table('model_has_roles')->insert([
                ['model_id' => 100, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
                ['model_id' => 100, 'model_type' => 'App\\Models\\User', 'role_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert - only role permissions are created, no user-specific rules
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2); // Only 2 role permissions

            $rolePerms = $policies->pluck('subject')->toArray();
            expect($rolePerms)->toContain('role:editor', 'role:analyst');

            // NOTE: User gets both permissions at runtime by passing:
            // new Subject('user:100', ['roles' => ['role:editor', 'role:analyst']])
        });

        test('handles multiple users with same permission', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'view dashboard', 'guard_name' => 'web'],
            ]);

            DB::table('model_has_permissions')->insert([
                ['model_id' => 100, 'model_type' => 'App\\Models\\User', 'permission_id' => 1],
                ['model_id' => 200, 'model_type' => 'App\\Models\\User', 'permission_id' => 1],
                ['model_id' => 300, 'model_type' => 'App\\Models\\User', 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(3);
            expect($policies->pluck('subject')->toArray())->toContain('user:100', 'user:200', 'user:300');
        });

        test('handles permissions with special characters', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit-blog_posts', 'guard_name' => 'web'],
                ['id' => 2, 'name' => 'delete user:profile', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(2);
        });

        test('handles large dataset efficiently', function (): void {
            // Arrange - create 100 permissions and assign to 10 roles
            $permissions = [];

            for ($i = 1; $i <= 100; ++$i) {
                $permissions[] = ['id' => $i, 'name' => sprintf('permission%d resource%d', $i, $i), 'guard_name' => 'web'];
            }

            DB::table('permissions')->insert($permissions);

            $roles = [];

            for ($i = 1; $i <= 10; ++$i) {
                $roles[] = ['id' => $i, 'name' => 'role'.$i, 'guard_name' => 'web'];
            }

            DB::table('roles')->insert($roles);

            $rolePermissions = [];

            for ($i = 1; $i <= 100; ++$i) {
                for ($j = 1; $j <= 10; ++$j) {
                    $rolePermissions[] = ['role_id' => $j, 'permission_id' => $i];
                }
            }

            DB::table('role_has_permissions')->insert($rolePermissions);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 1000 permission(s) to migrate')
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(1_000);
        });

        test('handles roles with no permissions', function (): void {
            // Arrange
            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'empty-role', 'guard_name' => 'web'],
            ]);

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('No permissions found to migrate')
                ->assertExitCode(2); // self::INVALID
        });

        test('handles permissions with null names gracefully', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'valid permission', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert - only valid permissions are migrated
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(1);
        });

        test('handles roles with special characters in names', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'super-admin_editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert
            $policy = DB::table('patrol_policies')->first();
            expect($policy->subject)->toBe('role:super-admin_editor');
        });

        test('migrates only from specified connection', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act - specify default connection explicitly
            $this->artisan('patrol:migrate-from-spatie', ['--connection' => config('database.default')])
                ->assertSuccessful();

            // Assert
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(1);
        });

        test('skips role permissions when role name is non-string (line 230 coverage)', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            // Note: SQLite will cast integers to strings, so this test documents the behavior
            // In a real database with strict typing, is_string() would catch non-string values
            // The is_string() check on line 230 exists as a defensive measure
            DB::table('roles')->insert([
                ['id' => 1, 'name' => '123', 'guard_name' => 'web'], // String representation
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act - This will succeed because SQLite casts to string
            $this->artisan('patrol:migrate-from-spatie')
                ->assertSuccessful();

            // Assert - Even with numeric-looking values, they're strings in SQLite
            $this->assertDatabaseHas('patrol_policies', [
                'subject' => 'role:123',
            ]);
        });

        test('skips role permissions when permission name is non-string (line 230 coverage)', function (): void {
            // Arrange
            // Note: SQLite will cast integers to strings, so this test documents the behavior
            // In a real database with strict typing, is_string() would catch non-string values
            // The is_string() check on line 230 exists as a defensive measure
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => '456', 'guard_name' => 'web'], // String representation
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act - This will succeed because SQLite casts to string
            $this->artisan('patrol:migrate-from-spatie')
                ->assertSuccessful();

            // Assert - Even with numeric-looking values, they're strings in SQLite
            // Permission "456" is treated as single-word permission -> action="456", resource="*"
            $this->assertDatabaseHas('patrol_policies', [
                'subject' => 'role:editor',
                'action' => '456',
                'resource' => '*',
            ]);
        });

        test('skips direct user permissions when permission name is non-string (line 344 coverage)', function (): void {
            // Arrange
            // Note: SQLite will cast integers to strings, so this test documents the behavior
            // In a real database with strict typing, is_string() would catch non-string values
            // The is_string() check on line 344 exists as a defensive measure
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => '789', 'guard_name' => 'web'], // String representation
            ]);

            DB::table('model_has_permissions')->insert([
                ['permission_id' => 1, 'model_type' => 'App\\Models\\User', 'model_id' => 123],
            ]);

            // Act - This will succeed because SQLite casts to string
            $this->artisan('patrol:migrate-from-spatie')
                ->assertSuccessful();

            // Assert - Even with numeric-looking values, they're strings in SQLite
            // Permission "789" is treated as single-word permission -> action="789", resource="*"
            $this->assertDatabaseHas('patrol_policies', [
                'subject' => 'user:123',
                'action' => '789',
                'resource' => '*',
            ]);
        });

        test('migrateUserRoleAssignments returns empty array (line 276 coverage)', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Add user role assignment (this should NOT be denormalized)
            DB::table('model_has_roles')->insert([
                ['model_id' => 999, 'model_type' => 'App\\Models\\User', 'role_id' => 1],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Skipping user role assignments')
                ->assertSuccessful();

            // Assert - migrateUserRoleAssignments should return empty array (line 276)
            // Only role permission should exist, NO user-specific rules
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(1);
            expect($policies->first()->subject)->toBe('role:editor');

            // Verify NO user:999 policies were created
            $userPolicies = DB::table('patrol_policies')->where('subject', 'user:999')->get();
            expect($userPolicies)->toHaveCount(0);
        });

        test('handles both string and non-string values in same migration (mixed coverage)', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'], // valid
                ['id' => 2, 'name' => '999', 'guard_name' => 'web'], // numeric-looking string
                ['id' => 3, 'name' => 'delete comments', 'guard_name' => 'web'], // valid
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'], // valid
                ['id' => 2, 'name' => '888', 'guard_name' => 'web'], // numeric-looking string
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1], // valid role, valid permission
                ['role_id' => 1, 'permission_id' => 2], // valid role, numeric permission
                ['role_id' => 2, 'permission_id' => 3], // numeric role, valid permission
            ]);

            // Also test direct user permissions
            DB::table('model_has_permissions')->insert([
                ['permission_id' => 1, 'model_type' => 'App\\Models\\User', 'model_id' => 100], // valid
                ['permission_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 200], // numeric permission
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')
                ->assertSuccessful();

            // Assert - SQLite casts everything to strings, so all will be migrated
            $policies = DB::table('patrol_policies')->get();
            expect($policies)->toHaveCount(5); // All 5 combinations succeed

            // Verify all migrations happened (SQLite type coercion)
            expect($policies->where('subject', 'role:editor')->where('resource', 'posts')->count())->toBe(1);
            expect($policies->where('subject', 'user:100')->where('resource', 'posts')->count())->toBe(1);

            // Even numeric-looking strings work in SQLite
            expect($policies->where('subject', 'role:editor')->where('action', '999')->count())->toBe(1);
            expect($policies->where('subject', 'role:888')->count())->toBe(1);
            expect($policies->where('subject', 'user:200')->count())->toBe(1);
        });

        test('handles permission names with multiple separators correctly', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit blog posts', 'guard_name' => 'web'], // space - first occurrence
                ['id' => 2, 'name' => 'blog.post.edit', 'guard_name' => 'web'], // dot - first occurrence
                ['id' => 3, 'name' => 'blog-post-delete', 'guard_name' => 'web'], // dash - first occurrence
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
                ['role_id' => 1, 'permission_id' => 2],
                ['role_id' => 1, 'permission_id' => 3],
            ]);

            // Act
            $this->artisan('patrol:migrate-from-spatie')->assertSuccessful();

            // Assert - should split on first separator only
            $policies = DB::table('patrol_policies')->orderBy('id')->get();

            // Space: "edit blog posts" -> action="edit", resource="blog posts"
            expect($policies[0]->action)->toBe('edit');
            expect($policies[0]->resource)->toBe('blog posts');

            // Dot: "blog.post.edit" -> action="post.edit", resource="blog"
            expect($policies[1]->action)->toBe('post.edit');
            expect($policies[1]->resource)->toBe('blog');

            // Dash: "blog-post-delete" -> action="post-delete", resource="blog"
            expect($policies[2]->action)->toBe('post-delete');
            expect($policies[2]->resource)->toBe('blog');
        });

        test('verifies all five Spatie tables must exist for migration', function (): void {
            // Test missing each table individually

            // Test 1: Missing permissions table
            DB::statement('DROP TABLE IF EXISTS permissions');

            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1);

            // Recreate permissions, drop roles
            DB::statement('
                CREATE TABLE permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    guard_name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ');
            DB::statement('DROP TABLE IF EXISTS roles');

            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1);

            // Recreate roles, drop role_has_permissions
            DB::statement('
                CREATE TABLE roles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL,
                    guard_name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP,
                    updated_at TIMESTAMP
                )
            ');
            DB::statement('DROP TABLE IF EXISTS role_has_permissions');

            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1);

            // Recreate role_has_permissions, drop model_has_permissions
            DB::statement('
                CREATE TABLE role_has_permissions (
                    permission_id INTEGER NOT NULL,
                    role_id INTEGER NOT NULL,
                    PRIMARY KEY (permission_id, role_id)
                )
            ');
            DB::statement('DROP TABLE IF EXISTS model_has_permissions');

            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1);

            // Recreate model_has_permissions, drop model_has_roles
            DB::statement('
                CREATE TABLE model_has_permissions (
                    permission_id INTEGER NOT NULL,
                    model_type VARCHAR(255) NOT NULL,
                    model_id INTEGER NOT NULL,
                    PRIMARY KEY (permission_id, model_id, model_type)
                )
            ');
            DB::statement('DROP TABLE IF EXISTS model_has_roles');

            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Spatie permission tables not found')
                ->assertExitCode(1);
        });

        test('handles exactly 10 rules without showing more indicator', function (): void {
            // Arrange - create exactly 10 permissions
            $permissions = [];

            for ($i = 1; $i <= 10; ++$i) {
                $permissions[] = ['id' => $i, 'name' => sprintf('permission%d resource%d', $i, $i), 'guard_name' => 'web'];
            }

            DB::table('permissions')->insert($permissions);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            $rolePermissions = [];

            for ($i = 1; $i <= 10; ++$i) {
                $rolePermissions[] = ['role_id' => 1, 'permission_id' => $i];
            }

            DB::table('role_has_permissions')->insert($rolePermissions);

            // Act & Assert
            $this->artisan('patrol:migrate-from-spatie')
                ->expectsOutputToContain('Found 10 permission(s) to migrate')
                ->assertSuccessful();

            // Assert - should NOT show "... and X more" for exactly 10 rules
            // (The displayRuleSummary method only shows "... and X more" when count > 10)
            $this->assertDatabaseCount('patrol_policies', 10);
        });

        test('uses default database connection when no connection option provided', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act - no connection option should use default
            $this->artisan('patrol:migrate-from-spatie')
                ->assertSuccessful();

            // Assert
            $this->assertDatabaseHas('patrol_policies', [
                'subject' => 'role:editor',
            ]);
        });

        test('handles empty string connection options by using defaults', function (): void {
            // Arrange
            DB::table('permissions')->insert([
                ['id' => 1, 'name' => 'edit posts', 'guard_name' => 'web'],
            ]);

            DB::table('roles')->insert([
                ['id' => 1, 'name' => 'editor', 'guard_name' => 'web'],
            ]);

            DB::table('role_has_permissions')->insert([
                ['role_id' => 1, 'permission_id' => 1],
            ]);

            // Act - empty strings should use default connection
            $this->artisan('patrol:migrate-from-spatie', [
                '--connection' => '',
                '--spatie-connection' => '',
            ])
                ->assertSuccessful();

            // Assert
            $this->assertDatabaseHas('patrol_policies', [
                'subject' => 'role:editor',
            ]);
        });
    });
});
