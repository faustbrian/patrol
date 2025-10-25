<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Facades\Patrol;
use Patrol\Laravel\Patrol as PatrolService;

beforeEach(function (): void {
    // Since Patrol facade returns self::class, we need to bind it to the Patrol service
    // The Patrol class uses static methods, so we bind the facade class to the Patrol class
    $this->app->bind(Patrol::class, fn (): PatrolService => new PatrolService());
});

afterEach(function (): void {
    // Reset resolvers after each test
    PatrolService::reset();
});

describe('Patrol Facade', function (): void {
    describe('Happy Paths', function (): void {
        test('facade resolves to Patrol service', function (): void {
            // Test that facade methods work correctly by testing a simple operation
            Patrol::resolveSubject(fn (): ?object => null);
            expect(Patrol::currentSubject())->toBeNull();
        });

        test('resolveSubject registers subject resolver', function (): void {
            $user = (object) ['id' => '123', 'name' => 'Alice'];

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('123');
        });

        test('currentSubject returns null when no resolver registered', function (): void {
            $subject = Patrol::currentSubject();

            expect($subject)->toBeNull();
        });

        test('currentSubject converts object to Subject', function (): void {
            $user = (object) ['id' => 'user-123', 'name' => 'Alice', 'email' => 'alice@example.com'];

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('user-123');
            expect($subject->attributes)->toHaveKey('name');
            expect($subject->attributes['name'])->toBe('Alice');
        });

        test('currentSubject returns Subject directly if resolver returns Subject', function (): void {
            $expectedSubject = new Subject('user-123', ['name' => 'Alice']);

            Patrol::resolveSubject(fn (): Subject => $expectedSubject);

            $subject = Patrol::currentSubject();

            expect($subject)->toBe($expectedSubject);
        });

        test('currentSubject converts array to Subject', function (): void {
            $userData = ['id' => 'user-123', 'name' => 'Alice'];

            Patrol::resolveSubject(fn (): array => $userData);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('user-123');
            expect($subject->attributes['name'])->toBe('Alice');
        });

        test('resolveTenant registers tenant resolver', function (): void {
            $tenant = (object) ['id' => 'tenant-1', 'name' => 'Acme Corp'];

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('tenant-1');
        });

        test('currentTenant returns null when no resolver registered', function (): void {
            $tenant = Patrol::currentTenant();

            expect($tenant)->toBeNull();
        });

        test('currentTenant converts object to Domain', function (): void {
            $tenant = (object) ['id' => 'tenant-123', 'name' => 'Acme Corp', 'plan' => 'enterprise'];

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('tenant-123');
            expect($domain->attributes)->toHaveKey('name');
            expect($domain->attributes['name'])->toBe('Acme Corp');
        });

        test('currentTenant returns Domain directly if resolver returns Domain', function (): void {
            $expectedDomain = new Domain('tenant-123', ['name' => 'Acme Corp']);

            Patrol::resolveTenant(fn (): Domain => $expectedDomain);

            $domain = Patrol::currentTenant();

            expect($domain)->toBe($expectedDomain);
        });

        test('currentTenant converts array to Domain', function (): void {
            $tenantData = ['id' => 'tenant-123', 'name' => 'Acme Corp'];

            Patrol::resolveTenant(fn (): array => $tenantData);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('tenant-123');
            expect($domain->attributes['name'])->toBe('Acme Corp');
        });

        test('resolveResource registers resource resolver', function (): void {
            $post = (object) ['id' => '456', 'title' => 'Test Post'];

            Patrol::resolveResource(fn ($id): object => $post);

            $resource = Patrol::resolveResourceById('456');

            expect($resource)->toBeInstanceOf(Resource::class);
            expect($resource->id)->toBe('456');
        });

        test('resolveResourceById returns null when no resolver registered', function (): void {
            $resource = Patrol::resolveResourceById('123');

            expect($resource)->toBeNull();
        });

        test('resolveResourceById converts object to Resource', function (): void {
            $post = (object) ['id' => 'post-123', 'title' => 'Test Post', 'published' => true];

            Patrol::resolveResource(fn ($id): object => $post);

            $resource = Patrol::resolveResourceById('post-123');

            expect($resource)->toBeInstanceOf(Resource::class);
            expect($resource->id)->toBe('post-123');
            expect($resource->attributes)->toHaveKey('title');
            expect($resource->attributes['title'])->toBe('Test Post');
        });

        test('resolveResourceById returns Resource directly if resolver returns Resource', function (): void {
            $expectedResource = new Resource('post-123', 'Post', ['title' => 'Test Post']);

            Patrol::resolveResource(fn ($id): Resource => $expectedResource);

            $resource = Patrol::resolveResourceById('post-123');

            expect($resource)->toBe($expectedResource);
        });

        test('resolveResourceById converts array to Resource', function (): void {
            $postData = ['id' => 'post-123', 'type' => 'Post', 'title' => 'Test Post'];

            Patrol::resolveResource(fn ($id): array => $postData);

            $resource = Patrol::resolveResourceById('post-123');

            expect($resource)->toBeInstanceOf(Resource::class);
            expect($resource->id)->toBe('post-123');
            expect($resource->type)->toBe('Post');
            expect($resource->attributes['title'])->toBe('Test Post');
        });

        test('reset clears all resolvers', function (): void {
            $user = (object) ['id' => '123'];
            $tenant = (object) ['id' => 'tenant-1'];
            $post = (object) ['id' => '456'];

            Patrol::resolveSubject(fn (): object => $user);
            Patrol::resolveTenant(fn (): object => $tenant);
            Patrol::resolveResource(fn ($id): object => $post);

            Patrol::reset();

            expect(Patrol::currentSubject())->toBeNull();
            expect(Patrol::currentTenant())->toBeNull();
            expect(Patrol::resolveResourceById('456'))->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('currentSubject returns null when resolver returns null', function (): void {
            Patrol::resolveSubject(fn (): ?object => null);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeNull();
        });

        test('currentTenant returns null when resolver returns null', function (): void {
            Patrol::resolveTenant(fn (): ?object => null);

            $tenant = Patrol::currentTenant();

            expect($tenant)->toBeNull();
        });

        test('resolveResourceById returns null when resolver returns null', function (): void {
            Patrol::resolveResource(fn ($id): ?object => null);

            $resource = Patrol::resolveResourceById('non-existent');

            expect($resource)->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('currentSubject uses default id for object without id property', function (): void {
            $user = (object) ['name' => 'Alice'];

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('anonymous');
        });

        test('currentSubject uses default id for array without id key', function (): void {
            $userData = ['name' => 'Alice'];

            Patrol::resolveSubject(fn (): array => $userData);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('anonymous');
        });

        test('currentTenant uses default id for object without id property', function (): void {
            $tenant = (object) ['name' => 'Acme Corp'];

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('default');
        });

        test('currentTenant uses default id for array without id key', function (): void {
            $tenantData = ['name' => 'Acme Corp'];

            Patrol::resolveTenant(fn (): array => $tenantData);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('default');
        });

        test('resolveResourceById uses identifier as id when object has no id property', function (): void {
            $resource = (object) ['title' => 'Test'];

            Patrol::resolveResource(fn ($id): object => $resource);

            $result = Patrol::resolveResourceById('custom-id');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('custom-id');
        });

        test('resolveResourceById uses unknown type when array has no type key', function (): void {
            $resourceData = ['id' => 'res-123', 'title' => 'Test'];

            Patrol::resolveResource(fn ($id): array => $resourceData);

            $result = Patrol::resolveResourceById('res-123');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->type)->toBe('unknown');
        });

        test('currentSubject extracts attributes from object with toArray method', function (): void {
            $user = new class()
            {
                public int $id = 123;

                public string $name = 'Alice';

                public function toArray(): array
                {
                    return ['id' => $this->id, 'name' => $this->name, 'custom' => 'value'];
                }
            };

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->attributes)->toHaveKey('custom');
            expect($subject->attributes['custom'])->toBe('value');
        });

        test('currentTenant extracts attributes from object with toArray method', function (): void {
            $tenant = new class()
            {
                public int $id = 456;

                public string $name = 'Acme Corp';

                public function toArray(): array
                {
                    return ['id' => $this->id, 'name' => $this->name, 'plan' => 'enterprise'];
                }
            };

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->attributes)->toHaveKey('plan');
            expect($domain->attributes['plan'])->toBe('enterprise');
        });

        test('resolveResourceById extracts type from class name', function (): void {
            $post = new class()
            {
                public string $id = 'post-123';

                public string $title = 'Test';
            };

            Patrol::resolveResource(fn ($id): object => $post);

            $result = Patrol::resolveResourceById('post-123');

            expect($result)->toBeInstanceOf(Resource::class);
            // Type should be extracted from class basename
            expect($result->type)->toBeString();
            expect($result->type)->not->toBe('unknown');
        });

        test('currentSubject handles integer IDs', function (): void {
            $user = (object) ['id' => 123, 'name' => 'Alice'];

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('123');
        });

        test('currentTenant handles integer IDs', function (): void {
            $tenant = (object) ['id' => 456, 'name' => 'Acme Corp'];

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('456');
        });

        test('resolveResourceById handles integer IDs in object', function (): void {
            $post = (object) ['id' => 789, 'title' => 'Test'];

            Patrol::resolveResource(fn ($id): object => $post);

            $result = Patrol::resolveResourceById('789');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('789');
        });

        test('multiple resolver updates replace previous resolvers', function (): void {
            $user1 = (object) ['id' => 'user-1'];
            $user2 = (object) ['id' => 'user-2'];

            Patrol::resolveSubject(fn (): object => $user1);
            Patrol::resolveSubject(fn (): object => $user2);

            $subject = Patrol::currentSubject();

            expect($subject->id)->toBe('user-2');
        });

        test('resolvers work independently', function (): void {
            $user = (object) ['id' => 'user-123'];
            $tenant = (object) ['id' => 'tenant-456'];

            Patrol::resolveSubject(fn (): object => $user);
            Patrol::resolveTenant(fn (): object => $tenant);

            expect(Patrol::currentSubject()->id)->toBe('user-123');
            expect(Patrol::currentTenant()->id)->toBe('tenant-456');
            expect(Patrol::resolveResourceById('res-1'))->toBeNull();
        });

        test('resolveResourceById passes identifier to resolver', function (): void {
            $resolvedId = null;

            Patrol::resolveResource(function ($id) use (&$resolvedId): object {
                $resolvedId = $id;

                return (object) ['id' => $id, 'title' => 'Test'];
            });

            Patrol::resolveResourceById('custom-identifier');

            expect($resolvedId)->toBe('custom-identifier');
        });

        test('currentSubject extracts attributes from array', function (): void {
            $userData = [
                'id' => 'user-123',
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'roles' => ['admin', 'editor'],
            ];

            Patrol::resolveSubject(fn (): array => $userData);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->attributes)->toBe($userData);
            expect($subject->attributes['roles'])->toBe(['admin', 'editor']);
        });

        test('currentTenant extracts attributes from array', function (): void {
            $tenantData = [
                'id' => 'tenant-123',
                'name' => 'Acme Corp',
                'plan' => 'enterprise',
                'features' => ['api', 'webhooks'],
            ];

            Patrol::resolveTenant(fn (): array => $tenantData);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->attributes)->toBe($tenantData);
            expect($domain->attributes['features'])->toBe(['api', 'webhooks']);
        });

        test('resolveResourceById extracts all attributes', function (): void {
            $resourceData = [
                'id' => 'res-123',
                'type' => 'Document',
                'title' => 'Test Document',
                'owner_id' => 'user-456',
                'tags' => ['important', 'draft'],
            ];

            Patrol::resolveResource(fn ($id): array => $resourceData);

            $result = Patrol::resolveResourceById('res-123');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->attributes)->toBe($resourceData);
            expect($result->attributes['tags'])->toBe(['important', 'draft']);
        });

        test('resolveResourceById with integer identifier uses unknown as id', function (): void {
            $resource = (object) ['title' => 'Test'];

            Patrol::resolveResource(fn ($id): object => $resource);

            $result = Patrol::resolveResourceById(123);

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('unknown');
        });

        test('currentSubject extracts attributes from object without toArray using get_object_vars', function (): void {
            $user = new class()
            {
                public string $id = 'user-123';

                public string $name = 'Bob';

                public string $email = 'bob@example.com';
            };

            Patrol::resolveSubject(fn (): object => $user);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->attributes)->toHaveKey('name');
            expect($subject->attributes['name'])->toBe('Bob');
            expect($subject->attributes)->toHaveKey('email');
        });

        test('currentTenant extracts attributes from object without toArray using get_object_vars', function (): void {
            $tenant = new class()
            {
                public string $id = 'tenant-123';

                public string $name = 'Corp Inc';
            };

            Patrol::resolveTenant(fn (): object => $tenant);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->attributes)->toHaveKey('name');
            expect($domain->attributes['name'])->toBe('Corp Inc');
        });

        test('resolveResourceById extracts attributes from object without toArray using get_object_vars', function (): void {
            $resource = new class()
            {
                public string $id = 'res-123';

                public string $title = 'Document';
            };

            Patrol::resolveResource(fn ($id): object => $resource);

            $result = Patrol::resolveResourceById('res-123');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->attributes)->toHaveKey('title');
            expect($result->attributes['title'])->toBe('Document');
        });

        test('resolveResourceById with array without id uses identifier', function (): void {
            $resourceData = ['title' => 'Test Document'];

            Patrol::resolveResource(fn ($id): array => $resourceData);

            $result = Patrol::resolveResourceById('custom-123');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('custom-123');
        });

        test('currentSubject extracts attributes from array with id', function (): void {
            $userData = ['id' => 'user-456', 'name' => 'Charlie'];

            Patrol::resolveSubject(fn (): array => $userData);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('user-456');
            expect($subject->attributes)->toBe($userData);
        });

        test('currentTenant extracts attributes from array with id', function (): void {
            $tenantData = ['id' => 'tenant-789', 'name' => 'Enterprise Corp'];

            Patrol::resolveTenant(fn (): array => $tenantData);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('tenant-789');
            expect($domain->attributes)->toBe($tenantData);
        });

        test('resolveResourceById with integer identifier in array', function (): void {
            $resourceData = ['id' => 999, 'title' => 'Resource'];

            Patrol::resolveResource(fn ($id): array => $resourceData);

            $result = Patrol::resolveResourceById('999');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('999');
        });

        test('extractType returns class basename for named objects', function (): void {
            $resource = new class()
            {
                public string $id = 'res-456';

                public string $name = 'TestResource';
            };

            Patrol::resolveResource(fn ($id): object => $resource);

            $result = Patrol::resolveResourceById('res-456');

            expect($result)->toBeInstanceOf(Resource::class);
            // Type should be the class basename (anonymous class will have a name)
            expect($result->type)->toBeString();
            expect($result->type)->not->toBe('unknown');
        });

        test('extractType returns type from array when present', function (): void {
            $resourceData = ['id' => 'res-789', 'type' => 'CustomType', 'name' => 'Test'];

            Patrol::resolveResource(fn ($id): array => $resourceData);

            $result = Patrol::resolveResourceById('res-789');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->type)->toBe('CustomType');
        });

        test('resolveResourceById with object having no id property uses identifier', function (): void {
            $resource = (object) ['name' => 'NoId', 'title' => 'Test'];

            Patrol::resolveResource(fn ($id): object => $resource);

            $result = Patrol::resolveResourceById('my-custom-id');

            expect($result)->toBeInstanceOf(Resource::class);
            expect($result->id)->toBe('my-custom-id');
        });

        test('currentSubject with integer id in array converts to string', function (): void {
            $userData = ['id' => 789, 'name' => 'IntId User'];

            Patrol::resolveSubject(fn (): array => $userData);

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('789');
        });

        test('currentTenant with integer id in array converts to string', function (): void {
            $tenantData = ['id' => 456, 'name' => 'IntId Tenant'];

            Patrol::resolveTenant(fn (): array => $tenantData);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('456');
        });

        test('extractAttributes returns empty array for scalar values', function (): void {
            // This tests the defensive return [] at line 303
            // While resolvers shouldn't return scalars, this ensures robustness
            Patrol::resolveSubject(fn (): string => 'scalar-value');

            $subject = Patrol::currentSubject();

            expect($subject)->toBeInstanceOf(Subject::class);
            expect($subject->id)->toBe('anonymous');
            expect($subject->attributes)->toBe([]);
        });

        test('extractAttributes returns empty array for numeric scalar in tenant resolver', function (): void {
            // Additional coverage for line 303 via tenant path
            Patrol::resolveTenant(fn (): int => 42);

            $domain = Patrol::currentTenant();

            expect($domain)->toBeInstanceOf(Domain::class);
            expect($domain->id)->toBe('default');
            expect($domain->attributes)->toBe([]);
        });

        test('extractAttributes returns empty array for boolean scalar in resource resolver', function (): void {
            // Additional coverage for line 303 via resource path
            Patrol::resolveResource(fn ($id): bool => true);

            $resource = Patrol::resolveResourceById('test-id');

            expect($resource)->toBeInstanceOf(Resource::class);
            expect($resource->id)->toBe('test-id');
            expect($resource->type)->toBe('unknown');
            expect($resource->attributes)->toBe([]);
        });
    });
});
