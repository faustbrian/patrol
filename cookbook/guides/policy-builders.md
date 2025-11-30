# Policy Builders

Fluent API for building authorization policies without manual PolicyRule construction.

## Overview

Policy Builders provide a clean, expressive way to construct policies using a fluent interface. Instead of manually creating PolicyRule objects, you can use domain-specific builders that generate the rules for you.

Patrol provides builders for common patterns:
- **RbacPolicyBuilder** - Role-based policies
- **RestfulPolicyBuilder** - RESTful API policies
- **CrudPolicyBuilder** - CRUD operation policies
- **AclPolicyBuilder** - Access control list policies

## Why Use Builders?

**Without builders (verbose):**
```php
$policy = new Policy([
    new PolicyRule('role:admin', 'posts', 'create', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'read', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'update', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'delete', Effect::Allow),
    new PolicyRule('role:editor', 'posts', 'create', Effect::Allow),
    new PolicyRule('role:editor', 'posts', 'read', Effect::Allow),
    new PolicyRule('role:editor', 'posts', 'update', Effect::Allow),
    new PolicyRule('role:viewer', 'posts', 'read', Effect::Allow),
]);
```

**With builders (clean):**
```php
$policy = RbacPolicyBuilder::make()
    ->role('admin')->fullAccess()->on('posts')
    ->role('editor')->can(['create', 'read', 'update'])->on('posts')
    ->role('viewer')->readOnly()->on('posts')
    ->build();
```

## RbacPolicyBuilder

Build role-based access control policies with a fluent interface.

### Basic Usage

```php
use Patrol\Laravel\Builders\RbacPolicyBuilder;

$policy = RbacPolicyBuilder::make()
    ->role('admin')
        ->fullAccess()
        ->on('*')
    ->build();
```

### Defining Role Permissions

```php
$policy = RbacPolicyBuilder::make()
    // Admin role - full access to everything
    ->role('admin')
        ->fullAccess()
        ->on('*')

    // Editor role - read and write posts and pages
    ->role('editor')
        ->can(['read', 'write', 'update'])
        ->on('posts')
    ->role('editor')
        ->can(['read', 'write', 'update'])
        ->on('pages')

    // Viewer role - read-only access to all resources
    ->role('viewer')
        ->readOnly()
        ->onAny()

    ->build();
```

### Method Reference

#### `role(string $roleName)`
Start defining permissions for a role.

```php
RbacPolicyBuilder::make()
    ->role('moderator')
    // ... permissions
    ->build();
```

#### `can(array|string $actions)`
Grant specific actions.

```php
->role('editor')
    ->can(['read', 'write', 'delete'])
    ->on('articles')
```

#### `fullAccess()`
Grant all actions (wildcard `*`).

```php
->role('admin')
    ->fullAccess()
    ->on('users')
```

#### `readOnly()`
Grant only read/view actions.

```php
->role('guest')
    ->readOnly()
    ->on('posts')
```

#### `on(string $resource)`
Specify the resource type.

```php
->role('author')
    ->can('publish')
    ->on('articles')
```

#### `onAny()`
Apply to all resources (wildcard `*`).

```php
->role('superadmin')
    ->fullAccess()
    ->onAny()
```

#### `withPriority(int|Priority $priority)`
Set rule priority.

```php
->role('banned-user')
    ->deny(['read', 'write'])
    ->onAny()
    ->withPriority(Priority::Critical)
```

#### `inDomain(string $domain)`
Set domain/tenant for multi-tenant scenarios.

```php
->role('admin')
    ->fullAccess()
    ->on('*')
    ->inDomain('tenant-1')
```

### Complex Example: Multi-Tenant CMS

```php
use Patrol\Laravel\Builders\RbacPolicyBuilder;
use Patrol\Core\ValueObjects\Priority;

$policy = RbacPolicyBuilder::make()
    // Global admin - full access everywhere
    ->role('global-admin')
        ->fullAccess()
        ->onAny()
        ->withPriority(Priority::Critical)

    // Tenant admin - full access within their tenant
    ->role('tenant-admin')
        ->fullAccess()
        ->on('posts')
        ->inDomain('tenant-1')
    ->role('tenant-admin')
        ->fullAccess()
        ->on('users')
        ->inDomain('tenant-1')
    ->role('tenant-admin')
        ->fullAccess()
        ->on('settings')
        ->inDomain('tenant-1')

    // Content editor - manage content only
    ->role('editor')
        ->can(['create', 'read', 'update', 'publish'])
        ->on('posts')
        ->inDomain('tenant-1')
    ->role('editor')
        ->can(['create', 'read', 'update'])
        ->on('pages')
        ->inDomain('tenant-1')

    // Author - own content only (use with ABAC)
    ->role('author')
        ->can(['create', 'read'])
        ->on('posts')

    // Viewer - read-only access
    ->role('viewer')
        ->readOnly()
        ->on('posts')

    ->build();

// Save to repository
$repository = new DatabasePolicyRepository();
$repository->save($policy);
```

---

## RestfulPolicyBuilder

Build RESTful API authorization policies with HTTP method mapping.

### Basic Usage

```php
use Patrol\Laravel\Builders\RestfulPolicyBuilder;

$policy = RestfulPolicyBuilder::for('posts')
    ->allowGetFor('*')                      // Anyone can GET
    ->allowPostFor('role:contributor')      // Contributors can POST
    ->allowPutFor('role:editor')            // Editors can PUT
    ->allowDeleteFor('role:admin')          // Admins can DELETE
    ->build();
```

### HTTP Method Mappings

```php
$policy = RestfulPolicyBuilder::for('api/documents')
    // GET - Read access
    ->allowGetFor('role:viewer')
    ->allowGetFor('role:editor')
    ->allowGetFor('role:admin')

    // POST - Create new resources
    ->allowPostFor('role:editor')
    ->allowPostFor('role:admin')

    // PUT/PATCH - Update existing resources
    ->allowPutFor('role:editor')
    ->allowPutFor('role:admin')
    ->allowPatchFor('role:editor')

    // DELETE - Remove resources
    ->allowDeleteFor('role:admin')

    // OPTIONS - CORS preflight
    ->allowOptionsFor('*')

    ->build();
```

### Method Reference

#### `for(string $resourcePath)`
Set the base resource path.

```php
RestfulPolicyBuilder::for('/api/users')
```

#### `allowGetFor(string $subject)`
Allow GET requests (read).

```php
->allowGetFor('role:viewer')
->allowGetFor('*')  // Public read
```

#### `allowPostFor(string $subject)`
Allow POST requests (create).

```php
->allowPostFor('role:contributor')
```

#### `allowPutFor(string $subject)`
Allow PUT requests (update/replace).

```php
->allowPutFor('role:editor')
```

#### `allowPatchFor(string $subject)`
Allow PATCH requests (partial update).

```php
->allowPatchFor('role:editor')
```

#### `allowDeleteFor(string $subject)`
Allow DELETE requests (remove).

```php
->allowDeleteFor('role:admin')
```

#### `allowOptionsFor(string $subject)`
Allow OPTIONS requests (CORS preflight).

```php
->allowOptionsFor('*')
```

#### `denyGetFor(string $subject)`
Explicitly deny GET requests.

```php
->denyGetFor('role:banned')
```

Similar deny methods exist: `denyPostFor()`, `denyPutFor()`, `denyPatchFor()`, `denyDeleteFor()`, `denyOptionsFor()`

### Real-World Example: Public API

```php
use Patrol\Laravel\Builders\RestfulPolicyBuilder;

$policy = RestfulPolicyBuilder::for('/api/v1')
    // Public endpoints - anyone can GET
    ->allowGetFor('*')
    ->allowOptionsFor('*')  // CORS

    // Free tier - read-only
    ->denyPostFor('tier:free')
    ->denyPutFor('tier:free')
    ->denyDeleteFor('tier:free')

    // Pro tier - can create and update
    ->allowPostFor('tier:pro')
    ->allowPutFor('tier:pro')
    ->allowPatchFor('tier:pro')

    // Enterprise tier - full access
    ->allowPostFor('tier:enterprise')
    ->allowPutFor('tier:enterprise')
    ->allowPatchFor('tier:enterprise')
    ->allowDeleteFor('tier:enterprise')

    // Banned users - no access
    ->denyGetFor('status:banned')
    ->denyPostFor('status:banned')
    ->denyPutFor('status:banned')
    ->denyDeleteFor('status:banned')

    ->build();
```

---

## CrudPolicyBuilder

Build CRUD (Create, Read, Update, Delete) policies with semantic method names.

### Basic Usage

```php
use Patrol\Laravel\Builders\CrudPolicyBuilder;

$policy = CrudPolicyBuilder::for('documents')
    ->allowReadFor('*')                     // Public read
    ->allowCreateFor('role:contributor')    // Contributors can create
    ->allowUpdateFor('role:editor')         // Editors can update
    ->allowDeleteFor('role:admin')          // Admins can delete
    ->build();
```

### CRUD Operations

```php
$policy = CrudPolicyBuilder::for('posts')
    // Read (view, list, show)
    ->allowReadFor('*')  // Public

    // Create (store, new)
    ->allowCreateFor('role:author')
    ->allowCreateFor('role:editor')
    ->allowCreateFor('role:admin')

    // Update (edit, modify)
    ->allowUpdateFor('role:editor')
    ->allowUpdateFor('role:admin')

    // Delete (destroy, remove)
    ->allowDeleteFor('role:admin')

    ->build();
```

### Method Reference

#### `for(string $resource)`
Set the resource type.

```php
CrudPolicyBuilder::for('articles')
```

#### `allowReadFor(string $subject)`
Allow read/view operations.

```php
->allowReadFor('role:viewer')
->allowReadFor('*')  // Public
```

#### `allowCreateFor(string $subject)`
Allow create/store operations.

```php
->allowCreateFor('role:author')
```

#### `allowUpdateFor(string $subject)`
Allow update/edit operations.

```php
->allowUpdateFor('role:editor')
```

#### `allowDeleteFor(string $subject)`
Allow delete/destroy operations.

```php
->allowDeleteFor('role:admin')
```

#### Deny Methods
Explicitly deny operations: `denyReadFor()`, `denyCreateFor()`, `denyUpdateFor()`, `denyDeleteFor()`

```php
->denyDeleteFor('role:guest')
```

### Real-World Example: Blog Platform

```php
use Patrol\Laravel\Builders\CrudPolicyBuilder;
use Patrol\Core\ValueObjects\Priority;

$policy = CrudPolicyBuilder::for('articles')
    // Public can read published articles
    ->allowReadFor('*')

    // Authors can create and read their drafts
    ->allowCreateFor('role:author')

    // Editors can do everything except delete
    ->allowReadFor('role:editor')
    ->allowCreateFor('role:editor')
    ->allowUpdateFor('role:editor')

    // Admins can do everything
    ->allowReadFor('role:admin')
    ->allowCreateFor('role:admin')
    ->allowUpdateFor('role:admin')
    ->allowDeleteFor('role:admin')

    // Suspended users - no write access
    ->denyCreateFor('status:suspended')
    ->denyUpdateFor('status:suspended')
    ->denyDeleteFor('status:suspended')

    ->build();
```

---

## AclPolicyBuilder

Build Access Control List policies with direct subject-resource mappings.

### Basic Usage

```php
use Patrol\Laravel\Builders\AclPolicyBuilder;

$policy = AclPolicyBuilder::make()
    ->allow('user-1')->to('read')->on('document-1')
    ->allow('user-1')->to('write')->on('document-1')
    ->allow('user-2')->to('read')->on('document-1')
    ->build();
```

### Method Reference

#### `allow(string $subject)`
Grant permission to a subject.

```php
AclPolicyBuilder::make()
    ->allow('user-123')
    ->to('edit')
    ->on('post-456')
```

#### `deny(string $subject)`
Deny permission to a subject.

```php
->deny('user-789')
    ->to('delete')
    ->on('post-456')
```

#### `to(string|array $actions)`
Specify allowed/denied actions.

```php
->allow('user-1')
    ->to(['read', 'write'])
    ->on('file-1')
```

#### `on(string $resource)`
Specify the resource.

```php
->allow('user-1')
    ->to('access')
    ->on('admin-panel')
```

#### `withPriority(int|Priority $priority)`
Set rule priority.

```php
->deny('banned-user')
    ->to('*')
    ->on('*')
    ->withPriority(Priority::Critical)
```

### Real-World Example: File Sharing

```php
use Patrol\Laravel\Builders\AclPolicyBuilder;
use Patrol\Core\ValueObjects\Priority;

$policy = AclPolicyBuilder::make()
    // Alice owns file-1 - full access
    ->allow('alice@example.com')
        ->to(['read', 'write', 'delete', 'share'])
        ->on('file:1')
        ->withPriority(Priority::High)

    // Bob is shared on file-1 - read only
    ->allow('bob@example.com')
        ->to('read')
        ->on('file:1')

    // Charlie is shared on file-1 - read and write
    ->allow('charlie@example.com')
        ->to(['read', 'write'])
        ->on('file:1')

    // David is explicitly denied (previous access revoked)
    ->deny('david@example.com')
        ->to('*')
        ->on('file:1')
        ->withPriority(Priority::High)

    // Alice owns file-2 - full access
    ->allow('alice@example.com')
        ->to('*')
        ->on('file:2')

    ->build();
```

---

## Combining Builders

You can combine policies from multiple builders.

```php
use Patrol\Core\ValueObjects\Policy;

// Build RBAC policies
$rbacPolicy = RbacPolicyBuilder::make()
    ->role('admin')->fullAccess()->on('*')
    ->role('editor')->can(['read', 'write'])->on('posts')
    ->build();

// Build RESTful API policies
$apiPolicy = RestfulPolicyBuilder::for('/api/v1')
    ->allowGetFor('*')
    ->allowPostFor('tier:pro')
    ->build();

// Build ACL policies for specific users
$aclPolicy = AclPolicyBuilder::make()
    ->allow('super-admin')->to('*')->on('*')
    ->build();

// Combine all policies
$combinedRules = array_merge(
    $rbacPolicy->rules(),
    $apiPolicy->rules(),
    $aclPolicy->rules()
);

$finalPolicy = new Policy($combinedRules);
```

---

## Persisting Built Policies

After building a policy, persist it to storage:

```php
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

$policy = RbacPolicyBuilder::make()
    ->role('admin')->fullAccess()->on('*')
    ->build();

// Save to database
$repository = new DatabasePolicyRepository();
$repository->save($policy);

// Or save to JSON file
$jsonRepo = new JsonPolicyRepository(storage_path('policies'));
$jsonRepo->save($policy);
```

See [Persisting Policies](./persisting-policies.md) for all storage options.

---

## Best Practices

### 1. Use the Right Builder

- **RbacPolicyBuilder** - For role-based systems with job functions
- **RestfulPolicyBuilder** - For REST APIs with HTTP methods
- **CrudPolicyBuilder** - For traditional CRUD applications
- **AclPolicyBuilder** - For direct user-resource permissions

### 2. Keep It Readable

```php
// ✅ GOOD - Clear and organized
$policy = RbacPolicyBuilder::make()
    ->role('admin')
        ->fullAccess()
        ->on('users')
    ->role('editor')
        ->can(['read', 'write'])
        ->on('posts')
    ->build();

// ❌ BAD - Hard to read
$policy = RbacPolicyBuilder::make()->role('admin')->fullAccess()->on('users')->role('editor')->can(['read', 'write'])->on('posts')->build();
```

### 3. Group Related Permissions

```php
// ✅ GOOD - Grouped by role
->role('editor')
    ->can(['read', 'write', 'update'])->on('posts')
->role('editor')
    ->can(['read', 'write', 'update'])->on('pages')

// ❌ BAD - Scattered
->role('editor')->can('read')->on('posts')
->role('viewer')->readOnly()->on('posts')
->role('editor')->can('write')->on('posts')
```

### 4. Use Descriptive Role Names

```php
// ✅ GOOD
->role('content-moderator')
->role('billing-admin')
->role('customer-support')

// ❌ BAD
->role('role1')
->role('user-type-a')
->role('group3')
```

### 5. Set Priorities for Overrides

```php
// High priority deny overrides lower allows
->deny('banned-user')
    ->to('*')
    ->on('*')
    ->withPriority(Priority::Critical)

->allow('editor')
    ->to('write')
    ->on('posts')
    ->withPriority(Priority::Normal)
```

---

## Testing Built Policies

```php
use Tests\TestCase;

class PolicyBuilderTest extends TestCase
{
    /** @test */
    public function it_builds_rbac_policy_correctly()
    {
        $policy = RbacPolicyBuilder::make()
            ->role('admin')->fullAccess()->on('posts')
            ->role('editor')->can(['read', 'write'])->on('posts')
            ->build();

        $rules = $policy->rules();

        expect($rules)->toHaveCount(2);
        expect($rules[0]->subject)->toBe('role:admin');
        expect($rules[0]->action)->toBe('*');
    }

    /** @test */
    public function it_builds_restful_policy_correctly()
    {
        $policy = RestfulPolicyBuilder::for('/api/posts')
            ->allowGetFor('*')
            ->allowPostFor('role:contributor')
            ->build();

        $rules = $policy->rules();

        expect($rules)->toHaveCount(2);
        expect($rules[0]->action)->toBe('GET');
        expect($rules[1]->action)->toBe('POST');
    }
}
```

---

## Common Patterns

### Pattern 1: Admin Bypass

```php
RbacPolicyBuilder::make()
    ->role('admin')
        ->fullAccess()
        ->onAny()
        ->withPriority(Priority::Critical)
    // ... other roles
```

### Pattern 2: Multi-Tenant Isolation

```php
RbacPolicyBuilder::make()
    ->role('admin')
        ->fullAccess()
        ->on('*')
        ->inDomain('tenant-1')
    ->role('admin')
        ->fullAccess()
        ->on('*')
        ->inDomain('tenant-2')
```

### Pattern 3: Tiered Access (SaaS)

```php
CrudPolicyBuilder::for('reports')
    ->allowReadFor('tier:free')
    ->allowReadFor('tier:pro')
    ->allowReadFor('tier:enterprise')

    ->allowCreateFor('tier:pro')
    ->allowCreateFor('tier:enterprise')

    ->allowUpdateFor('tier:enterprise')
    ->allowDeleteFor('tier:enterprise')
    ->build();
```

### Pattern 4: Public API with Auth Tiers

```php
RestfulPolicyBuilder::for('/api/v1/data')
    ->allowGetFor('*')                      // Public read
    ->allowOptionsFor('*')                  // CORS

    ->allowPostFor('api-key:valid')         // Authenticated create
    ->allowPutFor('api-key:valid')

    ->allowDeleteFor('tier:enterprise')     // Only enterprise can delete
    ->build();
```

---

## Migration from Manual Rules

### Before (Manual PolicyRule creation)

```php
$policy = new Policy([
    new PolicyRule('role:admin', 'posts', 'create', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'read', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'update', Effect::Allow),
    new PolicyRule('role:admin', 'posts', 'delete', Effect::Allow),
    new PolicyRule('role:editor', 'posts', 'read', Effect::Allow),
    new PolicyRule('role:editor', 'posts', 'update', Effect::Allow),
    new PolicyRule('role:viewer', 'posts', 'read', Effect::Allow),
]);
```

### After (Using Builder)

```php
$policy = RbacPolicyBuilder::make()
    ->role('admin')
        ->fullAccess()
        ->on('posts')
    ->role('editor')
        ->can(['read', 'update'])
        ->on('posts')
    ->role('viewer')
        ->readOnly()
        ->on('posts')
    ->build();
```

**Benefits:**
- 7 manual rules → 3 builder chains
- More readable and maintainable
- Less error-prone
- Self-documenting

---

## Related Guides

- [Persisting Policies](./persisting-policies.md) - Save built policies to storage
- [RBAC Model](../models/rbac.md) - Role-based authorization patterns
- [ACL Model](../models/acl.md) - Direct permission patterns
- [RESTful Model](../models/restful.md) - API authorization patterns
