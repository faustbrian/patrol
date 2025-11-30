# Configuration Guide

Complete guide to configuring Patrol for your Laravel application.

## Overview

Patrol's configuration controls how authorization decisions are made, how subjects and resources are resolved, and how the package integrates with your Laravel application.

**Configuration file location:** `config/patrol.php`

**Key areas:**
- Matcher selection (ACL, RBAC, ABAC, RESTful)
- Subject resolution (who is making the request)
- Tenant resolution (multi-tenancy support)
- Resource resolution (converting IDs to resources)
- Policy repository (where policies are stored)

---

## Publishing Configuration

Publish the configuration file to your application:

```bash
php artisan vendor:publish --tag=patrol-config
```

This creates `config/patrol.php` in your application.

---

## Configuration File Reference

### Default Configuration

```php
// config/patrol.php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Rule Matcher
    |--------------------------------------------------------------------------
    |
    | The default rule matcher to use for policy evaluation.
    | Options: 'acl', 'rbac', 'abac', 'restful', 'composite'
    |
    */
    'default_matcher' => env('PATROL_MATCHER', 'acl'),

    /*
    |--------------------------------------------------------------------------
    | Subject Resolver
    |--------------------------------------------------------------------------
    |
    | The class responsible for resolving the current subject (user).
    | Must implement: Patrol\Core\Contracts\SubjectResolverInterface
    |
    */
    'subject_resolver' => \Patrol\Laravel\Resolvers\LaravelSubjectResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | The class responsible for resolving the current tenant (for multi-tenancy).
    | Must implement: Patrol\Core\Contracts\TenantResolverInterface
    | Set to null if not using multi-tenancy.
    |
    */
    'tenant_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Resource Resolver
    |--------------------------------------------------------------------------
    |
    | The class responsible for converting resource IDs to Resource objects.
    | Must implement: Patrol\Core\Contracts\ResourceResolverInterface
    | Set to null to use default behavior.
    |
    */
    'resource_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Policy Repository
    |--------------------------------------------------------------------------
    |
    | The class responsible for loading and saving policies.
    | Must implement: Patrol\Core\Contracts\PolicyRepositoryInterface
    |
    */
    'repository' => \Patrol\Laravel\Repositories\DatabasePolicyRepository::class,

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching for policy evaluation to improve performance.
    |
    */
    'cache' => [
        'enabled' => env('PATROL_CACHE_ENABLED', true),
        'ttl' => env('PATROL_CACHE_TTL', 3600), // seconds
        'key_prefix' => 'patrol:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the Patrol middleware behaves.
    |
    */
    'middleware' => [
        'key' => 'patrol',
        'throw_unauthorized' => true,
    ],
];
```

---

## Matcher Configuration

### Available Matchers

**`acl`** - Access Control List
- Direct subject-resource-action permissions
- Best for small apps with explicit permissions

**`rbac`** - Role-Based Access Control
- Role-based permissions
- Best for enterprise apps with job functions

**`abac`** - Attribute-Based Access Control
- Dynamic attribute/expression evaluation
- Best for ownership-based or complex conditions

**`restful`** - RESTful Authorization
- HTTP method/path matching
- Best for APIs

**`composite`** - Composite Matcher
- Combines multiple matchers
- Best for apps using multiple authorization models

### Setting the Default Matcher

**Via Configuration:**
```php
'default_matcher' => 'rbac',
```

**Via Environment:**
```bash
# .env
PATROL_MATCHER=rbac
```

### Using Composite Matcher

Combine multiple matchers for complex authorization:

```php
'default_matcher' => 'composite',

'composite_matchers' => [
    \Patrol\Core\Engine\AclRuleMatcher::class,
    \Patrol\Core\Engine\RbacRuleMatcher::class,
    \Patrol\Core\Engine\AbacRuleMatcher::class,
],
```

**Use case:** When you need RBAC for roles + ABAC for ownership checks in the same app.

---

## Subject Resolver

The Subject Resolver determines **who** is making the authorization request.

### Default Subject Resolver

Patrol includes `LaravelSubjectResolver` which uses Laravel's `auth()` helper:

```php
'subject_resolver' => \Patrol\Laravel\Resolvers\LaravelSubjectResolver::class,
```

**What it does:**
- Gets current authenticated user via `auth()->user()`
- Creates Subject with user ID and basic attributes

### Custom Subject Resolver

Create a custom resolver when you need:
- Custom user attributes for ABAC
- Roles loaded from database for RBAC
- Multi-tenant domain resolution
- API token-based authentication

#### Step 1: Create Resolver Class

```php
<?php

namespace App\Resolvers;

use Patrol\Core\Contracts\SubjectResolverInterface;
use Patrol\Core\ValueObjects\Subject;

class CustomSubjectResolver implements SubjectResolverInterface
{
    public function resolve(): Subject
    {
        $user = auth()->user();

        // Guest users
        if (!$user) {
            return new Subject('guest', [
                'roles' => ['guest'],
            ]);
        }

        // Authenticated users with full attributes
        return new Subject(
            id: (string) $user->id,
            attributes: [
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'department' => $user->department_id,
                'level' => $user->clearance_level,
                'verified' => $user->email_verified_at !== null,
                'created_at' => $user->created_at,
            ]
        );
    }
}
```

#### Step 2: Register in Configuration

```php
// config/patrol.php
'subject_resolver' => \App\Resolvers\CustomSubjectResolver::class,
```

### Common Subject Patterns

#### RBAC Subject (with roles)

```php
public function resolve(): Subject
{
    $user = auth()->user();

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(), // ['editor', 'admin']
    ]);
}
```

#### ABAC Subject (with attributes)

```php
public function resolve(): Subject
{
    $user = auth()->user();

    return new Subject($user->id, [
        'department' => $user->department->name,
        'level' => $user->level,
        'manager_id' => $user->manager_id,
        'verified' => $user->verified,
    ]);
}
```

#### Multi-Tenant Subject (with domain)

```php
public function resolve(): Subject
{
    $user = auth()->user();

    return new Subject($user->id, [
        'domain' => $user->current_tenant_id,
        'domain_roles' => [
            'tenant-1' => ['admin'],
            'tenant-2' => ['viewer'],
        ],
    ]);
}
```

#### API Token Subject

```php
public function resolve(): Subject
{
    $token = request()->bearerToken();

    if (!$token) {
        return new Subject('anonymous', ['tier' => 'free']);
    }

    $apiKey = ApiKey::where('token', $token)->first();

    return new Subject($apiKey->id, [
        'tier' => $apiKey->tier, // 'free', 'pro', 'enterprise'
        'rate_limit' => $apiKey->rate_limit,
    ]);
}
```

### Optimizing Subject Resolution

#### Caching Subject

```php
public function resolve(): Subject
{
    $userId = auth()->id();

    return Cache::remember("subject:{$userId}", 300, function () use ($userId) {
        $user = User::with('roles')->find($userId);

        return new Subject($user->id, [
            'roles' => $user->roles->pluck('name')->all(),
        ]);
    });
}
```

#### Eager Loading

```php
public function resolve(): Subject
{
    // Ensure roles are eager loaded
    $user = auth()->user()->loadMissing('roles');

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
    ]);
}
```

---

## Tenant Resolver

The Tenant Resolver determines the current tenant/domain for multi-tenant applications.

### When to Use Tenant Resolver

Use tenant resolver when:
- You have a multi-tenant SaaS application
- Users have different roles per organization/workspace
- You need tenant-based data isolation
- Using RBAC with Domains model

### Creating a Tenant Resolver

#### Step 1: Create Resolver Class

```php
<?php

namespace App\Resolvers;

use Patrol\Core\Contracts\TenantResolverInterface;

class TenantResolver implements TenantResolverInterface
{
    public function resolve(): string|int|null
    {
        // Option 1: From authenticated user
        return auth()->user()?->current_tenant_id;

        // Option 2: From session
        // return session('current_tenant_id');

        // Option 3: From subdomain
        // $host = request()->getHost();
        // $subdomain = explode('.', $host)[0];
        // return Tenant::where('subdomain', $subdomain)->value('id');

        // Option 4: From request header (API)
        // return request()->header('X-Tenant-ID');
    }
}
```

#### Step 2: Register in Configuration

```php
// config/patrol.php
'tenant_resolver' => \App\Resolvers\TenantResolver::class,
```

### Multi-Tenant Patterns

#### User-Tenant Relationship

```php
public function resolve(): ?string
{
    $user = auth()->user();

    if (!$user) {
        return null;
    }

    // User's currently selected tenant
    return $user->current_tenant_id;
}
```

#### Subdomain-Based Tenancy

```php
public function resolve(): ?string
{
    $host = request()->getHost();

    // Extract subdomain from: tenant1.myapp.com
    $parts = explode('.', $host);

    if (count($parts) < 3) {
        return null; // No subdomain
    }

    $subdomain = $parts[0];

    return Tenant::where('subdomain', $subdomain)->value('id');
}
```

#### Path-Based Tenancy

```php
public function resolve(): ?string
{
    // Extract from URL: /tenants/123/dashboard
    $segments = request()->segments();

    if ($segments[0] === 'tenants' && isset($segments[1])) {
        return $segments[1];
    }

    return null;
}
```

---

## Resource Resolver

The Resource Resolver converts resource identifiers to Resource value objects.

### When to Use Resource Resolver

Use resource resolver when:
- You need to load resource attributes for ABAC
- Resources need to be loaded from database
- You want automatic attribute population

### Creating a Resource Resolver

#### Step 1: Create Resolver Class

```php
<?php

namespace App\Resolvers;

use Patrol\Core\Contracts\ResourceResolverInterface;
use Patrol\Core\ValueObjects\Resource;
use App\Models\{Document, Project, User};

class ResourceResolver implements ResourceResolverInterface
{
    public function resolve(string|int $id, string $type): ?Resource
    {
        $model = match ($type) {
            'document' => Document::find($id),
            'project' => Project::find($id),
            'user' => User::find($id),
            default => null,
        };

        if (!$model) {
            return null;
        }

        return new Resource(
            id: (string) $model->id,
            type: $type,
            attributes: $this->extractAttributes($model, $type)
        );
    }

    private function extractAttributes($model, string $type): array
    {
        return match ($type) {
            'document' => [
                'owner_id' => $model->user_id,
                'status' => $model->status,
                'department_id' => $model->department_id,
                'is_published' => $model->published_at !== null,
            ],
            'project' => [
                'owner_id' => $model->owner_id,
                'team_id' => $model->team_id,
                'is_locked' => $model->locked_at !== null,
            ],
            'user' => [
                'department_id' => $model->department_id,
                'manager_id' => $model->manager_id,
            ],
            default => [],
        };
    }
}
```

#### Step 2: Register in Configuration

```php
// config/patrol.php
'resource_resolver' => \App\Resolvers\ResourceResolver::class,
```

### Resource Resolver Patterns

#### ABAC Resource (with ownership)

```php
public function resolve(string|int $id, string $type): ?Resource
{
    $model = Document::find($id);

    return new Resource($id, $type, [
        'owner_id' => $model->user_id,
        'status' => $model->status,
        'created_at' => $model->created_at,
    ]);
}
```

#### RBAC Resource (with roles)

```php
public function resolve(string|int $id, string $type): ?Resource
{
    $model = Document::find($id);

    return new Resource($id, $type, [
        'roles' => $model->tags->pluck('name')->all(), // ['public', 'featured']
        'classification' => $model->classification, // 'public', 'confidential', 'secret'
    ]);
}
```

#### Nested Attributes

```php
public function resolve(string|int $id, string $type): ?Resource
{
    $model = Document::with('author')->find($id);

    return new Resource($id, $type, [
        'owner_id' => $model->user_id,
        'author' => [
            'id' => $model->author->id,
            'manager_id' => $model->author->manager_id,
            'department_id' => $model->author->department_id,
        ],
    ]);
}
```

---

## Policy Repository Configuration

Configure where policies are loaded from and saved to.

### Available Repositories

**DatabasePolicyRepository** - Store policies in database
```php
'repository' => \Patrol\Laravel\Repositories\DatabasePolicyRepository::class,
```

**JsonPolicyRepository** - Store policies in JSON files
```php
'repository' => \Patrol\Core\Storage\JsonPolicyRepository::class,
```

**YamlPolicyRepository** - Store policies in YAML files
```php
'repository' => \Patrol\Core\Storage\YamlPolicyRepository::class,
```

See [Persisting Policies](./persisting-policies.md) for detailed repository documentation.

---

## Cache Configuration

Enable caching to improve authorization performance.

```php
'cache' => [
    'enabled' => env('PATROL_CACHE_ENABLED', true),
    'ttl' => env('PATROL_CACHE_TTL', 3600), // 1 hour
    'key_prefix' => 'patrol:',
    'driver' => env('PATROL_CACHE_DRIVER'), // defaults to app cache driver
],
```

### Environment Variables

```bash
# .env
PATROL_CACHE_ENABLED=true
PATROL_CACHE_TTL=3600
PATROL_CACHE_DRIVER=redis
```

### Cache Invalidation

Clear Patrol cache when policies change:

```php
use Illuminate\Support\Facades\Cache;

// Clear all Patrol cache
Cache::tags(['patrol'])->flush();

// Clear specific policy
Cache::forget('patrol:policy');

// Clear subject cache
Cache::forget("patrol:subject:{$userId}");
```

### Disabling Cache (Development)

```bash
# .env
PATROL_CACHE_ENABLED=false
```

---

## Middleware Configuration

Configure Patrol's middleware behavior.

```php
'middleware' => [
    'key' => 'patrol',
    'throw_unauthorized' => true,
    'redirect_to' => null, // Redirect instead of throwing 403
],
```

### Customizing Unauthorized Response

**Option 1: Throw 403 Exception (default)**
```php
'throw_unauthorized' => true,
```

**Option 2: Redirect to Login**
```php
'throw_unauthorized' => false,
'redirect_to' => '/login',
```

**Option 3: Custom Handler**
```php
'unauthorized_handler' => \App\Http\Handlers\UnauthorizedHandler::class,
```

---

## Environment-Specific Configuration

### Development Environment

```php
// config/patrol.php
return [
    'default_matcher' => env('PATROL_MATCHER', 'acl'),

    'cache' => [
        'enabled' => env('APP_ENV') !== 'local', // Disable cache in local
        'ttl' => env('PATROL_CACHE_TTL', 3600),
    ],

    'debug' => env('PATROL_DEBUG', false), // Enable verbose logging
];
```

### Production Environment

```bash
# .env
PATROL_MATCHER=rbac
PATROL_CACHE_ENABLED=true
PATROL_CACHE_TTL=7200
PATROL_CACHE_DRIVER=redis
PATROL_DEBUG=false
```

### Testing Environment

```php
// config/patrol.php
return [
    'cache' => [
        'enabled' => env('APP_ENV') !== 'testing', // Disable in tests
    ],
];
```

---

## Complete Configuration Example

### Multi-Tenant SaaS Application

```php
<?php

// config/patrol.php
return [
    'default_matcher' => 'composite',

    'composite_matchers' => [
        \Patrol\Core\Engine\RbacRuleMatcher::class,  // For roles
        \Patrol\Core\Engine\AbacRuleMatcher::class,  // For ownership
    ],

    'subject_resolver' => \App\Resolvers\SaasSubjectResolver::class,
    'tenant_resolver' => \App\Resolvers\TenantResolver::class,
    'resource_resolver' => \App\Resolvers\ResourceResolver::class,

    'repository' => \Patrol\Laravel\Repositories\DatabasePolicyRepository::class,

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'driver' => 'redis',
    ],

    'middleware' => [
        'key' => 'patrol',
        'throw_unauthorized' => true,
    ],
];
```

**Subject Resolver:**
```php
class SaasSubjectResolver implements SubjectResolverInterface
{
    public function resolve(): Subject
    {
        $user = auth()->user();

        if (!$user) {
            return new Subject('guest', ['roles' => ['guest']]);
        }

        return new Subject($user->id, [
            'domain' => $user->current_tenant_id,
            'domain_roles' => $user->tenant_roles, // Roles per tenant
            'subscription_tier' => $user->currentTenant->subscription_tier,
        ]);
    }
}
```

**Tenant Resolver:**
```php
class TenantResolver implements TenantResolverInterface
{
    public function resolve(): ?string
    {
        return auth()->user()?->current_tenant_id;
    }
}
```

---

## Programmatic Configuration

Configure Patrol via code instead of config file:

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

// In AppServiceProvider boot()
Patrol::resolveSubject(function () {
    $user = auth()->user();

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
    ]);
});

Patrol::resolveTenant(fn() => auth()->user()?->current_tenant_id);

Patrol::resolveResource(function ($id, $type) {
    $model = match ($type) {
        'post' => Post::find($id),
        default => null,
    };

    return $model ? new Resource($id, $type, $model->toArray()) : null;
});
```

**Use cases:**
- Dynamic configuration based on runtime conditions
- Testing with custom resolvers
- Per-request resolver overrides

---

## Troubleshooting Configuration

### Issue: "Subject resolver not found"

**Symptom:** Error loading subject resolver class

**Solution:** Ensure class exists and implements `SubjectResolverInterface`
```php
'subject_resolver' => \App\Resolvers\CustomSubjectResolver::class,
```

### Issue: "Policy not loading"

**Symptom:** Authorization always denies

**Solution:** Check repository configuration
```bash
# Verify policy repository is set
php artisan config:cache
php artisan config:clear
```

### Issue: "Slow authorization checks"

**Symptom:** Authorization takes > 100ms

**Solution:** Enable caching
```php
'cache' => [
    'enabled' => true,
    'driver' => 'redis', // Faster than file cache
],
```

### Issue: "Multi-tenancy not working"

**Symptom:** Users see data from other tenants

**Solution:** Verify tenant resolver is configured
```php
'tenant_resolver' => \App\Resolvers\TenantResolver::class,
```

---

## Related Guides

- [API Reference](./api-reference.md) - Core value objects and interfaces
- [Getting Started](./getting-started.md) - Initial setup guide
- [Persisting Policies](./persisting-policies.md) - Policy storage options
- [RBAC with Domains](../models/rbac-domains.md) - Multi-tenant role model
