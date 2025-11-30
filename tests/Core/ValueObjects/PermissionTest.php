<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Permission;

describe('Permission', function (): void {
    describe('Happy Paths', function (): void {
        test('creates permission with name only', function (): void {
            // Arrange & Act
            $permission = new Permission('users.create');

            // Assert
            expect($permission->name)->toBe('users.create');
            expect($permission->domain)->toBeNull();
        });

        test('creates permission with name and domain', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $permission = new Permission('documents.read', $domain);

            // Assert
            expect($permission->name)->toBe('documents.read');
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->id)->toBe('tenant-1');
        });

        test('creates permission with domain containing attributes', function (): void {
            // Arrange
            $domain = new Domain('tenant-1', [
                'organization' => 'Acme Corp',
                'tier' => 'enterprise',
            ]);

            // Act
            $permission = new Permission('admin.users.manage', $domain);

            // Assert
            expect($permission->name)->toBe('admin.users.manage');
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->attributes)->toBe([
                'organization' => 'Acme Corp',
                'tier' => 'enterprise',
            ]);
        });

        test('supports common permission names', function (): void {
            // Arrange & Act
            $create = new Permission('users.create');
            $read = new Permission('users.read');
            $update = new Permission('users.update');
            $delete = new Permission('users.delete');
            $list = new Permission('users.list');

            // Assert
            expect($create->name)->toBe('users.create');
            expect($read->name)->toBe('users.read');
            expect($update->name)->toBe('users.update');
            expect($delete->name)->toBe('users.delete');
            expect($list->name)->toBe('users.list');
        });

        test('supports dot notation permission names', function (): void {
            // Arrange & Act
            $permission1 = new Permission('users.create');
            $permission2 = new Permission('documents.read');
            $permission3 = new Permission('reports.export');

            // Assert
            expect($permission1->name)->toBe('users.create');
            expect($permission2->name)->toBe('documents.read');
            expect($permission3->name)->toBe('reports.export');
        });

        test('supports hyphenated permission names', function (): void {
            // Arrange & Act
            $permission1 = new Permission('create-user');
            $permission2 = new Permission('read-document');
            $permission3 = new Permission('export-report');

            // Assert
            expect($permission1->name)->toBe('create-user');
            expect($permission2->name)->toBe('read-document');
            expect($permission3->name)->toBe('export-report');
        });

        test('supports hierarchical permission names', function (): void {
            // Arrange & Act
            $permission1 = new Permission('admin.users.manage');
            $permission2 = new Permission('api.documents.write');
            $permission3 = new Permission('system.config.update');

            // Assert
            expect($permission1->name)->toBe('admin.users.manage');
            expect($permission2->name)->toBe('api.documents.write');
            expect($permission3->name)->toBe('system.config.update');
        });

        test('creates multiple independent permissions with same domain', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $create = new Permission('users.create', $domain);
            $read = new Permission('users.read', $domain);

            // Assert
            expect($create->name)->toBe('users.create');
            expect($read->name)->toBe('users.read');
            expect($create->domain)->toBe($domain);
            expect($read->domain)->toBe($domain);
        });

        test('creates multiple independent permissions with different domains', function (): void {
            // Arrange
            $domain1 = new Domain('tenant-1');
            $domain2 = new Domain('tenant-2');

            // Act
            $permission1 = new Permission('users.create', $domain1);
            $permission2 = new Permission('users.create', $domain2);

            // Assert
            expect($permission1->name)->toBe('users.create');
            expect($permission2->name)->toBe('users.create');
            expect($permission1->domain)->toBe($domain1);
            expect($permission2->domain)->toBe($domain2);
            expect($permission1->domain->id)->toBe('tenant-1');
            expect($permission2->domain->id)->toBe('tenant-2');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty string permission name', function (): void {
            // Arrange & Act
            $permission = new Permission('');

            // Assert
            expect($permission->name)->toBe('');
            expect($permission->domain)->toBeNull();
        });

        test('handles single character permission name', function (): void {
            // Arrange & Act
            $permission = new Permission('x');

            // Assert
            expect($permission->name)->toBe('x');
        });

        test('handles very long permission name', function (): void {
            // Arrange
            $longName = str_repeat('users.create', 1_000);

            // Act
            $permission = new Permission($longName);

            // Assert
            expect($permission->name)->toBe($longName);
            expect(mb_strlen($permission->name))->toBe(12_000);
        });

        test('handles permission name with special characters', function (): void {
            // Arrange & Act
            $permission = new Permission('users@organization.com:read');

            // Assert
            expect($permission->name)->toBe('users@organization.com:read');
        });

        test('handles permission name with unicode characters', function (): void {
            // Arrange & Act
            $permission = new Permission('用户.创建');

            // Assert
            expect($permission->name)->toBe('用户.创建');
        });

        test('handles permission name with emojis', function (): void {
            // Arrange & Act
            $permission = new Permission('users.create-👤');

            // Assert
            expect($permission->name)->toBe('users.create-👤');
        });

        test('handles permission name with whitespace', function (): void {
            // Arrange & Act
            $permission = new Permission('create user account');

            // Assert
            expect($permission->name)->toBe('create user account');
        });

        test('handles permission name with leading/trailing whitespace', function (): void {
            // Arrange & Act
            $permission = new Permission('  users.create  ');

            // Assert
            expect($permission->name)->toBe('  users.create  ');
        });

        test('handles permission name with newlines', function (): void {
            // Arrange & Act
            $permission = new Permission("users\ncreate");

            // Assert
            expect($permission->name)->toBe("users\ncreate");
        });

        test('handles permission name with tabs', function (): void {
            // Arrange & Act
            $permission = new Permission("users\tcreate");

            // Assert
            expect($permission->name)->toBe("users\tcreate");
        });

        test('preserves case sensitivity in permission names', function (): void {
            // Arrange & Act
            $lower = new Permission('users.create');
            $upper = new Permission('USERS.CREATE');
            $mixed = new Permission('Users.Create');

            // Assert
            expect($lower->name)->toBe('users.create');
            expect($upper->name)->toBe('USERS.CREATE');
            expect($mixed->name)->toBe('Users.Create');
            expect($lower->name)->not->toBe($upper->name);
            expect($lower->name)->not->toBe($mixed->name);
        });

        test('handles permission name with numeric characters', function (): void {
            // Arrange & Act
            $permission = new Permission('users123.create');

            // Assert
            expect($permission->name)->toBe('users123.create');
        });

        test('handles permission name with only numbers', function (): void {
            // Arrange & Act
            $permission = new Permission('12345');

            // Assert
            expect($permission->name)->toBe('12345');
        });

        test('handles permission name with hyphens and underscores', function (): void {
            // Arrange & Act
            $permission = new Permission('create-user_account-level-1');

            // Assert
            expect($permission->name)->toBe('create-user_account-level-1');
        });

        test('handles permission name with dots and colons', function (): void {
            // Arrange & Act
            $permission = new Permission('organization.department:users.create');

            // Assert
            expect($permission->name)->toBe('organization.department:users.create');
        });

        test('handles permission name with path-like structure', function (): void {
            // Arrange & Act
            $permission = new Permission('api/v1/users/create');

            // Assert
            expect($permission->name)->toBe('api/v1/users/create');
        });

        test('handles permission name with URL-like structure', function (): void {
            // Arrange & Act
            $permission = new Permission('https://api.example.com/users/create');

            // Assert
            expect($permission->name)->toBe('https://api.example.com/users/create');
        });

        test('handles null domain explicitly', function (): void {
            // Arrange & Act
            $permission = new Permission('users.create');

            // Assert
            expect($permission->name)->toBe('users.create');
            expect($permission->domain)->toBeNull();
        });

        test('handles domain with empty id', function (): void {
            // Arrange
            $domain = new Domain('');

            // Act
            $permission = new Permission('users.create', $domain);

            // Assert
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->id)->toBe('');
        });

        test('handles domain with special characters', function (): void {
            // Arrange
            $domain = new Domain('tenant-123@organization.com');

            // Act
            $permission = new Permission('users.create', $domain);

            // Assert
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->id)->toBe('tenant-123@organization.com');
        });

        test('handles domain with unicode characters', function (): void {
            // Arrange
            $domain = new Domain('租户-123');

            // Act
            $permission = new Permission('users.create', $domain);

            // Assert
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->id)->toBe('租户-123');
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
            $permission = new Permission('users.create', $domain);

            // Assert
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->attributes['metadata']['nested']['deep']['value'])->toBe('test');
        });

        test('handles domain with empty attributes array', function (): void {
            // Arrange
            $domain = new Domain('tenant-1', []);

            // Act
            $permission = new Permission('users.create', $domain);

            // Assert
            expect($permission->domain)->toBe($domain);
            expect($permission->domain->attributes)->toBe([]);
        });
    });

    describe('Immutability', function (): void {
        test('is readonly class', function (): void {
            // Arrange & Act & Assert
            expect(Permission::class)->toBeReadonly();
        });

        test('properties are readonly', function (): void {
            // Arrange
            $permission = new Permission('users.create');

            // Act & Assert - This would fail at compile time in strict mode
            // We verify the class is declared as readonly which makes all properties readonly
            $reflection = new ReflectionClass(Permission::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        test('domain property is readonly', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');
            $permission = new Permission('users.create', $domain);

            // Act & Assert - Verify domain is accessible but immutable
            expect($permission->domain)->toBe($domain);

            $reflection = new ReflectionClass(Permission::class);
            expect($reflection->isReadOnly())->toBeTrue();
        });

        test('creates new instance instead of modifying existing', function (): void {
            // Arrange
            $originalDomain = new Domain('tenant-1');
            $permission1 = new Permission('users.create', $originalDomain);

            // Act - Create a new permission instead of modifying
            $newDomain = new Domain('tenant-2');
            $permission2 = new Permission('users.create', $newDomain);

            // Assert - Original permission unchanged
            expect($permission1->domain)->toBe($originalDomain);
            expect($permission1->domain->id)->toBe('tenant-1');
            expect($permission2->domain)->toBe($newDomain);
            expect($permission2->domain->id)->toBe('tenant-2');
        });
    });

    describe('Type Safety', function (): void {
        test('enforces strict string type for name', function (): void {
            // Arrange & Act - PHP will handle type coercion
            $permission = new Permission('123');

            // Assert
            expect($permission->name)->toBeString();
            expect($permission->name)->toBe('123');
        });

        test('enforces Domain type or null for domain parameter', function (): void {
            // Arrange
            $domain = new Domain('tenant-1');

            // Act
            $permissionWithDomain = new Permission('users.create', $domain);
            $permissionWithoutDomain = new Permission('users.create');

            // Assert
            expect($permissionWithDomain->domain)->toBeInstanceOf(Domain::class);
            expect($permissionWithoutDomain->domain)->toBeNull();
        });

        test('maintains type consistency across multiple instantiations', function (): void {
            // Arrange & Act
            $permissions = [
                new Permission('users.create'),
                new Permission('users.read', new Domain('tenant-1')),
                new Permission('users.update'),
                new Permission('users.delete', new Domain('tenant-2')),
            ];

            // Assert
            foreach ($permissions as $permission) {
                expect($permission)->toBeInstanceOf(Permission::class);
                expect($permission->name)->toBeString();

                // Domain must be either null or instance of Domain
                if ($permission->domain instanceof Domain) {
                    expect($permission->domain)->toBeInstanceOf(Domain::class);
                }
            }
        });
    });

    describe('Multi-tenant Scenarios', function (): void {
        test('differentiates global permission from tenant-scoped permission', function (): void {
            // Arrange
            $globalPermission = new Permission('users.create');
            $tenantPermission = new Permission('users.create', new Domain('tenant-1'));

            // Assert
            expect($globalPermission->name)->toBe($tenantPermission->name);
            expect($globalPermission->domain)->toBeNull();
            expect($tenantPermission->domain)->not->toBeNull();
            expect($tenantPermission->domain->id)->toBe('tenant-1');
        });

        test('supports user with different permissions in different tenants', function (): void {
            // Arrange & Act
            $createInTenant1 = new Permission('users.create', new Domain('tenant-1'));
            $readInTenant2 = new Permission('users.read', new Domain('tenant-2'));
            $updateInTenant3 = new Permission('users.update', new Domain('tenant-3'));

            // Assert
            expect($createInTenant1->domain->id)->toBe('tenant-1');
            expect($readInTenant2->domain->id)->toBe('tenant-2');
            expect($updateInTenant3->domain->id)->toBe('tenant-3');
            expect($createInTenant1->name)->not->toBe($readInTenant2->name);
        });

        test('supports same permission name across multiple tenants', function (): void {
            // Arrange
            $domains = [
                new Domain('tenant-1'),
                new Domain('tenant-2'),
                new Domain('tenant-3'),
            ];

            // Act
            $permissions = array_map(
                fn (Domain $domain): Permission => new Permission('users.create', $domain),
                $domains,
            );

            // Assert
            expect($permissions)->toHaveCount(3);

            foreach ($permissions as $index => $permission) {
                expect($permission->name)->toBe('users.create');
                expect($permission->domain->id)->toBe('tenant-'.($index + 1));
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
            $orgPermission = new Permission('admin.manage', $organizationDomain);
            $deptPermission = new Permission('documents.read', $departmentDomain);

            // Assert
            expect($orgPermission->domain->attributes['parent'])->toBeNull();
            expect($deptPermission->domain->attributes['parent'])->toBe('org-1');
        });

        test('supports security zone domains', function (): void {
            // Arrange
            $publicZone = new Domain('public', ['security_level' => 0]);
            $internalZone = new Domain('internal', ['security_level' => 1]);
            $restrictedZone = new Domain('restricted', ['security_level' => 2]);

            // Act
            $publicPermission = new Permission('documents.read', $publicZone);
            $internalPermission = new Permission('documents.update', $internalZone);
            $restrictedPermission = new Permission('documents.delete', $restrictedZone);

            // Assert
            expect($publicPermission->domain->attributes['security_level'])->toBe(0);
            expect($internalPermission->domain->attributes['security_level'])->toBe(1);
            expect($restrictedPermission->domain->attributes['security_level'])->toBe(2);
        });
    });

    describe('Usage Patterns', function (): void {
        test('supports creating permissions from configuration', function (): void {
            // Arrange
            $config = [
                ['name' => 'users.create', 'domain' => null],
                ['name' => 'users.read', 'domain' => 'tenant-1'],
                ['name' => 'users.update', 'domain' => 'tenant-2'],
            ];

            // Act
            $permissions = array_map(
                fn (array $cfg): Permission => new Permission(
                    $cfg['name'],
                    $cfg['domain'] ? new Domain($cfg['domain']) : null,
                ),
                $config,
            );

            // Assert
            expect($permissions)->toHaveCount(3);
            expect($permissions[0]->name)->toBe('users.create');
            expect($permissions[0]->domain)->toBeNull();
            expect($permissions[1]->domain->id)->toBe('tenant-1');
            expect($permissions[2]->domain->id)->toBe('tenant-2');
        });

        test('supports collection of permissions for a user', function (): void {
            // Arrange & Act
            $userPermissions = [
                new Permission('users.create', new Domain('tenant-1')),
                new Permission('users.read', new Domain('tenant-2')),
                new Permission('users.update'),
            ];

            // Assert
            expect($userPermissions)->toHaveCount(3);
            expect($userPermissions[0]->name)->toBe('users.create');
            expect($userPermissions[1]->name)->toBe('users.read');
            expect($userPermissions[2]->name)->toBe('users.update');
        });

        test('supports filtering permissions by domain', function (): void {
            // Arrange
            $permissions = [
                new Permission('users.create', new Domain('tenant-1')),
                new Permission('users.read', new Domain('tenant-2')),
                new Permission('users.update', new Domain('tenant-1')),
                new Permission('users.delete'),
            ];

            // Act
            $tenant1Permissions = array_filter(
                $permissions,
                fn (Permission $permission): bool => $permission->domain?->id === 'tenant-1',
            );

            // Assert
            expect($tenant1Permissions)->toHaveCount(2);
        });

        test('supports global permissions mixed with tenant-specific permissions', function (): void {
            // Arrange & Act
            $permissions = [
                new Permission('system.config.read'),      // Global permission
                new Permission('system.config.update'),     // Global permission
                new Permission('users.create', new Domain('tenant-1')),
                new Permission('users.create', new Domain('tenant-2')),
            ];

            $globalPermissions = array_filter($permissions, fn (Permission $permission): bool => !$permission->domain instanceof Domain);
            $tenantPermissions = array_filter($permissions, fn (Permission $permission): bool => $permission->domain instanceof Domain);

            // Assert
            expect($globalPermissions)->toHaveCount(2);
            expect($tenantPermissions)->toHaveCount(2);
        });
    });
});
