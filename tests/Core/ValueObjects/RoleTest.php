<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Role;

describe('Role', function (): void {
    describe('Happy Paths', function (): void {
        test('creates role with name only', function (): void {
            // Arrange & Act
            $role = new Role('admin');

            // Assert
            expect($role->name)->toBe('admin');
            expect($role->domain)->toBeNull();
        });

        test('creates role with name and domain', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $role = new Role('editor', $domain);

            // Assert
            expect($role->name)->toBe('editor');
            expect($role->domain)->toBe($domain);
            expect($role->domain->id)->toBe('tenant-1');
        });

        test('creates role with domain containing attributes', function (): void {
            // Arrange
            $domain = new Domain('tenant-1', [
                'organization' => 'Acme Corp',
                'tier' => 'enterprise',
            ]);

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->name)->toBe('admin');
            expect($role->domain)->toBe($domain);
            expect($role->domain->attributes)->toBe([
                'organization' => 'Acme Corp',
                'tier' => 'enterprise',
            ]);
        });

        test('supports common role names', function (): void {
            // Arrange & Act
            $admin = new Role('admin');
            $editor = new Role('editor');
            $viewer = new Role('viewer');
            $owner = new Role('owner');
            $guest = new Role('guest');

            // Assert
            expect($admin->name)->toBe('admin');
            expect($editor->name)->toBe('editor');
            expect($viewer->name)->toBe('viewer');
            expect($owner->name)->toBe('owner');
            expect($guest->name)->toBe('guest');
        });

        test('supports namespaced role names', function (): void {
            // Arrange & Act
            $role = new Role('organization:admin');

            // Assert
            expect($role->name)->toBe('organization:admin');
        });

        test('creates multiple independent roles with same domain', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $admin = new Role('admin', $domain);
            $editor = new Role('editor', $domain);

            // Assert
            expect($admin->name)->toBe('admin');
            expect($editor->name)->toBe('editor');
            expect($admin->domain)->toBe($domain);
            expect($editor->domain)->toBe($domain);
        });

        test('creates multiple independent roles with different domains', function (): void {
            // Arrange
            $domain1 = new Domain('tenant-1');
            $domain2 = new Domain('tenant-2');

            // Act
            $role1 = new Role('admin', $domain1);
            $role2 = new Role('admin', $domain2);

            // Assert
            expect($role1->name)->toBe('admin');
            expect($role2->name)->toBe('admin');
            expect($role1->domain)->toBe($domain1);
            expect($role2->domain)->toBe($domain2);
            expect($role1->domain->id)->toBe('tenant-1');
            expect($role2->domain->id)->toBe('tenant-2');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty string role name', function (): void {
            // Arrange & Act
            $role = new Role('');

            // Assert
            expect($role->name)->toBe('');
            expect($role->domain)->toBeNull();
        });

        test('handles single character role name', function (): void {
            // Arrange & Act
            $role = new Role('a');

            // Assert
            expect($role->name)->toBe('a');
        });

        test('handles very long role name', function (): void {
            // Arrange
            $longName = str_repeat('admin', 1_000);

            // Act
            $role = new Role($longName);

            // Assert
            expect($role->name)->toBe($longName);
            expect(mb_strlen($role->name))->toBe(5_000);
        });

        test('handles role name with special characters', function (): void {
            // Arrange & Act
            $role = new Role('admin@organization.com');

            // Assert
            expect($role->name)->toBe('admin@organization.com');
        });

        test('handles role name with unicode characters', function (): void {
            // Arrange & Act
            $role = new Role('管理员');

            // Assert
            expect($role->name)->toBe('管理员');
        });

        test('handles role name with emojis', function (): void {
            // Arrange & Act
            $role = new Role('admin-👑');

            // Assert
            expect($role->name)->toBe('admin-👑');
        });

        test('handles role name with whitespace', function (): void {
            // Arrange & Act
            $role = new Role('super admin');

            // Assert
            expect($role->name)->toBe('super admin');
        });

        test('handles role name with leading/trailing whitespace', function (): void {
            // Arrange & Act
            $role = new Role('  admin  ');

            // Assert
            expect($role->name)->toBe('  admin  ');
        });

        test('handles role name with newlines', function (): void {
            // Arrange & Act
            $role = new Role("admin\nuser");

            // Assert
            expect($role->name)->toBe("admin\nuser");
        });

        test('handles role name with tabs', function (): void {
            // Arrange & Act
            $role = new Role("admin\tuser");

            // Assert
            expect($role->name)->toBe("admin\tuser");
        });

        test('preserves case sensitivity in role names', function (): void {
            // Arrange & Act
            $lower = new Role('admin');
            $upper = new Role('ADMIN');
            $mixed = new Role('Admin');

            // Assert
            expect($lower->name)->toBe('admin');
            expect($upper->name)->toBe('ADMIN');
            expect($mixed->name)->toBe('Admin');
            expect($lower->name)->not->toBe($upper->name);
            expect($lower->name)->not->toBe($mixed->name);
        });

        test('handles role name with numeric characters', function (): void {
            // Arrange & Act
            $role = new Role('admin123');

            // Assert
            expect($role->name)->toBe('admin123');
        });

        test('handles role name with only numbers', function (): void {
            // Arrange & Act
            $role = new Role('12345');

            // Assert
            expect($role->name)->toBe('12345');
        });

        test('handles role name with hyphens and underscores', function (): void {
            // Arrange & Act
            $role = new Role('super-admin_level-1');

            // Assert
            expect($role->name)->toBe('super-admin_level-1');
        });

        test('handles role name with dots and colons', function (): void {
            // Arrange & Act
            $role = new Role('organization.department:admin');

            // Assert
            expect($role->name)->toBe('organization.department:admin');
        });

        test('handles role name with path-like structure', function (): void {
            // Arrange & Act
            $role = new Role('organization/department/admin');

            // Assert
            expect($role->name)->toBe('organization/department/admin');
        });

        test('handles role name with URL-like structure', function (): void {
            // Arrange & Act
            $role = new Role('https://example.com/roles/admin');

            // Assert
            expect($role->name)->toBe('https://example.com/roles/admin');
        });

        test('handles null domain explicitly', function (): void {
            // Arrange & Act
            $role = new Role('admin');

            // Assert
            expect($role->name)->toBe('admin');
            expect($role->domain)->toBeNull();
        });

        test('handles domain with empty id', function (): void {
            // Arrange
            $domain = new Domain('');

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->domain)->toBe($domain);
            expect($role->domain->id)->toBe('');
        });

        test('handles domain with special characters', function (): void {
            // Arrange
            $domain = new Domain('tenant-123@organization.com');

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->domain)->toBe($domain);
            expect($role->domain->id)->toBe('tenant-123@organization.com');
        });

        test('handles domain with unicode characters', function (): void {
            // Arrange
            $domain = new Domain('租户-123');

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->domain)->toBe($domain);
            expect($role->domain->id)->toBe('租户-123');
        });

        test('handles domain with complex nested attributes', function (): void {
            // Arrange
            $domain = new Domain('tenant-1', [
                'metadata' => [
                    'nested' => [
                        'deep' => [
                            'value' => 'test',
                        ],
                    ],
                ],
                'features' => ['feature1', 'feature2'],
            ]);

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->domain)->toBe($domain);
            expect($role->domain->attributes['metadata']['nested']['deep']['value'])->toBe('test');
        });

        test('handles domain with empty attributes array', function (): void {
            // Arrange
            $domain = new Domain('tenant-1', []);

            // Act
            $role = new Role('admin', $domain);

            // Assert
            expect($role->domain)->toBe($domain);
            expect($role->domain->attributes)->toBe([]);
        });
    });

    describe('Immutability', function (): void {
        test('is readonly class', function (): void {
            // Arrange & Act & Assert
            expect(Role::class)->toBeReadonly();
        });

        test('properties are readonly', function (): void {
            // Arrange
            $role = new Role('admin');

            // Act & Assert - This would fail at compile time in strict mode
            // We verify the class is declared as readonly which makes all properties readonly
            $reflection = new ReflectionClass(Role::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        test('domain property is readonly', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');
            $role = new Role('admin', $domain);

            // Act & Assert - Verify domain is accessible but immutable
            expect($role->domain)->toBe($domain);

            $reflection = new ReflectionClass(Role::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        test('creates new instance instead of modifying existing', function (): void {
            // Arrange
            $originalDomain = new Domain('tenant-1');
            $role1 = new Role('admin', $originalDomain);

            // Act - Create a new role instead of modifying
            $newDomain = new Domain('tenant-2');
            $role2 = new Role('admin', $newDomain);

            // Assert - Original role unchanged
            expect($role1->domain)->toBe($originalDomain);
            expect($role1->domain->id)->toBe('tenant-1');
            expect($role2->domain)->toBe($newDomain);
            expect($role2->domain->id)->toBe('tenant-2');
        });
    });

    describe('Type Safety', function (): void {
        test('enforces strict string type for name', function (): void {
            // Arrange & Act - PHP will handle type coercion
            $role = new Role('123');

            // Assert
            expect($role->name)->toBeString();
            expect($role->name)->toBe('123');
        });

        test('enforces Domain type or null for domain parameter', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $roleWithDomain = new Role('admin', $domain);
            $roleWithoutDomain = new Role('admin');

            // Assert
            expect($roleWithDomain->domain)->toBeInstanceOf(Domain::class);
            expect($roleWithoutDomain->domain)->toBeNull();
        });

        test('maintains type consistency across multiple instantiations', function (): void {
            // Arrange & Act
            $roles = [
                new Role('admin'),
                new Role('editor', new Domain('tenant-1')),
                new Role('viewer'),
                new Role('owner', new Domain('tenant-2')),
            ];

            // Assert
            foreach ($roles as $role) {
                expect($role)->toBeInstanceOf(Role::class);
                expect($role->name)->toBeString();

                // Domain must be either null or instance of Domain
                if (!$role->domain instanceof Domain) {
                    continue;
                }

                expect($role->domain)->toBeInstanceOf(Domain::class);
            }
        });
    });

    describe('Multi-tenant Scenarios', function (): void {
        test('differentiates global role from tenant-scoped role', function (): void {
            // Arrange
            $globalRole = new Role('admin');
            $tenantRole = new Role('admin', new Domain('tenant-1'));

            // Assert
            expect($globalRole->name)->toBe($tenantRole->name);
            expect($globalRole->domain)->toBeNull();
            expect($tenantRole->domain)->not->toBeNull();
            expect($tenantRole->domain->id)->toBe('tenant-1');
        });

        test('supports user with different roles in different tenants', function (): void {
            // Arrange & Act
            $adminInTenant1 = new Role('admin', new Domain('tenant-1'));
            $viewerInTenant2 = new Role('viewer', new Domain('tenant-2'));
            $editorInTenant3 = new Role('editor', new Domain('tenant-3'));

            // Assert
            expect($adminInTenant1->domain->id)->toBe('tenant-1');
            expect($viewerInTenant2->domain->id)->toBe('tenant-2');
            expect($editorInTenant3->domain->id)->toBe('tenant-3');
            expect($adminInTenant1->name)->not->toBe($viewerInTenant2->name);
        });

        test('supports same role name across multiple tenants', function (): void {
            // Arrange
            $domains = [
                new Domain('tenant-1'),
                new Domain('tenant-2'),
                new Domain('tenant-3'),
            ];

            // Act
            $roles = array_map(
                fn (Domain $domain): Role => new Role('admin', $domain),
                $domains,
            );

            // Assert
            expect($roles)->toHaveCount(3);

            foreach ($roles as $index => $role) {
                expect($role->name)->toBe('admin');
                expect($role->domain->id)->toBe('tenant-'.($index + 1));
            }
        });

        test('supports hierarchical domain structures', function (): void {
            // Arrange
            $organizationDomain = new Domain('org-1', [
                'name' => 'Acme Corp',
                'parent' => null,
            ]);

            $departmentDomain = new Domain('dept-1', [
                'name' => 'Engineering',
                'parent' => 'org-1',
            ]);

            // Act
            $orgAdmin = new Role('admin', $organizationDomain);
            $deptManager = new Role('manager', $departmentDomain);

            // Assert
            expect($orgAdmin->domain->attributes['parent'])->toBeNull();
            expect($deptManager->domain->attributes['parent'])->toBe('org-1');
        });

        test('supports security zone domains', function (): void {
            // Arrange
            $publicZone = new Domain('public', ['security_level' => 0]);
            $internalZone = new Domain('internal', ['security_level' => 1]);
            $restrictedZone = new Domain('restricted', ['security_level' => 2]);

            // Act
            $publicRole = new Role('viewer', $publicZone);
            $internalRole = new Role('editor', $internalZone);
            $restrictedRole = new Role('admin', $restrictedZone);

            // Assert
            expect($publicRole->domain->attributes['security_level'])->toBe(0);
            expect($internalRole->domain->attributes['security_level'])->toBe(1);
            expect($restrictedRole->domain->attributes['security_level'])->toBe(2);
        });
    });

    describe('Usage Patterns', function (): void {
        test('supports creating roles from configuration', function (): void {
            // Arrange
            $config = [
                ['name' => 'admin', 'domain' => null],
                ['name' => 'editor', 'domain' => 'tenant-1'],
                ['name' => 'viewer', 'domain' => 'tenant-2'],
            ];

            // Act
            $roles = array_map(
                fn (array $cfg): Role => new Role(
                    $cfg['name'],
                    $cfg['domain'] ? new Domain($cfg['domain']) : null,
                ),
                $config,
            );

            // Assert
            expect($roles)->toHaveCount(3);
            expect($roles[0]->name)->toBe('admin');
            expect($roles[0]->domain)->toBeNull();
            expect($roles[1]->domain->id)->toBe('tenant-1');
            expect($roles[2]->domain->id)->toBe('tenant-2');
        });

        test('supports collection of roles for a user', function (): void {
            // Arrange & Act
            $userRoles = [
                new Role('admin', new Domain('tenant-1')),
                new Role('editor', new Domain('tenant-2')),
                new Role('viewer'),
            ];

            // Assert
            expect($userRoles)->toHaveCount(3);
            expect($userRoles[0]->name)->toBe('admin');
            expect($userRoles[1]->name)->toBe('editor');
            expect($userRoles[2]->name)->toBe('viewer');
        });

        test('supports filtering roles by domain', function (): void {
            // Arrange
            $roles = [
                new Role('admin', new Domain('tenant-1')),
                new Role('editor', new Domain('tenant-2')),
                new Role('viewer', new Domain('tenant-1')),
                new Role('guest'),
            ];

            // Act
            $tenant1Roles = array_filter(
                $roles,
                fn (Role $role): bool => $role->domain?->id === 'tenant-1',
            );

            // Assert
            expect($tenant1Roles)->toHaveCount(2);
        });

        test('supports global roles mixed with tenant-specific roles', function (): void {
            // Arrange & Act
            $roles = [
                new Role('super-admin'),      // Global role
                new Role('system-admin'),     // Global role
                new Role('admin', new Domain('tenant-1')),
                new Role('admin', new Domain('tenant-2')),
            ];

            $globalRoles = array_filter($roles, fn (Role $role): bool => !$role->domain instanceof Domain);
            $tenantRoles = array_filter($roles, fn (Role $role): bool => $role->domain instanceof Domain);

            // Assert
            expect($globalRoles)->toHaveCount(2);
            expect($tenantRoles)->toHaveCount(2);
        });
    });
});
