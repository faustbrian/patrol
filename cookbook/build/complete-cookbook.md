# Patrol Authorization Cookbook - Complete Guide

> Generated on 2025-10-03 13:51:11

This is a concatenated version of all cookbook entries for easy reference, searching, and offline use.

---

# Patrol Authorization Cookbook

A comprehensive guide to authorization patterns and models using Patrol.

## Introduction

This cookbook provides practical examples and patterns for implementing various authorization models with Patrol. Each recipe includes complete working examples, Laravel integration, testing strategies, and best practices.

Whether you're building a simple app or a complex multi-tenant system, you'll find patterns and examples to guide your implementation.

## Table of Contents

### Basic Models

#### [ACL (Access Control List)](./acl.md)
Direct subject-resource-action permissions for explicit authorization. The simplest model for straightforward permission systems.

**Use when:** You have a small application with direct user-to-resource relationships.

---

#### [ACL with Superuser](./acl-superuser.md)
Special users with unrestricted access using wildcard permissions. Perfect for admin accounts.

**Use when:** You need system administrators who bypass normal permission checks.

---

#### [ACL without Users](./acl-without-users.md)
Authorization for systems without authentication. Public APIs and anonymous access.

**Use when:** Building public APIs, documentation sites, or systems without user accounts.

---

#### [ACL without Resources](./acl-without-resources.md)
Permission types instead of specific resources. Capability-based authorization (write-article, export-pdf).

**Use when:** Implementing feature flags, SaaS tiers, or system-wide capabilities.

---

### Role-Based Models

#### [RBAC (Role-Based Access Control)](./rbac.md)
Assign permissions to roles, then assign roles to users. The most common enterprise authorization model.

**Use when:** You have clear job functions and 10+ users with similar permission patterns.

---

#### [RBAC with Resource Roles](./rbac-resource-roles.md)
Both users and resources have roles. Sophisticated role interactions (user clearance level + document classification).

**Use when:** Implementing security clearances, subscription tiers, or document classification systems.

---

#### [RBAC with Domains](./rbac-domains.md)
Multi-tenant role sets where users have different roles in different organizations/workspaces.

**Use when:** Building multi-tenant SaaS, workspace-based apps, or multi-organization systems.

---

### Advanced Models

#### [ABAC (Attribute-Based Access Control)](./abac.md)
Dynamic authorization using attribute expressions. Evaluate conditions at runtime (ownership, time, location).

**Use when:** You need dynamic permissions based on attributes, relationships, or context.

---

#### [RESTful Authorization](./restful.md)
HTTP method and path-based authorization for API endpoints (GET /api/users → allow/deny).

**Use when:** Building REST APIs, microservices, or HTTP-based services.

---

### Security Patterns

#### [Deny-Override](./deny-override.md)
Explicit deny rules that override all allow rules. Security-critical access control.

**Use when:** You need security overrides, compliance requirements, or emergency lockdowns.

---

#### [Priority-Based](./priority-based.md)
Firewall-style rule ordering where rules are evaluated by priority, first match wins.

**Use when:** You need explicit control over rule evaluation order or complex override scenarios.

---

## Quick Navigation

### By Use Case

**Small Applications (< 10 users)**
- [ACL](./acl.md)
- [ACL with Superuser](./acl-superuser.md)

**Enterprise Applications**
- [RBAC](./rbac.md)
- [RBAC with Domains](./rbac-domains.md)
- [Deny-Override](./deny-override.md)

**Multi-Tenant SaaS**
- [RBAC with Domains](./rbac-domains.md)
- [ACL without Resources](./acl-without-resources.md) (for feature tiers)

**Public APIs**
- [RESTful](./restful.md)
- [ACL without Users](./acl-without-users.md)

**Complex Dynamic Permissions**
- [ABAC](./abac.md)
- [Priority-Based](./priority-based.md)

**Security-Critical Systems**
- [Deny-Override](./deny-override.md)
- [Priority-Based](./priority-based.md)
- [RBAC with Resource Roles](./rbac-resource-roles.md)

### By Feature

**Ownership-Based**
- [ABAC](./abac.md) - resource.owner_id == subject.id

**Time-Based**
- [ABAC](./abac.md) - Business hours restrictions
- [Priority-Based](./priority-based.md) - Time-based priority rules

**Location-Based**
- [ABAC](./abac.md) - IP restrictions, geo-fencing
- [RESTful](./restful.md) - API endpoint access by location

**Hierarchy/Clearance**
- [RBAC with Resource Roles](./rbac-resource-roles.md)
- [Priority-Based](./priority-based.md)

**Feature Flags/Tiers**
- [ACL without Resources](./acl-without-resources.md)
- [RBAC with Resource Roles](./rbac-resource-roles.md)

## How to Use This Cookbook

### 1. Choose Your Model

Start by understanding your requirements:

- **How many users?** Small apps → ACL, Large apps → RBAC
- **Multi-tenant?** → RBAC with Domains
- **Dynamic permissions?** → ABAC
- **API authorization?** → RESTful
- **Security-critical?** → Deny-Override

### 2. Read the Recipe

Each cookbook entry follows the same structure:

1. **Overview** - What the model does
2. **Basic Concept** - Core idea in simple terms
3. **Use Cases** - When to use this model
4. **Core Example** - Working code example
5. **Patterns** - Common patterns (2-3 examples)
6. **Laravel Integration** - Subject/Resource resolvers, middleware, controllers
7. **Real-World Example** - Complete realistic scenario
8. **Database Storage** - Migrations and repository implementation
9. **Testing** - Pest test examples
10. **Best Practices** - Numbered list of recommendations
11. **When to Use** - ✅ Good for / ❌ Avoid for
12. **Related Models** - Links to related patterns

### 3. Implement

Copy the examples and adapt them to your needs:

```php
// Start with the core example
$policy = new Policy([
    new PolicyRule('user-1', 'article:*', 'read', Effect::Allow),
]);

// Add Laravel integration
Patrol::resolveSubject(function () {
    return new Subject(auth()->id());
});

// Add tests
it('allows user to read articles', function () {
    // ... test code from cookbook
});
```

### 4. Combine Patterns

You can combine multiple patterns:

```php
// RBAC + ABAC + Deny-Override
$policy = new Policy([
    // Role-based
    new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),

    // Ownership-based (ABAC)
    new ConditionalPolicyRule(
        condition: 'resource.author_id == subject.id',
        resource: 'article:*',
        action: '*',
        effect: Effect::Allow
    ),

    // Deny override for suspended users
    new PolicyRule('status:suspended', '*', '*', Effect::Deny),
]);
```

## Common Patterns

### Combining Models

Most real-world applications combine multiple models:

#### SaaS Application
```php
// RBAC (organization roles) + ABAC (ownership) + ACL without Resources (features)
new PolicyRule('role:admin', 'organization:*', '*', Effect::Allow), // RBAC
new ConditionalPolicyRule('resource.owner_id == subject.id', 'project:*', '*', Effect::Allow), // ABAC
new PolicyRule('subscription:premium', '*', 'export-pdf', Effect::Allow), // Feature-based
```

#### Enterprise System
```php
// RBAC with Domains + Deny-Override + Priority
new PolicyRule('role:manager', 'team:*', 'manage', Effect::Allow, domain: 'department-1'), // Domain RBAC
new PolicyRule('status:suspended', '*', '*', Effect::Deny, priority: 900), // Deny override
new PolicyRule('clearance:secret', 'classified:*', 'read', Effect::Allow, priority: 500), // Priority
```

### Migration Path

Start simple, add complexity as needed:

1. **Start:** Basic ACL
2. **Growing:** Add RBAC when you have repeated permission patterns
3. **Multi-tenant:** Add Domains when you need organization isolation
4. **Dynamic:** Add ABAC for ownership and complex logic
5. **Security:** Add Deny-Override for critical restrictions

## Examples by Framework Integration

### Laravel

Every recipe includes:
- Subject/Resource resolvers
- Middleware examples
- Controller examples
- Blade directive usage (where applicable)

### Testing

Every recipe includes 3 Pest test examples:
- Happy path (allow)
- Sad path (deny)
- Edge case (pattern-specific)

### Database

Every recipe includes:
- Migration examples
- Repository implementation
- Model relationships (where applicable)

## Contributing

Found a pattern not covered here? Have a better example? Contributions are welcome!

## Quick Reference

### Core Components

- **Subject** - The user/entity requesting access
- **Resource** - The thing being accessed
- **Action** - What they want to do
- **Effect** - Allow or Deny
- **Policy** - Collection of rules
- **PolicyRule** - Basic rule (subject + resource + action → effect)
- **ConditionalPolicyRule** - ABAC rule with conditions
- **Priority** - For priority-based evaluation
- **Domain** - For multi-tenant scenarios

### Effect Resolvers

- **EffectResolver** - Standard (all allows required)
- **DenyOverrideEffectResolver** - Deny wins
- **PriorityEffectResolver** - First match wins

### Rule Matchers

- **AclRuleMatcher** - Basic ACL matching
- **RbacRuleMatcher** - Role-based matching
- **AbacRuleMatcher** - Attribute/condition evaluation
- **RestfulRuleMatcher** - HTTP path/method matching
- **PriorityRuleMatcher** - Priority-ordered matching

## Getting Help

1. **Check the cookbook** - Most patterns are covered
2. **Read the pattern's "Related Models"** - Find similar patterns
3. **Review tests** - Each recipe has test examples
4. **Combine patterns** - Mix and match as needed

## Pattern Decision Tree

```
Do you have user accounts?
├─ No → ACL without Users, RESTful (for APIs)
└─ Yes
   └─ Do you have < 10 users?
      ├─ Yes → ACL, ACL with Superuser
      └─ No
         └─ Do you need multi-tenant?
            ├─ Yes → RBAC with Domains
            └─ No
               └─ Do you need dynamic permissions?
                  ├─ Yes → ABAC
                  └─ No → RBAC
```

## Next Steps

1. Choose a pattern from the table of contents above
2. Read the full recipe
3. Implement the examples
4. Add tests
5. Combine with other patterns as needed

Happy authorizing! 🔐




---

<div style="page-break-before: always;"></div>

# ACL (Access Control List)

Direct subject-resource-action permissions for explicit authorization.

## Overview

ACL is the simplest authorization model where you explicitly define which subjects can perform which actions on specific resources. It's ideal for straightforward permission systems without roles or complex logic.

## Basic Concept

```
Subject + Resource + Action = Permission
```

## Use Cases

- Simple file permission systems
- Direct user-to-document access control
- Explicit permission grants
- Small-scale applications with few users

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, EffectResolver};

// Define policy rules
$policy = new Policy([
    // User 1 can read document 1
    new PolicyRule(
        subject: 'user-1',
        resource: 'document-1',
        action: 'read',
        effect: Effect::Allow
    ),

    // User 1 can edit document 1
    new PolicyRule(
        subject: 'user-1',
        resource: 'document-1',
        action: 'edit',
        effect: Effect::Allow
    ),

    // User 2 can only read document 1
    new PolicyRule(
        subject: 'user-2',
        resource: 'document-1',
        action: 'read',
        effect: Effect::Allow
    ),
]);

// Evaluate authorization
$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

$subject = new Subject('user-1');
$resource = new Resource('document-1', 'document');
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow
```

## Wildcard Patterns

```php
// Allow user to read all documents
new PolicyRule(
    subject: 'user-1',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);

// Allow user all actions on specific document
new PolicyRule(
    subject: 'user-1',
    resource: 'document-1',
    action: '*',
    effect: Effect::Allow
);
```

## Laravel Integration

### Middleware

```php
// routes/web.php
Route::middleware(['patrol:document-1:read'])->get('/documents/1', function () {
    return view('documents.show', ['id' => 1]);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class DocumentController extends Controller
{
    public function show(string $id)
    {
        $document = Document::findOrFail($id);

        // Check ACL permission
        if (!Patrol::check($document, 'read')) {
            abort(403);
        }

        return view('documents.show', compact('document'));
    }

    public function update(Request $request, string $id)
    {
        $document = Document::findOrFail($id);

        // Require edit permission
        Patrol::authorize($document, 'edit');

        $document->update($request->validated());

        return redirect()->route('documents.show', $id);
    }
}
```

## Real-World Example: File Sharing App

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Alice owns file1 - full access
    new PolicyRule('alice', 'file:1', 'read', Effect::Allow),
    new PolicyRule('alice', 'file:1', 'write', Effect::Allow),
    new PolicyRule('alice', 'file:1', 'delete', Effect::Allow),
    new PolicyRule('alice', 'file:1', 'share', Effect::Allow),

    // Bob is shared on file1 - read only
    new PolicyRule('bob', 'file:1', 'read', Effect::Allow),

    // Charlie is shared on file1 - read and write
    new PolicyRule('charlie', 'file:1', 'read', Effect::Allow),
    new PolicyRule('charlie', 'file:1', 'write', Effect::Allow),

    // Alice owns file2
    new PolicyRule('alice', 'file:2', '*', Effect::Allow),
]);
```

## Database Storage

```php
// Migration
Schema::create('acl_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('subject_id');
    $table->string('resource_type');
    $table->string('resource_id');
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamps();

    $table->unique(['subject_id', 'resource_type', 'resource_id', 'action']);
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class AclRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('acl_permissions')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject_id,
                resource: "{$row->resource_type}:{$row->resource_id}",
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }

    public function grant(string $subjectId, string $resourceType, string $resourceId, string $action): void
    {
        DB::table('acl_permissions')->insert([
            'subject_id' => $subjectId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'effect' => 'allow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function revoke(string $subjectId, string $resourceType, string $resourceId, string $action): void
    {
        DB::table('acl_permissions')
            ->where('subject_id', $subjectId)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('action', $action)
            ->delete();
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows user to read document with explicit permission', function () {
    $policy = new Policy([
        new PolicyRule('user-1', 'document-1', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('document-1', 'document'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies user without explicit permission', function () {
    $policy = new Policy([
        new PolicyRule('user-1', 'document-1', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-2'), // different user
        new Resource('document-1', 'document'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports wildcard resource patterns', function () {
    $policy = new Policy([
        new PolicyRule('user-1', 'document:*', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('document-123', 'document'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Allow);
});
```

## Best Practices

1. **Explicit is Better**: Define exact permissions rather than relying on wildcards
2. **Least Privilege**: Grant only the minimum required permissions
3. **Regular Audits**: Review ACL entries periodically to remove stale permissions
4. **Group Similar Actions**: Use wildcards for common permission sets
5. **Document Ownership**: Track who created/owns resources for easier permission management

## When to Use ACL

✅ **Good for:**
- Small applications with few users
- Simple permission requirements
- Direct user-resource relationships
- File/folder permission systems

❌ **Consider alternatives for:**
- Large user bases (use RBAC)
- Complex permission logic (use ABAC)
- Dynamic authorization (use ABAC)
- Multi-tenant systems (use RBAC with Domains)

## Related Models

- [ACL with Superuser](./acl-superuser.md) - Add admin bypass
- [RBAC](./rbac.md) - Role-based permissions
- [ABAC](./abac.md) - Attribute-based logic




---

<div style="page-break-before: always;"></div>

# ACL with Superuser

Special users with unrestricted access to all resources using wildcard permissions.

## Overview

ACL with Superuser extends basic ACL by designating certain users as administrators who bypass normal permission checks. This is achieved using wildcard rules that match all resources and actions.

## Basic Concept

```
Superuser = * (all resources) + * (all actions)
```

## Use Cases

- System administrators
- Application owners
- Support staff needing emergency access
- Service accounts with full privileges
- Testing/debugging accounts

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, EffectResolver};

$policy = new Policy([
    // Admin user has access to everything
    new PolicyRule(
        subject: 'admin-user',
        resource: '*',
        action: '*',
        effect: Effect::Allow
    ),

    // Regular user with limited access
    new PolicyRule(
        subject: 'user-1',
        resource: 'document:1',
        action: 'read',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

// Admin can access anything
$result = $evaluator->evaluate(
    $policy,
    new Subject('admin-user'),
    new Resource('document-999', 'document'),
    new Action('delete')
);
// => Effect::Allow

// Regular user cannot
$result = $evaluator->evaluate(
    $policy,
    new Subject('user-1'),
    new Resource('document-999', 'document'),
    new Action('delete')
);
// => Effect::Deny
```

## Patterns

### Global Superuser

```php
// Complete access to everything
new PolicyRule(
    subject: 'superuser',
    resource: '*',
    action: '*',
    effect: Effect::Allow
);
```

### Resource-Scoped Superuser

```php
// Admin for all documents only
new PolicyRule(
    subject: 'document-admin',
    resource: 'document:*',
    action: '*',
    effect: Effect::Allow
);

// Admin for all projects only
new PolicyRule(
    subject: 'project-admin',
    resource: 'project:*',
    action: '*',
    effect: Effect::Allow
);
```

### Action-Scoped Superuser

```php
// Read-only superuser (can read anything, but not modify)
new PolicyRule(
    subject: 'auditor',
    resource: '*',
    action: 'read',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver with Superuser Check

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest');
    }

    // Check if user is superuser
    $isSuperuser = $user->hasRole('superuser');

    return new Subject(
        $isSuperuser ? 'superuser' : $user->id,
        [
            'is_superuser' => $isSuperuser,
            'roles' => $user->roles->pluck('name')->all(),
        ]
    );
});
```

### Middleware

```php
// Regular authorization
Route::middleware(['patrol:document:edit'])->group(function () {
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
});

// Superuser-only route
Route::middleware(['patrol:*:*'])->group(function () {
    Route::get('/admin/system-settings', [AdminController::class, 'settings']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class DocumentController extends Controller
{
    public function destroy(Document $document)
    {
        // Only superusers or document owners can delete
        if (!Patrol::check($document, 'delete')) {
            abort(403, 'Only superusers or owners can delete documents');
        }

        $document->delete();

        return redirect()->route('documents.index');
    }
}
```

## Real-World Example: Multi-Role System

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // System administrator - unrestricted access
    new PolicyRule('superuser', '*', '*', Effect::Allow),

    // Content admin - manage all content
    new PolicyRule('content-admin', 'article:*', '*', Effect::Allow),
    new PolicyRule('content-admin', 'page:*', '*', Effect::Allow),

    // User admin - manage all users
    new PolicyRule('user-admin', 'user:*', '*', Effect::Allow),

    // Auditor - read-only access to everything
    new PolicyRule('auditor', '*', 'read', Effect::Allow),
    new PolicyRule('auditor', '*', 'export', Effect::Allow),

    // Regular users
    new PolicyRule('user-1', 'article:1', 'edit', Effect::Allow),
    new PolicyRule('user-2', 'article:2', 'edit', Effect::Allow),
]);
```

## Database Storage

```php
// Migration
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->boolean('is_superuser')->default(false);
    $table->timestamps();
});

// Model
class User extends Authenticatable
{
    public function isSuperuser(): bool
    {
        return $this->is_superuser;
    }

    public function getSubjectIdentifier(): string
    {
        return $this->is_superuser ? 'superuser' : $this->id;
    }
}

// Policy Repository
class SuperuserAclRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = [];

        // Add superuser rule
        $rules[] = new PolicyRule(
            subject: 'superuser',
            resource: '*',
            action: '*',
            effect: Effect::Allow
        );

        // Add regular ACL rules from database
        $aclRules = DB::table('acl_permissions')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject_id,
                resource: "{$row->resource_type}:{$row->resource_id}",
                action: $row->action,
                effect: Effect::from($row->effect),
            ));

        return new Policy(array_merge($rules, $aclRules->all()));
    }
}
```

## Security Considerations

### Audit Logging

```php
use Illuminate\Support\Facades\Log;

class AuditingPolicyEvaluator extends PolicyEvaluator
{
    public function evaluate(Policy $policy, Subject $subject, Resource $resource, Action $action): Effect
    {
        $result = parent::evaluate($policy, $subject, $resource, $action);

        // Log superuser access
        if ($subject->id() === 'superuser') {
            Log::info('Superuser access', [
                'subject' => $subject->attributes(),
                'resource' => $resource->id(),
                'action' => $action->name(),
                'result' => $result->value,
                'timestamp' => now(),
                'ip' => request()->ip(),
            ]);
        }

        return $result;
    }
}
```

### Temporary Superuser

```php
// Grant temporary superuser access
class TemporarySuperuserService
{
    public function grantTemporaryAccess(User $user, int $minutes = 30): void
    {
        Cache::put(
            "superuser:{$user->id}",
            true,
            now()->addMinutes($minutes)
        );

        Log::warning('Temporary superuser access granted', [
            'user_id' => $user->id,
            'duration_minutes' => $minutes,
            'granted_by' => auth()->id(),
        ]);
    }

    public function hasTemporaryAccess(User $user): bool
    {
        return Cache::has("superuser:{$user->id}");
    }
}

// Update subject resolver
Patrol::resolveSubject(function () {
    $user = auth()->user();

    $isSuperuser = $user?->is_superuser
        || app(TemporarySuperuserService::class)->hasTemporaryAccess($user);

    return new Subject(
        $isSuperuser ? 'superuser' : $user->id
    );
});
```

## Testing

```php
use Pest\Tests;

it('grants superuser access to all resources', function () {
    $policy = new Policy([
        new PolicyRule('superuser', '*', '*', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    // Superuser can access anything
    $result = $evaluator->evaluate(
        $policy,
        new Subject('superuser'),
        new Resource('any-resource', 'any-type'),
        new Action('any-action')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies regular users from superuser actions', function () {
    $policy = new Policy([
        new PolicyRule('superuser', '*', '*', Effect::Allow),
        new PolicyRule('user-1', 'document:1', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    // Regular user cannot access other resources
    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('document-2', 'document'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports resource-scoped superusers', function () {
    $policy = new Policy([
        new PolicyRule('document-admin', 'document:*', '*', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    // Can access documents
    $result = $evaluator->evaluate(
        $policy,
        new Subject('document-admin'),
        new Resource('document-123', 'document'),
        new Action('delete')
    );
    expect($result)->toBe(Effect::Allow);

    // Cannot access projects
    $result = $evaluator->evaluate(
        $policy,
        new Subject('document-admin'),
        new Resource('project-1', 'project'),
        new Action('delete')
    );
    expect($result)->toBe(Effect::Deny);
});
```

## Best Practices

1. **Minimize Superusers**: Only grant to essential personnel
2. **Audit Everything**: Log all superuser actions
3. **Temporary Access**: Use time-limited superuser grants when possible
4. **Scope Appropriately**: Use resource or action scoping when full access isn't needed
5. **Multi-Factor Auth**: Require additional authentication for superuser actions
6. **Regular Reviews**: Audit superuser list quarterly

## Security Warnings

⚠️ **Dangers:**
- Superusers bypass all permission checks
- No granular control once granted
- Hard to revoke if compromised
- Can accidentally cause damage

🔒 **Mitigations:**
- Implement audit logging
- Use temporary grants
- Require MFA for superuser accounts
- Regular access reviews
- Separate superuser accounts from daily accounts

## When to Use Superuser

✅ **Good for:**
- System administrators
- Emergency access scenarios
- Support/debugging needs
- Automated service accounts

❌ **Avoid for:**
- Regular application users
- Long-term access grants
- Multiple admin levels (use RBAC instead)
- Fine-grained permissions (use ACL/ABAC instead)

## Related Models

- [ACL](./acl.md) - Basic access control
- [RBAC](./rbac.md) - Role-based alternative
- [Priority-Based](./priority-based.md) - Override superuser in emergencies




---

<div style="page-break-before: always;"></div>

# ACL without Users

Authorization for systems without authentication or user accounts.

## Overview

ACL without Users is designed for applications where resources need access control but there are no user accounts. This uses wildcard subjects (`*`) to grant anonymous or public access to resources based solely on actions and resource types.

## Basic Concept

```
* (anonymous) + Resource + Action = Public Permission
```

## Use Cases

- Public APIs without authentication
- Static content websites
- Public file servers
- Read-only documentation sites
- Open data platforms
- Public voting/polling systems
- Anonymous feedback forms
- Public bulletin boards

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, EffectResolver};

$policy = new Policy([
    // Anyone can read blog posts
    new PolicyRule(
        subject: '*',
        resource: 'blog-post:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Anyone can read public documents
    new PolicyRule(
        subject: '*',
        resource: 'document:public-*',
        action: 'read',
        effect: Effect::Allow
    ),

    // No one can delete (not even anonymous)
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: 'delete',
        effect: Effect::Deny
    ),
]);

$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

// Anonymous user can read blog posts
$result = $evaluator->evaluate(
    $policy,
    new Subject('*'), // or new Subject('anonymous')
    new Resource('blog-post-1', 'blog-post'),
    new Action('read')
);
// => Effect::Allow
```

## Patterns

### Read-Only Public Access

```php
// Public read access to all content
new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'read',
    effect: Effect::Allow
);

// Deny all modifications
new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'write',
    effect: Effect::Deny
);

new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'delete',
    effect: Effect::Deny
);
```

### Selective Resource Access

```php
// Public articles only
new PolicyRule(
    subject: '*',
    resource: 'article:published-*',
    action: 'read',
    effect: Effect::Allow
);

// Public downloads
new PolicyRule(
    subject: '*',
    resource: 'file:public-*',
    action: 'download',
    effect: Effect::Allow
);

// Block private resources
new PolicyRule(
    subject: '*',
    resource: 'file:private-*',
    action: '*',
    effect: Effect::Deny
);
```

### Action-Based Control

```php
// Allow specific actions for anonymous users
new PolicyRule(
    subject: '*',
    resource: 'form:*',
    action: 'submit',
    effect: Effect::Allow
);

new PolicyRule(
    subject: '*',
    resource: 'poll:*',
    action: 'vote',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    // Always return anonymous subject for public apps
    return new Subject('*', [
        'is_anonymous' => true,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});
```

### Middleware

```php
// Public read-only route
Route::middleware(['patrol:article:read'])->get('/articles/{id}', function ($id) {
    return Article::findOrFail($id);
});

// Public submission
Route::middleware(['patrol:form:submit'])->post('/feedback', function (Request $request) {
    Feedback::create($request->validated());
    return response()->json(['message' => 'Thank you!']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function index()
    {
        // Filter to only public articles
        $articles = Article::all();
        $publicArticles = Patrol::filter($articles, 'read');

        return view('articles.index', compact('publicArticles'));
    }

    public function show(Article $article)
    {
        // Check if anonymous user can view
        if (!Patrol::check($article, 'read')) {
            abort(403, 'This article is not public');
        }

        return view('articles.show', compact('article'));
    }
}
```

## Real-World Example: Public API

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Public endpoints - anyone can read
    new PolicyRule('*', 'endpoint:/api/status', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/docs', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/health', 'GET', Effect::Allow),

    // Public data endpoints
    new PolicyRule('*', 'endpoint:/api/articles', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/articles/*', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/categories', 'GET', Effect::Allow),

    // Public submission endpoints
    new PolicyRule('*', 'endpoint:/api/feedback', 'POST', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/contact', 'POST', Effect::Allow),

    // Block all other modifications
    new PolicyRule('*', 'endpoint:*', 'POST', Effect::Deny),
    new PolicyRule('*', 'endpoint:*', 'PUT', Effect::Deny),
    new PolicyRule('*', 'endpoint:*', 'DELETE', Effect::Deny),
]);
```

## Database Storage

```php
// Migration
Schema::create('public_resources', function (Blueprint $table) {
    $table->id();
    $table->string('resource_type');
    $table->string('resource_pattern'); // e.g., 'public-*', 'published-*'
    $table->json('allowed_actions');     // ['read', 'download']
    $table->boolean('is_public')->default(true);
    $table->timestamps();
});

// Repository Implementation
class PublicResourceRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('public_resources')
            ->where('is_public', true)
            ->get()
            ->flatMap(function ($resource) {
                return collect($resource->allowed_actions)->map(
                    fn($action) => new PolicyRule(
                        subject: '*',
                        resource: "{$resource->resource_type}:{$resource->resource_pattern}",
                        action: $action,
                        effect: Effect::Allow
                    )
                );
            })
            ->all();

        return new Policy($rules);
    }
}
```

## Rate Limiting for Anonymous Access

```php
use Illuminate\Support\Facades\RateLimiter;

class RateLimitedPatrolMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Rate limit by IP for anonymous users
        $key = 'patrol:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 100)) {
            abort(429, 'Too many requests');
        }

        RateLimiter::hit($key, 60); // 60 seconds

        // Check patrol authorization
        if (!Patrol::check($resource, $action)) {
            abort(403);
        }

        return $next($request);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows anonymous users to read public resources', function () {
    $policy = new Policy([
        new PolicyRule('*', 'article:public-*', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('public-article-1', 'article'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies anonymous users from modifying resources', function () {
    $policy = new Policy([
        new PolicyRule('*', 'article:*', 'read', Effect::Allow),
        new PolicyRule('*', 'article:*', 'write', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('article-1', 'article'),
        new Action('write')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports action-based anonymous access', function () {
    $policy = new Policy([
        new PolicyRule('*', 'form:*', 'submit', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('form-1', 'form'),
        new Action('submit')
    );

    expect($result)->toBe(Effect::Allow);
});
```

## Security Considerations

### 1. CSRF Protection

```php
// Always verify CSRF tokens for mutations
Route::middleware(['patrol:form:submit', 'csrf'])->post('/feedback', ...);
```

### 2. Rate Limiting

```php
// Aggressive rate limits for anonymous users
Route::middleware(['patrol:api:read', 'throttle:60,1'])->get('/api/data', ...);
```

### 3. Input Validation

```php
public function submit(Request $request)
{
    // Strict validation for anonymous submissions
    $validated = $request->validate([
        'email' => 'required|email|max:255',
        'message' => 'required|string|max:1000',
    ]);

    // Additional checks
    if ($this->isSpam($validated['message'])) {
        abort(422, 'Spam detected');
    }

    Feedback::create($validated);
}
```

### 4. Resource Visibility

```php
// Model scope for public resources only
class Article extends Model
{
    public function scopePublic($query)
    {
        return $query->where('is_public', true)
            ->where('published_at', '<=', now());
    }
}
```

## Best Practices

1. **Default Deny**: Only explicitly allow necessary public access
2. **Rate Limiting**: Always rate limit anonymous endpoints
3. **Minimal Exposure**: Limit public actions to read-only when possible
4. **Audit Logs**: Track anonymous access patterns
5. **Resource Scoping**: Use prefixes like `public-*` to clearly mark public resources
6. **CSRF Protection**: Enable for all state-changing operations

## When to Use ACL Without Users

✅ **Good for:**
- Public documentation sites
- Read-only APIs
- Anonymous surveys/polls
- Public file downloads
- Static content sites

❌ **Avoid for:**
- Applications requiring user tracking
- Systems needing personalization
- Multi-user collaboration
- Audit-heavy applications

## Combining with Authentication

```php
// Mix authenticated and anonymous access
$policy = new Policy([
    // Anonymous users: read-only
    new PolicyRule('*', 'article:*', 'read', Effect::Allow),

    // Authenticated users: can comment
    new PolicyRule('user:*', 'article:*', 'comment', Effect::Allow),

    // Authors: can edit
    new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow),
]);
```

## Related Models

- [ACL](./acl.md) - User-based ACL
- [ABAC](./abac.md) - Attribute-based for more control
- [RESTful](./restful.md) - HTTP-based authorization




---

<div style="page-break-before: always;"></div>

# ACL without Resources

Permission types instead of specific resources for action-based authorization.

## Overview

ACL without Resources is a permission model where you define what types of actions users can perform system-wide, rather than tying permissions to specific resources. This creates a simpler model focused on capabilities (e.g., "write-article", "read-log") instead of resource-action pairs.

## Basic Concept

```
Subject + Permission Type = Capability
```

## Use Cases

- System-wide capabilities (can create invoices, can approve requests)
- Feature flags and feature access control
- Administrative permissions (can access admin panel, can view reports)
- Application-level permissions without resource specificity
- Module-based access control
- License/tier-based feature access

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, EffectResolver};

// Define capability-based policy
$policy = new Policy([
    // User can write articles (any article)
    new PolicyRule(
        subject: 'user-1',
        resource: '*',
        action: 'write-article',
        effect: Effect::Allow
    ),

    // User can read logs (any log)
    new PolicyRule(
        subject: 'user-1',
        resource: '*',
        action: 'read-log',
        effect: Effect::Allow
    ),

    // User can approve requests (any request)
    new PolicyRule(
        subject: 'user-2',
        resource: '*',
        action: 'approve-request',
        effect: Effect::Allow
    ),

    // User can generate reports
    new PolicyRule(
        subject: 'user-2',
        resource: '*',
        action: 'generate-report',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

// Check capability
$subject = new Subject('user-1');
$resource = new Resource('*', 'any');
$action = new Action('write-article');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow
```

## Patterns

### Feature-Based Permissions

```php
// Premium features
new PolicyRule(
    subject: 'premium-user',
    resource: '*',
    action: 'export-pdf',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'premium-user',
    resource: '*',
    action: 'advanced-analytics',
    effect: Effect::Allow
);

// Free tier restrictions
new PolicyRule(
    subject: 'free-user',
    resource: '*',
    action: 'export-pdf',
    effect: Effect::Deny
);
```

### Module-Based Access

```php
// CRM module access
new PolicyRule(
    subject: 'sales-user',
    resource: '*',
    action: 'access-crm',
    effect: Effect::Allow
);

// Inventory module access
new PolicyRule(
    subject: 'warehouse-user',
    resource: '*',
    action: 'access-inventory',
    effect: Effect::Allow
);

// Finance module access
new PolicyRule(
    subject: 'accountant',
    resource: '*',
    action: 'access-finance',
    effect: Effect::Allow
);
```

### Administrative Capabilities

```php
// Can manage users globally
new PolicyRule(
    subject: 'admin',
    resource: '*',
    action: 'manage-users',
    effect: Effect::Allow
);

// Can configure system settings
new PolicyRule(
    subject: 'admin',
    resource: '*',
    action: 'configure-system',
    effect: Effect::Allow
);

// Can view audit logs
new PolicyRule(
    subject: 'admin',
    resource: '*',
    action: 'view-audit-logs',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver with Capabilities

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest');
    }

    return new Subject($user->id, [
        'tier' => $user->subscription_tier,
        'permissions' => $user->permissions->pluck('name')->all(),
        'modules' => $user->accessibleModules()->pluck('name')->all(),
    ]);
});
```

### Middleware for Capabilities

```php
// routes/web.php

// Require capability to access feature
Route::middleware(['patrol:*:write-article'])->group(function () {
    Route::get('/articles/create', [ArticleController::class, 'create']);
    Route::post('/articles', [ArticleController::class, 'store']);
});

// Module access
Route::middleware(['patrol:*:access-crm'])->prefix('crm')->group(function () {
    Route::resource('contacts', ContactController::class);
    Route::resource('deals', DealController::class);
});

// Premium features
Route::middleware(['patrol:*:export-pdf'])->group(function () {
    Route::get('/reports/{id}/pdf', [ReportController::class, 'exportPdf']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function create()
    {
        // Check capability
        Patrol::authorize(new Resource('*', 'any'), 'write-article');

        return view('articles.create');
    }

    public function store(Request $request)
    {
        if (!Patrol::check(new Resource('*', 'any'), 'write-article')) {
            abort(403, 'You do not have permission to write articles');
        }

        $article = Article::create($request->validated());

        return redirect()->route('articles.show', $article);
    }
}

class ReportController extends Controller
{
    public function exportPdf(Report $report)
    {
        // Check premium capability
        if (!Patrol::check(new Resource('*', 'any'), 'export-pdf')) {
            return redirect()->route('pricing')
                ->with('error', 'PDF export is a premium feature');
        }

        return $report->toPdf()->download();
    }
}
```

## Real-World Example: SaaS Application

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Free tier capabilities
    new PolicyRule('free-user', '*', 'create-basic-project', Effect::Allow),
    new PolicyRule('free-user', '*', 'upload-file', Effect::Allow),
    new PolicyRule('free-user', '*', 'export-csv', Effect::Allow),

    // Free tier restrictions
    new PolicyRule('free-user', '*', 'export-pdf', Effect::Deny),
    new PolicyRule('free-user', '*', 'advanced-analytics', Effect::Deny),
    new PolicyRule('free-user', '*', 'api-access', Effect::Deny),

    // Pro tier capabilities (includes free tier)
    new PolicyRule('pro-user', '*', 'create-basic-project', Effect::Allow),
    new PolicyRule('pro-user', '*', 'upload-file', Effect::Allow),
    new PolicyRule('pro-user', '*', 'export-csv', Effect::Allow),
    new PolicyRule('pro-user', '*', 'export-pdf', Effect::Allow),
    new PolicyRule('pro-user', '*', 'advanced-analytics', Effect::Allow),
    new PolicyRule('pro-user', '*', 'api-access', Effect::Allow),
    new PolicyRule('pro-user', '*', 'priority-support', Effect::Allow),

    // Enterprise tier capabilities (all features)
    new PolicyRule('enterprise-user', '*', '*', Effect::Allow),

    // Admin capabilities
    new PolicyRule('admin', '*', 'manage-users', Effect::Allow),
    new PolicyRule('admin', '*', 'configure-system', Effect::Allow),
    new PolicyRule('admin', '*', 'view-audit-logs', Effect::Allow),
    new PolicyRule('admin', '*', 'manage-billing', Effect::Allow),
]);
```

## Database Storage

```php
// Migration
Schema::create('user_capabilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('capability'); // e.g., 'write-article', 'export-pdf'
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'capability']);
});

Schema::create('subscription_tiers', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // 'free', 'pro', 'enterprise'
    $table->json('capabilities'); // Array of capability names
    $table->timestamps();
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class CapabilityRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = [];

        // Add user-specific capabilities
        $userCapabilities = DB::table('user_capabilities')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        foreach ($userCapabilities as $capability) {
            $rules[] = new PolicyRule(
                subject: (string) $capability->user_id,
                resource: '*',
                action: $capability->capability,
                effect: Effect::from($capability->effect),
            );
        }

        // Add tier-based capabilities
        $users = DB::table('users')
            ->join('subscriptions', 'users.id', '=', 'subscriptions.user_id')
            ->join('subscription_tiers', 'subscriptions.tier_id', '=', 'subscription_tiers.id')
            ->select('users.id', 'subscription_tiers.capabilities')
            ->get();

        foreach ($users as $user) {
            $capabilities = json_decode($user->capabilities, true);
            foreach ($capabilities as $capability) {
                $rules[] = new PolicyRule(
                    subject: (string) $user->id,
                    resource: '*',
                    action: $capability,
                    effect: Effect::Allow,
                );
            }
        }

        return new Policy($rules);
    }

    public function grantCapability(int $userId, string $capability, ?Carbon $expiresAt = null): void
    {
        DB::table('user_capabilities')->updateOrInsert(
            ['user_id' => $userId, 'capability' => $capability],
            [
                'effect' => 'allow',
                'expires_at' => $expiresAt,
                'updated_at' => now(),
            ]
        );
    }

    public function revokeCapability(int $userId, string $capability): void
    {
        DB::table('user_capabilities')
            ->where('user_id', $userId)
            ->where('capability', $capability)
            ->delete();
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows users with write-article capability', function () {
    $policy = new Policy([
        new PolicyRule('user-1', '*', 'write-article', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('*', 'any'),
        new Action('write-article')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies users without specific capability', function () {
    $policy = new Policy([
        new PolicyRule('user-1', '*', 'write-article', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('*', 'any'),
        new Action('export-pdf') // different capability
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports tier-based capability restrictions', function () {
    $policy = new Policy([
        // Free tier has limited capabilities
        new PolicyRule('free-user', '*', 'create-project', Effect::Allow),
        new PolicyRule('free-user', '*', 'export-pdf', Effect::Deny),

        // Pro tier has more capabilities
        new PolicyRule('pro-user', '*', 'create-project', Effect::Allow),
        new PolicyRule('pro-user', '*', 'export-pdf', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    // Free user cannot export PDF
    $result = $evaluator->evaluate(
        $policy,
        new Subject('free-user'),
        new Resource('*', 'any'),
        new Action('export-pdf')
    );
    expect($result)->toBe(Effect::Deny);

    // Pro user can export PDF
    $result = $evaluator->evaluate(
        $policy,
        new Subject('pro-user'),
        new Resource('*', 'any'),
        new Action('export-pdf')
    );
    expect($result)->toBe(Effect::Allow);
});
```

## Best Practices

1. **Clear Naming**: Use descriptive capability names (verb-noun format: write-article, export-pdf)
2. **Capability Grouping**: Group related capabilities by module or feature
3. **Documentation**: Maintain a capability registry documenting all available capabilities
4. **Tier Inheritance**: Structure tiers so higher tiers inherit lower tier capabilities
5. **Expiration**: Support time-limited capabilities for trials or temporary access
6. **Audit Trail**: Log capability grants and revocations
7. **Feature Discovery**: Provide UI to show available vs restricted capabilities

## When to Use ACL Without Resources

✅ **Good for:**
- SaaS applications with tiered pricing
- Feature flag systems
- Module-based access control
- Application-wide capabilities
- License-based feature access
- Simple permission systems

❌ **Avoid for:**
- Resource-specific permissions (use ACL or RBAC)
- Complex multi-tenant systems (use RBAC with Domains)
- Attribute-based logic (use ABAC)
- Per-resource authorization (use standard ACL)

## Feature Flag Integration

```php
// Combine with feature flags
class FeatureAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        // Check if feature is enabled globally
        if (!Features::enabled($feature)) {
            abort(404);
        }

        // Check if user has capability for this feature
        if (!Patrol::check(new Resource('*', 'any'), "access-{$feature}")) {
            abort(403, "Your plan does not include access to {$feature}");
        }

        return $next($request);
    }
}

// Usage
Route::middleware(['feature:advanced-analytics'])->group(function () {
    Route::get('/analytics/advanced', [AnalyticsController::class, 'advanced']);
});
```

## Related Models

- [ACL](./acl.md) - Resource-specific permissions
- [RBAC](./rbac.md) - Role-based capabilities
- [ABAC](./abac.md) - Attribute-based feature access




---

<div style="page-break-before: always;"></div>

# RBAC (Role-Based Access Control)

Assign permissions to roles, then assign roles to users for scalable authorization.

## Overview

RBAC (Role-Based Access Control) is an authorization model where permissions are assigned to roles rather than individual users. Users are then assigned roles, inheriting all permissions associated with those roles. This creates a more maintainable and scalable permission system.

## Basic Concept

```
User → Role → Permissions
Subject has Role → Role allows Action on Resource
```

## Use Cases

- Enterprise applications with defined job functions
- Multi-user systems with common permission patterns
- Organizations with hierarchical structures
- Systems requiring centralized permission management
- Applications with user groups or teams
- SaaS platforms with standard user types

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy, Role};
use Patrol\Core\Engine\{PolicyEvaluator, RbacRuleMatcher, EffectResolver};

// Define role-based policy
$policy = new Policy([
    // Editor role can create and edit articles
    new PolicyRule(
        subject: 'role:editor',
        resource: 'article:*',
        action: 'create',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:editor',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Viewer role can only read articles
    new PolicyRule(
        subject: 'role:viewer',
        resource: 'article:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Admin role has full access
    new PolicyRule(
        subject: 'role:admin',
        resource: '*',
        action: '*',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

// User with editor role
$subject = new Subject('user-1', [
    'roles' => ['editor'],
]);

$result = $evaluator->evaluate(
    $policy,
    $subject,
    new Resource('article-123', 'article'),
    new Action('edit')
);
// => Effect::Allow
```

## Patterns

### Hierarchical Roles

```php
// Define role hierarchy
new PolicyRule('role:admin', '*', '*', Effect::Allow);

new PolicyRule('role:manager', 'project:*', 'create', Effect::Allow);
new PolicyRule('role:manager', 'project:*', 'edit', Effect::Allow);
new PolicyRule('role:manager', 'user:*', 'view', Effect::Allow);

new PolicyRule('role:developer', 'project:*', 'view', Effect::Allow);
new PolicyRule('role:developer', 'code:*', 'edit', Effect::Allow);
new PolicyRule('role:developer', 'deployment:*', 'trigger', Effect::Allow);

new PolicyRule('role:viewer', 'project:*', 'view', Effect::Allow);
new PolicyRule('role:viewer', 'code:*', 'view', Effect::Allow);
```

### Department-Based Roles

```php
// Sales department roles
new PolicyRule('role:sales-rep', 'lead:*', 'view', Effect::Allow);
new PolicyRule('role:sales-rep', 'lead:*', 'edit', Effect::Allow);
new PolicyRule('role:sales-rep', 'deal:*', 'create', Effect::Allow);

new PolicyRule('role:sales-manager', 'lead:*', '*', Effect::Allow);
new PolicyRule('role:sales-manager', 'deal:*', '*', Effect::Allow);
new PolicyRule('role:sales-manager', 'report:sales:*', 'view', Effect::Allow);

// Support department roles
new PolicyRule('role:support-agent', 'ticket:*', 'view', Effect::Allow);
new PolicyRule('role:support-agent', 'ticket:*', 'reply', Effect::Allow);

new PolicyRule('role:support-manager', 'ticket:*', '*', Effect::Allow);
new PolicyRule('role:support-manager', 'report:support:*', 'view', Effect::Allow);
```

### Multi-Role Users

```php
// Users can have multiple roles
$subject = new Subject('user-1', [
    'roles' => ['developer', 'support-agent'], // Multiple roles
]);

// All role permissions are combined
// User can edit code (from developer role) AND view tickets (from support role)
```

## Laravel Integration

### Subject Resolver with Roles

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', ['roles' => []]);
    }

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
        'email' => $user->email,
    ]);
});
```

### Middleware

```php
// routes/web.php

// Require specific role
Route::middleware(['patrol:article:edit'])->group(function () {
    Route::get('/articles/{id}/edit', [ArticleController::class, 'edit']);
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
});

// Admin-only routes
Route::middleware(['patrol:*:*'])->prefix('admin')->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function create()
    {
        // Only editors and admins can create articles
        Patrol::authorize(new Resource('article', 'article'), 'create');

        return view('articles.create');
    }

    public function edit(Article $article)
    {
        // Check if user's role allows editing
        if (!Patrol::check($article, 'edit')) {
            abort(403, 'Your role does not allow editing articles');
        }

        return view('articles.edit', compact('article'));
    }

    public function destroy(Article $article)
    {
        // Only admin role can delete
        Patrol::authorize($article, 'delete');

        $article->delete();

        return redirect()->route('articles.index');
    }
}
```

## Real-World Example: Project Management System

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Project Owner - full control over their projects
    new PolicyRule('role:project-owner', 'project:owned:*', '*', Effect::Allow),

    // Project Manager - manage team and tasks
    new PolicyRule('role:project-manager', 'project:*', 'view', Effect::Allow),
    new PolicyRule('role:project-manager', 'project:*', 'edit', Effect::Allow),
    new PolicyRule('role:project-manager', 'task:*', 'create', Effect::Allow),
    new PolicyRule('role:project-manager', 'task:*', 'assign', Effect::Allow),
    new PolicyRule('role:project-manager', 'task:*', 'edit', Effect::Allow),
    new PolicyRule('role:project-manager', 'milestone:*', 'create', Effect::Allow),

    // Team Lead - manage assigned team tasks
    new PolicyRule('role:team-lead', 'project:*', 'view', Effect::Allow),
    new PolicyRule('role:team-lead', 'task:assigned:*', 'edit', Effect::Allow),
    new PolicyRule('role:team-lead', 'task:*', 'comment', Effect::Allow),

    // Developer - work on assigned tasks
    new PolicyRule('role:developer', 'project:*', 'view', Effect::Allow),
    new PolicyRule('role:developer', 'task:assigned:*', 'view', Effect::Allow),
    new PolicyRule('role:developer', 'task:assigned:*', 'update-status', Effect::Allow),
    new PolicyRule('role:developer', 'task:*', 'comment', Effect::Allow),

    // Client - view only
    new PolicyRule('role:client', 'project:accessible:*', 'view', Effect::Allow),
    new PolicyRule('role:client', 'task:*', 'view', Effect::Allow),
    new PolicyRule('role:client', 'task:*', 'comment', Effect::Allow),

    // Admin - everything
    new PolicyRule('role:admin', '*', '*', Effect::Allow),
]);
```

## Database Storage

```php
// Migrations
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('display_name');
    $table->text('description')->nullable();
    $table->timestamps();
});

Schema::create('role_permissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->string('resource_type');
    $table->string('resource_pattern')->default('*');
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamps();

    $table->unique(['role_id', 'resource_type', 'resource_pattern', 'action'], 'role_permission_unique');
});

Schema::create('role_user', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->primary(['role_id', 'user_id']);
});

// Models
class Role extends Model
{
    protected $fillable = ['name', 'display_name', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }
}

class User extends Authenticatable
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $this->roles()->detach($role->id);
        }
    }
}

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class RbacRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
            ->select(
                'roles.name as role_name',
                'role_permissions.resource_type',
                'role_permissions.resource_pattern',
                'role_permissions.action',
                'role_permissions.effect'
            )
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: "role:{$row->role_name}",
                resource: "{$row->resource_type}:{$row->resource_pattern}",
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows users with appropriate role to access resources', function () {
    $policy = new Policy([
        new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['roles' => ['editor']]);

    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('article-123', 'article'),
        new Action('edit')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies users without required role', function () {
    $policy = new Policy([
        new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['roles' => ['viewer']]);

    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('article-123', 'article'),
        new Action('edit')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports multiple roles per user', function () {
    $policy = new Policy([
        new PolicyRule('role:developer', 'code:*', 'edit', Effect::Allow),
        new PolicyRule('role:reviewer', 'code:*', 'review', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // User has both roles
    $subject = new Subject('user-1', ['roles' => ['developer', 'reviewer']]);

    // Can edit (from developer role)
    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('code-123', 'code'),
        new Action('edit')
    );
    expect($result)->toBe(Effect::Allow);

    // Can review (from reviewer role)
    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('code-123', 'code'),
        new Action('review')
    );
    expect($result)->toBe(Effect::Allow);
});
```

## Role Management

### Seeding Default Roles

```php
class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access',
                'permissions' => [
                    ['resource_type' => '*', 'resource_pattern' => '*', 'action' => '*'],
                ],
            ],
            [
                'name' => 'editor',
                'display_name' => 'Editor',
                'description' => 'Can create and edit content',
                'permissions' => [
                    ['resource_type' => 'article', 'resource_pattern' => '*', 'action' => 'create'],
                    ['resource_type' => 'article', 'resource_pattern' => '*', 'action' => 'edit'],
                    ['resource_type' => 'article', 'resource_pattern' => '*', 'action' => 'view'],
                ],
            ],
            [
                'name' => 'viewer',
                'display_name' => 'Viewer',
                'description' => 'Read-only access',
                'permissions' => [
                    ['resource_type' => '*', 'resource_pattern' => '*', 'action' => 'view'],
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::create([
                'name' => $roleData['name'],
                'display_name' => $roleData['display_name'],
                'description' => $roleData['description'],
            ]);

            foreach ($roleData['permissions'] as $permission) {
                RolePermission::create([
                    'role_id' => $role->id,
                    'resource_type' => $permission['resource_type'],
                    'resource_pattern' => $permission['resource_pattern'],
                    'action' => $permission['action'],
                    'effect' => 'allow',
                ]);
            }
        }
    }
}
```

## Best Practices

1. **Role Granularity**: Create roles based on job functions, not individuals
2. **Minimal Roles**: Start with few roles, add more only when needed
3. **Clear Naming**: Use descriptive role names (editor, manager, viewer)
4. **Document Roles**: Maintain documentation of what each role can do
5. **Audit Trail**: Log role assignments and permission changes
6. **Regular Review**: Periodically review and clean up unused roles
7. **Avoid Role Explosion**: Don't create too many similar roles
8. **Separation of Duties**: Ensure sensitive operations require multiple roles

## When to Use RBAC

✅ **Good for:**
- Enterprise applications
- Systems with clear job functions
- Organizations with departments/teams
- Applications with 10+ users
- Systems requiring centralized permission management
- Multi-tenant applications with standard user types

❌ **Avoid for:**
- Very small applications (use ACL)
- Highly dynamic permissions (use ABAC)
- Complex attribute-based logic (use ABAC)
- Single-user or few-user systems

## Role Inheritance (Advanced)

```php
class HierarchicalRbacRepository implements PolicyRepositoryInterface
{
    private array $roleHierarchy = [
        'admin' => ['manager', 'editor', 'viewer'],
        'manager' => ['editor', 'viewer'],
        'editor' => ['viewer'],
    ];

    public function find(): Policy
    {
        $rules = [];

        // Get base role permissions
        $rolePermissions = DB::table('role_permissions')
            ->join('roles', 'role_permissions.role_id', '=', 'roles.id')
            ->get();

        foreach ($rolePermissions as $permission) {
            // Add permission for the role itself
            $rules[] = new PolicyRule(
                subject: "role:{$permission->role_name}",
                resource: "{$permission->resource_type}:{$permission->resource_pattern}",
                action: $permission->action,
                effect: Effect::from($permission->effect)
            );

            // Add inherited permissions
            foreach ($this->roleHierarchy as $parent => $children) {
                if (in_array($permission->role_name, $children)) {
                    $rules[] = new PolicyRule(
                        subject: "role:{$parent}",
                        resource: "{$permission->resource_type}:{$permission->resource_pattern}",
                        action: $permission->action,
                        effect: Effect::from($permission->effect)
                    );
                }
            }
        }

        return new Policy($rules);
    }
}
```

## Related Models

- [ACL](./acl.md) - Direct user permissions
- [RBAC with Resource Roles](./rbac-resource-roles.md) - Resources have roles too
- [RBAC with Domains](./rbac-domains.md) - Multi-tenant role sets
- [ABAC](./abac.md) - Attribute-based extension




---

<div style="page-break-before: always;"></div>

# RBAC with Resource Roles

Both users and resources have roles for sophisticated role-based authorization.

## Overview

RBAC with Resource Roles extends traditional RBAC by assigning roles to both users AND resources. This enables sophisticated scenarios where user roles interact with resource roles to determine access. For example, a "member" user role might have different permissions on "public" vs "private" resources.

## Basic Concept

```
User Role + Resource Role + Action = Permission
(subject.role, resource.role, action) → allow/deny
```

## Use Cases

- Document classification systems (public/private/confidential documents)
- Multi-level security clearances (user clearance level vs document classification)
- Content visibility levels (subscribers see premium content)
- Organizational hierarchies (managers access department-specific resources)
- Medical records systems (doctor role + patient sensitivity level)
- Military/government systems (clearance-based access)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, RbacRuleMatcher, EffectResolver};

$policy = new Policy([
    // Managers can read department resources
    new PolicyRule(
        subject: 'role:manager',
        resource: 'resource-role:department',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers can edit department resources
    new PolicyRule(
        subject: 'role:manager',
        resource: 'resource-role:department',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Employees can only read department resources
    new PolicyRule(
        subject: 'role:employee',
        resource: 'resource-role:department',
        action: 'read',
        effect: Effect::Allow
    ),

    // Only admins can access confidential resources
    new PolicyRule(
        subject: 'role:admin',
        resource: 'resource-role:confidential',
        action: '*',
        effect: Effect::Allow
    ),

    // Everyone can read public resources
    new PolicyRule(
        subject: 'role:*',
        resource: 'resource-role:public',
        action: 'read',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

// Manager accessing department resource
$subject = new Subject('user-1', ['roles' => ['manager']]);
$resource = new Resource('doc-123', 'document', ['role' => 'department']);
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow

// Employee accessing confidential resource
$subject = new Subject('user-2', ['roles' => ['employee']]);
$resource = new Resource('doc-456', 'document', ['role' => 'confidential']);
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Deny
```

## Patterns

### Security Clearance System

```php
// Top Secret clearance can access all levels
new PolicyRule(
    subject: 'clearance:top-secret',
    resource: 'classification:*',
    action: 'read',
    effect: Effect::Allow
);

// Secret clearance can access secret and below
new PolicyRule(
    subject: 'clearance:secret',
    resource: 'classification:secret',
    action: 'read',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'clearance:secret',
    resource: 'classification:confidential',
    action: 'read',
    effect: Effect::Allow
);

// Confidential clearance only
new PolicyRule(
    subject: 'clearance:confidential',
    resource: 'classification:confidential',
    action: 'read',
    effect: Effect::Allow
);
```

### Content Subscription Tiers

```php
// Premium subscribers access all content
new PolicyRule(
    subject: 'subscription:premium',
    resource: 'content-tier:*',
    action: 'view',
    effect: Effect::Allow
);

// Basic subscribers access free and basic content
new PolicyRule(
    subject: 'subscription:basic',
    resource: 'content-tier:free',
    action: 'view',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'subscription:basic',
    resource: 'content-tier:basic',
    action: 'view',
    effect: Effect::Allow
);

// Free users only access free content
new PolicyRule(
    subject: 'subscription:free',
    resource: 'content-tier:free',
    action: 'view',
    effect: Effect::Allow
);
```

### Healthcare Record Access

```php
// Doctors can access general and moderate sensitivity
new PolicyRule(
    subject: 'role:doctor',
    resource: 'sensitivity:general',
    action: 'read',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'role:doctor',
    resource: 'sensitivity:moderate',
    action: 'read',
    effect: Effect::Allow
);

// Only psychiatrists can access mental health records
new PolicyRule(
    subject: 'role:psychiatrist',
    resource: 'sensitivity:mental-health',
    action: 'read',
    effect: Effect::Allow
);

// Nurses can only access general records
new PolicyRule(
    subject: 'role:nurse',
    resource: 'sensitivity:general',
    action: 'read',
    effect: Effect::Allow
);
```

## Laravel Integration

### Resource Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Resource;

Patrol::resolveResource(function ($resource) {
    if ($resource instanceof \App\Models\Document) {
        return new Resource(
            $resource->id,
            'document',
            [
                'role' => $resource->classification, // public, department, confidential
                'department_id' => $resource->department_id,
                'owner_id' => $resource->owner_id,
            ]
        );
    }

    if ($resource instanceof \App\Models\Article) {
        return new Resource(
            $resource->id,
            'article',
            [
                'role' => $resource->tier, // free, basic, premium
                'published' => $resource->is_published,
            ]
        );
    }

    return new Resource($resource->id, get_class($resource));
});
```

### Subject Resolver

```php
Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', ['roles' => ['guest'], 'clearance' => 'none']);
    }

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
        'clearance' => $user->clearance_level, // confidential, secret, top-secret
        'department_id' => $user->department_id,
    ]);
});
```

### Middleware

```php
// routes/web.php

// Route requires matching user and resource roles
Route::middleware(['patrol:document:read'])->get('/documents/{document}', function (Document $document) {
    return view('documents.show', compact('document'));
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class DocumentController extends Controller
{
    public function show(Document $document)
    {
        // Patrol automatically checks user role against document classification
        if (!Patrol::check($document, 'read')) {
            abort(403, 'You do not have clearance to view this document');
        }

        return view('documents.show', compact('document'));
    }

    public function index()
    {
        // Filter documents based on user clearance
        $documents = Document::all();
        $accessible = Patrol::filter($documents, 'read');

        return view('documents.index', compact('accessible'));
    }
}

class ArticleController extends Controller
{
    public function show(Article $article)
    {
        // Check user subscription tier against content tier
        if (!Patrol::check($article, 'view')) {
            return redirect()->route('pricing')
                ->with('error', 'This content requires a premium subscription');
        }

        return view('articles.show', compact('article'));
    }
}
```

## Real-World Example: Enterprise Document Management

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Public documents - everyone can read
    new PolicyRule('role:*', 'classification:public', 'read', Effect::Allow),

    // Internal documents - employees and above
    new PolicyRule('role:employee', 'classification:internal', 'read', Effect::Allow),
    new PolicyRule('role:manager', 'classification:internal', '*', Effect::Allow),

    // Confidential - managers and executives
    new PolicyRule('role:manager', 'classification:confidential', 'read', Effect::Allow),
    new PolicyRule('role:executive', 'classification:confidential', '*', Effect::Allow),

    // Top Secret - executives only
    new PolicyRule('role:executive', 'classification:top-secret', '*', Effect::Allow),

    // Department-specific access
    new PolicyRule(
        subject: 'department:sales',
        resource: 'department:sales',
        action: 'read',
        effect: Effect::Allow
    ),

    new PolicyRule(
        subject: 'department:engineering',
        resource: 'department:engineering',
        action: 'read',
        effect: Effect::Allow
    ),

    // Cross-department for managers
    new PolicyRule('role:manager', 'department:*', 'read', Effect::Allow),

    // Admins can access everything
    new PolicyRule('role:admin', 'classification:*', '*', Effect::Allow),
]);
```

## Database Storage

```php
// Migrations
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->enum('classification', ['public', 'internal', 'confidential', 'top-secret']);
    $table->foreignId('department_id')->nullable()->constrained();
    $table->foreignId('owner_id')->constrained('users');
    $table->timestamps();
});

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->enum('clearance_level', ['none', 'confidential', 'secret', 'top-secret']);
    $table->foreignId('department_id')->nullable()->constrained();
    $table->timestamps();
});

Schema::create('role_resource_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('subject_role'); // user role: manager, employee
    $table->string('resource_role'); // resource classification: public, confidential
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamps();

    $table->unique(['subject_role', 'resource_role', 'action'], 'role_resource_permission_unique');
});

// Models
class Document extends Model
{
    protected $fillable = ['title', 'content', 'classification', 'department_id', 'owner_id'];

    public function getResourceRole(): string
    {
        return $this->classification;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

class User extends Authenticatable
{
    public function hasAccessToClassification(string $classification): bool
    {
        $clearanceHierarchy = [
            'top-secret' => ['top-secret', 'confidential', 'internal', 'public'],
            'secret' => ['confidential', 'internal', 'public'],
            'confidential' => ['confidential', 'internal', 'public'],
            'none' => ['public'],
        ];

        return in_array($classification, $clearanceHierarchy[$this->clearance_level] ?? []);
    }
}

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class ResourceRoleRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('role_resource_permissions')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject_role,
                resource: "classification:{$row->resource_role}",
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows users with matching clearance to access classified documents', function () {
    $policy = new Policy([
        new PolicyRule('clearance:secret', 'classification:confidential', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['clearance' => 'secret']);
    $resource = new Resource('doc-1', 'document', ['role' => 'confidential']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Allow);
});

it('denies users without sufficient clearance', function () {
    $policy = new Policy([
        new PolicyRule('clearance:top-secret', 'classification:top-secret', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['clearance' => 'confidential']);
    $resource = new Resource('doc-1', 'document', ['role' => 'top-secret']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Deny);
});

it('supports subscription tier based content access', function () {
    $policy = new Policy([
        new PolicyRule('subscription:premium', 'content-tier:premium', 'view', Effect::Allow),
        new PolicyRule('subscription:basic', 'content-tier:premium', 'view', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // Premium user can access
    $subject = new Subject('user-1', ['subscription' => 'premium']);
    $resource = new Resource('article-1', 'article', ['role' => 'premium']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('view'));
    expect($result)->toBe(Effect::Allow);

    // Basic user cannot
    $subject = new Subject('user-2', ['subscription' => 'basic']);
    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('view'));
    expect($result)->toBe(Effect::Deny);
});
```

## Clearance Hierarchy Helper

```php
class ClearanceService
{
    private array $hierarchy = [
        'top-secret' => 4,
        'secret' => 3,
        'confidential' => 2,
        'internal' => 1,
        'public' => 0,
    ];

    public function canAccess(string $userClearance, string $resourceClassification): bool
    {
        $userLevel = $this->hierarchy[$userClearance] ?? -1;
        $resourceLevel = $this->hierarchy[$resourceClassification] ?? 999;

        return $userLevel >= $resourceLevel;
    }

    public function getAccessibleClassifications(string $userClearance): array
    {
        $userLevel = $this->hierarchy[$userClearance] ?? -1;

        return array_keys(array_filter(
            $this->hierarchy,
            fn($level) => $userLevel >= $level
        ));
    }
}

// Usage in repository
class HierarchicalClearanceRepository implements PolicyRepositoryInterface
{
    public function __construct(private ClearanceService $clearanceService)
    {
    }

    public function find(): Policy
    {
        $rules = [];

        foreach ($this->hierarchy as $clearance => $level) {
            $accessible = $this->clearanceService->getAccessibleClassifications($clearance);

            foreach ($accessible as $classification) {
                $rules[] = new PolicyRule(
                    subject: "clearance:{$clearance}",
                    resource: "classification:{$classification}",
                    action: 'read',
                    effect: Effect::Allow
                );
            }
        }

        return new Policy($rules);
    }
}
```

## Best Practices

1. **Clear Hierarchies**: Document role hierarchies for both users and resources
2. **Consistent Naming**: Use consistent role names across user and resource roles
3. **Audit Trail**: Log access to sensitive resource roles
4. **Regular Reviews**: Periodically audit user clearances and resource classifications
5. **Least Privilege**: Default to most restrictive resource role
6. **Classification Guidelines**: Provide clear guidelines for classifying resources
7. **Automatic Downgrade**: Consider automatic declassification over time
8. **Access Reports**: Generate reports showing who can access what classifications

## When to Use RBAC with Resource Roles

✅ **Good for:**
- Security clearance systems
- Document classification systems
- Multi-level content tiers
- Healthcare/medical records
- Financial data with sensitivity levels
- Government/military applications
- Subscription-based content platforms

❌ **Avoid for:**
- Simple permission needs (use basic RBAC or ACL)
- Systems without resource classification
- Highly dynamic permissions (use ABAC)
- Small-scale applications

## Subscription Tier Example

```php
class SubscriptionController extends Controller
{
    public function upgrade(User $user, string $tier)
    {
        $user->update(['subscription_tier' => $tier]);

        // Clear policy cache to reflect new permissions
        Cache::tags(['patrol', "user:{$user->id}"])->flush();

        return redirect()->back()->with('success', "Upgraded to {$tier} tier");
    }
}

// In your resource resolver
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof Article) {
        $tier = match($resource->is_premium) {
            true => 'premium',
            false => $resource->is_paid ? 'basic' : 'free',
        };

        return new Resource($resource->id, 'article', ['role' => $tier]);
    }

    return new Resource($resource->id, get_class($resource));
});
```

## Related Models

- [RBAC](./rbac.md) - Basic role-based access
- [ABAC](./abac.md) - Attribute-based alternative
- [RBAC with Domains](./rbac-domains.md) - Multi-tenant roles
- [Priority-Based](./priority-based.md) - Override clearance in emergencies




---

<div style="page-break-before: always;"></div>

# RBAC with Domains

Multi-tenant role sets where roles are scoped to specific domains or tenants.

## Overview

RBAC with Domains (also called Multi-Tenant RBAC) extends traditional RBAC by adding a domain/tenant dimension. Users can have different roles in different domains, enabling sophisticated multi-tenant authorization where a user might be an admin in one organization but a viewer in another.

## Basic Concept

```
User + Domain + Role → Permissions
(subject, domain, role, resource, action) → allow/deny
```

## Use Cases

- Multi-tenant SaaS platforms (different permissions per organization)
- Workspace-based applications (Slack, Trello, Asana)
- Multi-organization systems (user belongs to multiple companies)
- Team-based collaboration tools
- Multi-project environments (different role per project)
- Educational platforms (different roles per class/school)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy, Domain};
use Patrol\Core\Engine\{PolicyEvaluator, RbacRuleMatcher, EffectResolver};

$policy = new Policy([
    // User is admin in organization-1
    new PolicyRule(
        subject: 'user-1',
        resource: 'org:organization-1:*',
        action: '*',
        effect: Effect::Allow,
        domain: new Domain('organization-1')
    ),

    // User is viewer in organization-2
    new PolicyRule(
        subject: 'user-1',
        resource: 'org:organization-2:*',
        action: 'read',
        effect: Effect::Allow,
        domain: new Domain('organization-2')
    ),

    // User is member in organization-3
    new PolicyRule(
        subject: 'user-1',
        resource: 'org:organization-3:project:*',
        action: 'read',
        effect: Effect::Allow,
        domain: new Domain('organization-3')
    ),
    new PolicyRule(
        subject: 'user-1',
        resource: 'org:organization-3:project:*',
        action: 'create',
        effect: Effect::Allow,
        domain: new Domain('organization-3')
    ),
]);

$evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

// Admin in organization-1 can delete
$result = $evaluator->evaluate(
    $policy,
    new Subject('user-1', ['domain' => 'organization-1']),
    new Resource('project-1', 'project', ['domain' => 'organization-1']),
    new Action('delete')
);
// => Effect::Allow

// Viewer in organization-2 cannot delete
$result = $evaluator->evaluate(
    $policy,
    new Subject('user-1', ['domain' => 'organization-2']),
    new Resource('project-1', 'project', ['domain' => 'organization-2']),
    new Action('delete')
);
// => Effect::Deny
```

## Patterns

### Organization-Scoped Roles

```php
// Admin in org-1
new PolicyRule(
    subject: 'role:admin',
    resource: 'project:*',
    action: '*',
    effect: Effect::Allow,
    domain: new Domain('org-1')
);

// Member in org-1
new PolicyRule(
    subject: 'role:member',
    resource: 'project:*',
    action: 'read',
    effect: Effect::Allow,
    domain: new Domain('org-1')
);

// Same role names, different organizations
new PolicyRule(
    subject: 'role:admin',
    resource: 'project:*',
    action: '*',
    effect: Effect::Allow,
    domain: new Domain('org-2')
);
```

### Workspace-Based Permissions

```php
// Owner of workspace-1
new PolicyRule(
    subject: 'role:owner',
    resource: 'workspace:workspace-1:*',
    action: '*',
    effect: Effect::Allow,
    domain: new Domain('workspace-1')
);

// Editor in workspace-2
new PolicyRule(
    subject: 'role:editor',
    resource: 'workspace:workspace-2:*',
    action: 'edit',
    effect: Effect::Allow,
    domain: new Domain('workspace-2')
);

// Guest in workspace-3 (read-only)
new PolicyRule(
    subject: 'role:guest',
    resource: 'workspace:workspace-3:*',
    action: 'read',
    effect: Effect::Allow,
    domain: new Domain('workspace-3')
);
```

### Cross-Domain Access

```php
// Platform admin can access all domains
new PolicyRule(
    subject: 'platform-admin',
    resource: '*',
    action: '*',
    effect: Effect::Allow,
    domain: new Domain('*') // All domains
);

// Support staff can read across domains
new PolicyRule(
    subject: 'role:support',
    resource: '*',
    action: 'read',
    effect: Effect::Allow,
    domain: new Domain('*')
);
```

## Laravel Integration

### Domain-Aware Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest');
    }

    // Get current domain from session, subdomain, or request
    $currentDomain = session('current_organization_id')
        ?? request()->route('organization')
        ?? tenant('id');

    // Get user's role in current domain
    $membership = $user->memberships()
        ->where('organization_id', $currentDomain)
        ->first();

    return new Subject($user->id, [
        'domain' => $currentDomain,
        'roles' => $membership ? [$membership->role] : [],
        'organization_id' => $currentDomain,
        'all_domains' => $user->memberships->pluck('organization_id')->all(),
    ]);
});
```

### Resource Resolver

```php
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof \App\Models\Project) {
        return new Resource(
            $resource->id,
            'project',
            [
                'domain' => $resource->organization_id,
                'organization_id' => $resource->organization_id,
            ]
        );
    }

    if ($resource instanceof \App\Models\Organization) {
        return new Resource(
            $resource->id,
            'organization',
            [
                'domain' => $resource->id,
            ]
        );
    }

    return new Resource($resource->id, get_class($resource));
});
```

### Middleware

```php
// routes/web.php

// Organization-scoped routes
Route::prefix('organizations/{organization}')->group(function () {
    // Set domain context
    Route::middleware(['set-domain:organization'])->group(function () {

        // Only admins in this org can manage settings
        Route::middleware(['patrol:settings:manage'])->group(function () {
            Route::get('/settings', [SettingsController::class, 'edit']);
            Route::put('/settings', [SettingsController::class, 'update']);
        });

        // Members can view projects
        Route::middleware(['patrol:project:read'])->group(function () {
            Route::get('/projects', [ProjectController::class, 'index']);
        });
    });
});

// Middleware to set domain context
class SetDomainMiddleware
{
    public function handle(Request $request, Closure $next, string $param)
    {
        $domainId = $request->route($param);
        session(['current_organization_id' => $domainId]);

        return $next($request);
    }
}
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ProjectController extends Controller
{
    public function index(Organization $organization)
    {
        // Domain is set via middleware
        $projects = $organization->projects;

        // Filter based on user's role in this organization
        $accessible = Patrol::filter($projects, 'read');

        return view('projects.index', compact('organization', 'accessible'));
    }

    public function store(Request $request, Organization $organization)
    {
        // Check if user can create projects in this organization
        Patrol::authorize(
            new Resource($organization->id, 'organization'),
            'create-project'
        );

        $project = $organization->projects()->create($request->validated());

        return redirect()->route('projects.show', [$organization, $project]);
    }

    public function destroy(Organization $organization, Project $project)
    {
        // Only admins in this org can delete projects
        if (!Patrol::check($project, 'delete')) {
            abort(403, 'Only organization administrators can delete projects');
        }

        $project->delete();

        return redirect()->route('projects.index', $organization);
    }
}
```

## Real-World Example: Team Collaboration Platform

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy, Domain};

$policy = new Policy([
    // Workspace owner permissions
    new PolicyRule(
        subject: 'role:owner',
        resource: 'workspace:*',
        action: '*',
        effect: Effect::Allow,
        domain: new Domain('*') // In any workspace they own
    ),

    // Admin permissions per workspace
    new PolicyRule(
        subject: 'role:admin',
        resource: 'board:*',
        action: '*',
        effect: Effect::Allow
        // Domain is implicitly the current workspace
    ),
    new PolicyRule(
        subject: 'role:admin',
        resource: 'member:*',
        action: 'manage',
        effect: Effect::Allow
    ),

    // Member permissions
    new PolicyRule(
        subject: 'role:member',
        resource: 'board:*',
        action: 'read',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:member',
        resource: 'board:*',
        action: 'create',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:member',
        resource: 'card:*',
        action: '*',
        effect: Effect::Allow
    ),

    // Guest permissions (read-only)
    new PolicyRule(
        subject: 'role:guest',
        resource: 'board:*',
        action: 'read',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:guest',
        resource: 'card:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Platform-wide support access
    new PolicyRule(
        subject: 'platform-admin',
        resource: '*',
        action: '*',
        effect: Effect::Allow,
        domain: new Domain('*')
    ),
]);
```

## Database Storage

```php
// Migrations
Schema::create('organizations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});

Schema::create('organization_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role'); // owner, admin, member, guest
    $table->timestamps();

    $table->unique(['organization_id', 'user_id']);
});

Schema::create('domain_role_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('domain_type')->default('organization'); // organization, workspace, project
    $table->string('role_name'); // owner, admin, member
    $table->string('resource_type');
    $table->string('resource_pattern')->default('*');
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamps();

    $table->unique(['domain_type', 'role_name', 'resource_type', 'action'], 'domain_role_permission_unique');
});

// Models
class Organization extends Model
{
    protected $fillable = ['name', 'slug'];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}

class User extends Authenticatable
{
    public function organizations()
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function roleIn(Organization $organization): ?string
    {
        return $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first()
            ?->pivot
            ?->role;
    }

    public function isAdminIn(Organization $organization): bool
    {
        return in_array($this->roleIn($organization), ['owner', 'admin']);
    }
}

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class DomainRbacRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('domain_role_permissions')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: "role:{$row->role_name}",
                resource: "{$row->resource_type}:{$row->resource_pattern}",
                action: $row->action,
                effect: Effect::from($row->effect),
                domain: new Domain($row->domain_type)
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows users with admin role in specific domain', function () {
    $policy = new Policy([
        new PolicyRule(
            subject: 'role:admin',
            resource: 'project:*',
            action: 'delete',
            effect: Effect::Allow,
            domain: new Domain('org-1')
        ),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // User is admin in org-1
    $subject = new Subject('user-1', ['roles' => ['admin'], 'domain' => 'org-1']);
    $resource = new Resource('project-1', 'project', ['domain' => 'org-1']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('delete'));

    expect($result)->toBe(Effect::Allow);
});

it('denies users with same role in different domain', function () {
    $policy = new Policy([
        new PolicyRule(
            subject: 'role:admin',
            resource: 'project:*',
            action: 'delete',
            effect: Effect::Allow,
            domain: new Domain('org-1')
        ),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // User is admin in org-2, trying to access org-1 resource
    $subject = new Subject('user-1', ['roles' => ['admin'], 'domain' => 'org-2']);
    $resource = new Resource('project-1', 'project', ['domain' => 'org-1']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('delete'));

    expect($result)->toBe(Effect::Deny);
});

it('supports cross-domain platform admins', function () {
    $policy = new Policy([
        new PolicyRule(
            subject: 'platform-admin',
            resource: '*',
            action: '*',
            effect: Effect::Allow,
            domain: new Domain('*')
        ),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // Platform admin can access any domain
    $subject = new Subject('platform-admin', ['is_platform_admin' => true]);
    $resource = new Resource('project-1', 'project', ['domain' => 'org-1']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('delete'));

    expect($result)->toBe(Effect::Allow);
});
```

## Domain Switching

```php
class OrganizationController extends Controller
{
    public function switch(Organization $organization)
    {
        // Check if user has membership
        if (!auth()->user()->organizations->contains($organization)) {
            abort(403, 'You are not a member of this organization');
        }

        // Switch domain context
        session(['current_organization_id' => $organization->id]);

        // Clear cached permissions for this user
        Cache::tags(['patrol', 'user:' . auth()->id()])->flush();

        return redirect()->route('dashboard', $organization);
    }
}
```

## Best Practices

1. **Clear Domain Context**: Always make domain context explicit in UI and API
2. **Prevent Cross-Domain Leakage**: Ensure resources can't be accessed across domains
3. **Domain Isolation**: Strictly isolate data between domains
4. **Role Consistency**: Use consistent role names across domains
5. **Default Domain**: Set sensible default domain for users
6. **Audit Trails**: Log domain switches and cross-domain access
7. **Domain Indicator**: Show current domain prominently in UI
8. **Permission Cache**: Cache permissions per user per domain

## Security Considerations

### Domain Verification

```php
class DomainSecurityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $requestedDomain = $request->route('organization');
        $currentDomain = session('current_organization_id');

        // Verify user has access to requested domain
        if ($requestedDomain && $requestedDomain != $currentDomain) {
            if (!auth()->user()->organizations->contains('id', $requestedDomain)) {
                abort(403, 'Access denied to this organization');
            }

            // Auto-switch domain if user has access
            session(['current_organization_id' => $requestedDomain]);
        }

        return $next($request);
    }
}
```

## When to Use RBAC with Domains

✅ **Good for:**
- Multi-tenant SaaS platforms
- Workspace/team-based applications
- Users belonging to multiple organizations
- Project-based access control
- Educational platforms with multiple schools/classes
- Enterprise systems with departments

❌ **Avoid for:**
- Single-tenant applications (use basic RBAC)
- Simple permission needs (use ACL)
- Extremely complex cross-domain logic (use ABAC)
- Systems without clear domain boundaries

## Multi-Domain User Interface

```php
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $currentOrg = Organization::find(session('current_organization_id'));

        // Get user's role in current organization
        $role = $user->roleIn($currentOrg);

        // Get accessible resources based on domain and role
        $projects = Patrol::filter($currentOrg->projects, 'read');

        // Get all organizations user belongs to for switcher
        $organizations = $user->organizations;

        return view('dashboard', compact('currentOrg', 'role', 'projects', 'organizations'));
    }
}
```

## Related Models

- [RBAC](./rbac.md) - Basic role-based access
- [ABAC](./abac.md) - Attribute-based for complex logic
- [ACL](./acl.md) - Per-resource permissions
- [RBAC with Resource Roles](./rbac-resource-roles.md) - Resource-level roles




---

<div style="page-break-before: always;"></div>

# ABAC (Attribute-Based Access Control)

Dynamic authorization using attribute expressions and conditions for fine-grained control.

## Overview

ABAC (Attribute-Based Access Control) evaluates attributes of subjects, resources, actions, and the environment to make authorization decisions. Instead of static rules, ABAC uses expressions and conditions that are evaluated at runtime, enabling highly dynamic and context-aware permissions.

## Basic Concept

```
Attributes + Conditions → Dynamic Permission
if (condition) then allow/deny
```

## Use Cases

- Time-based access (office hours only, temporary access)
- Location-based permissions (IP restrictions, geo-fencing)
- Ownership-based access (users edit their own content)
- Dynamic relationships (managers approve subordinate requests)
- Context-aware permissions (read if published, edit if draft)
- Attribute matching (same department, same team)
- Resource state conditions (locked, archived, published)

## Core Example

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AbacRuleMatcher, EffectResolver, AttributeResolver};

$policy = new Policy([
    // Users can edit their own articles
    new ConditionalPolicyRule(
        condition: 'resource.author_id == subject.id',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Managers can edit team member articles
    new ConditionalPolicyRule(
        condition: 'resource.author.manager_id == subject.id',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Users can only access published articles (unless they're the author)
    new ConditionalPolicyRule(
        condition: 'resource.status == "published" || resource.author_id == subject.id',
        resource: 'article:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Editors can edit any article during business hours
    new ConditionalPolicyRule(
        condition: 'subject.role == "editor" && environment.hour >= 9 && environment.hour <= 17',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),
]);

$attributeResolver = new AttributeResolver();
$evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

// User editing their own article
$subject = new Subject('user-1', ['id' => 1]);
$resource = new Resource('article-1', 'article', ['author_id' => 1]);
$action = new Action('edit');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow (condition matches: 1 == 1)
```

## Patterns

### Ownership-Based Access

```php
// Users own their resources
new ConditionalPolicyRule(
    condition: 'resource.owner_id == subject.id',
    resource: '*',
    action: '*',
    effect: Effect::Allow
);

// Team lead owns team resources
new ConditionalPolicyRule(
    condition: 'resource.team_id == subject.team_id && subject.role == "team_lead"',
    resource: 'project:*',
    action: 'manage',
    effect: Effect::Allow
);
```

### Time-Based Access

```php
// Access only during business hours
new ConditionalPolicyRule(
    condition: 'environment.hour >= 9 && environment.hour <= 17 && environment.weekday == true',
    resource: 'sensitive-data:*',
    action: 'access',
    effect: Effect::Allow
);

// Temporary access with expiration
new ConditionalPolicyRule(
    condition: 'environment.now <= resource.access_expires_at',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);
```

### Location-Based Access

```php
// Office network only
new ConditionalPolicyRule(
    condition: 'environment.ip_address startsWith "192.168.1."',
    resource: 'admin:*',
    action: '*',
    effect: Effect::Allow
);

// Geo-restricted content
new ConditionalPolicyRule(
    condition: 'environment.country in resource.allowed_countries',
    resource: 'content:*',
    action: 'view',
    effect: Effect::Allow
);
```

### Attribute Matching

```php
// Same department access
new ConditionalPolicyRule(
    condition: 'subject.department_id == resource.department_id',
    resource: 'report:*',
    action: 'read',
    effect: Effect::Allow
);

// Clearance level matching
new ConditionalPolicyRule(
    condition: 'subject.clearance_level >= resource.required_clearance',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver with Rich Attributes

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', [
            'id' => null,
            'role' => 'guest',
        ]);
    }

    return new Subject($user->id, [
        'id' => $user->id,
        'email' => $user->email,
        'role' => $user->role,
        'department_id' => $user->department_id,
        'team_id' => $user->team_id,
        'manager_id' => $user->manager_id,
        'clearance_level' => $user->clearance_level,
        'created_at' => $user->created_at,
    ]);
});
```

### Resource Resolver with Attributes

```php
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof \App\Models\Article) {
        return new Resource($resource->id, 'article', [
            'author_id' => $resource->author_id,
            'status' => $resource->status,
            'department_id' => $resource->department_id,
            'is_published' => $resource->is_published,
            'published_at' => $resource->published_at,
            'author' => [
                'id' => $resource->author->id,
                'manager_id' => $resource->author->manager_id,
            ],
        ]);
    }

    if ($resource instanceof \App\Models\Project) {
        return new Resource($resource->id, 'project', [
            'owner_id' => $resource->owner_id,
            'team_id' => $resource->team_id,
            'is_locked' => $resource->is_locked,
            'required_clearance' => $resource->required_clearance,
        ]);
    }

    return new Resource($resource->id, get_class($resource));
});
```

### Environment Attributes Provider

```php
use Patrol\Core\Contracts\AttributeProviderInterface;

class EnvironmentAttributeProvider implements AttributeProviderInterface
{
    public function getAttributes(): array
    {
        return [
            'now' => now(),
            'hour' => now()->hour,
            'weekday' => now()->isWeekday(),
            'ip_address' => request()->ip(),
            'country' => geoip(request()->ip())->country,
            'user_agent' => request()->userAgent(),
            'is_mobile' => request()->isMobile(),
        ];
    }
}

// Register in service provider
Patrol::addAttributeProvider(new EnvironmentAttributeProvider());
```

### Middleware

```php
// routes/web.php

// ABAC automatically evaluates conditions
Route::middleware(['patrol:article:edit'])->group(function () {
    Route::put('/articles/{article}', [ArticleController::class, 'update']);
});

// Conditional access
Route::middleware(['patrol:admin:access'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function edit(Article $article)
    {
        // ABAC checks ownership, status, etc.
        if (!Patrol::check($article, 'edit')) {
            abort(403, 'You can only edit your own articles');
        }

        return view('articles.edit', compact('article'));
    }

    public function publish(Article $article)
    {
        // Only editors or authors can publish
        Patrol::authorize($article, 'publish');

        $article->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('articles.show', $article);
    }
}

class DocumentController extends Controller
{
    public function download(Document $document)
    {
        // Check clearance level, location, time, etc.
        if (!Patrol::check($document, 'download')) {
            abort(403, 'Access denied based on security policy');
        }

        return response()->download($document->path);
    }
}
```

## Real-World Example: Dynamic Document System

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect, Policy};

$policy = new Policy([
    // Own documents - full access
    new ConditionalPolicyRule(
        condition: 'resource.owner_id == subject.id',
        resource: 'document:*',
        action: '*',
        effect: Effect::Allow
    ),

    // Department members - read if published
    new ConditionalPolicyRule(
        condition: 'resource.department_id == subject.department_id && resource.status == "published"',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers - read team documents
    new ConditionalPolicyRule(
        condition: 'resource.owner.manager_id == subject.id',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers - approve team documents
    new ConditionalPolicyRule(
        condition: 'resource.owner.manager_id == subject.id && resource.status == "pending"',
        resource: 'document:*',
        action: 'approve',
        effect: Effect::Allow
    ),

    // Sensitive docs - office network only
    new ConditionalPolicyRule(
        condition: 'resource.sensitivity == "high" && environment.ip_address startsWith "10.0."',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Clearance-based access
    new ConditionalPolicyRule(
        condition: 'subject.clearance_level >= resource.required_clearance',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Time-based restrictions on modifications
    new ConditionalPolicyRule(
        condition: 'environment.hour >= 9 && environment.hour <= 17',
        resource: 'document:*',
        action: 'delete',
        effect: Effect::Allow
    ),

    // Archive restrictions - no edits
    new ConditionalPolicyRule(
        condition: 'resource.is_archived == true',
        resource: 'document:*',
        action: 'edit',
        effect: Effect::Deny
    ),
]);
```

## Database Storage

```php
// Migration
Schema::create('abac_rules', function (Blueprint $table) {
    $table->id();
    $table->text('condition'); // Expression: "resource.owner_id == subject.id"
    $table->string('resource_type')->nullable();
    $table->string('resource_pattern')->default('*');
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class AbacRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('abac_rules')
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($row) => new ConditionalPolicyRule(
                condition: $row->condition,
                resource: $row->resource_type
                    ? "{$row->resource_type}:{$row->resource_pattern}"
                    : '*',
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Expression Language

Patrol's ABAC supports a powerful expression language:

### Comparison Operators
- `==` - Equality
- `!=` - Inequality
- `>`, `<`, `>=`, `<=` - Comparisons
- `in` - Membership
- `startsWith`, `endsWith`, `contains` - String operations

### Logical Operators
- `&&` - AND
- `||` - OR
- `!` - NOT

### Example Expressions

```php
// Simple equality
'subject.id == resource.owner_id'

// Complex conditions
'subject.role == "manager" && resource.department_id == subject.department_id'

// Array membership
'subject.id in resource.authorized_users'

// String operations
'environment.ip_address startsWith "192.168."'

// Nested attributes
'resource.author.department_id == subject.department_id'

// Multiple conditions
'(subject.role == "admin" || resource.owner_id == subject.id) && !resource.is_locked'
```

## Testing

```php
use Pest\Tests;

it('allows users to edit their own resources', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'resource.owner_id == subject.id',
            resource: 'article:*',
            action: 'edit',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $subject = new Subject('user-1', ['id' => 1]);
    $resource = new Resource('article-1', 'article', ['owner_id' => 1]);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('edit'));

    expect($result)->toBe(Effect::Allow);
});

it('denies access outside business hours', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'environment.hour >= 9 && environment.hour <= 17',
            resource: 'admin:*',
            action: 'access',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $attributeResolver->setEnvironmentAttributes(['hour' => 20]); // 8 PM

    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('admin-panel', 'admin'),
        new Action('access')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports attribute matching between subject and resource', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'subject.department_id == resource.department_id',
            resource: 'report:*',
            action: 'read',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $subject = new Subject('user-1', ['department_id' => 5]);
    $resource = new Resource('report-1', 'report', ['department_id' => 5]);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Allow);
});
```

## Advanced Patterns

### Delegation

```php
// Users can delegate access
new ConditionalPolicyRule(
    condition: 'subject.id in resource.delegated_to',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);

// Model implementation
class Document extends Model
{
    public function delegate(User $user): void
    {
        $delegations = $this->delegated_to ?? [];
        $delegations[] = $user->id;
        $this->update(['delegated_to' => array_unique($delegations)]);
    }
}
```

### Workflow States

```php
// Different permissions based on state
new ConditionalPolicyRule(
    condition: 'resource.status == "draft" && resource.author_id == subject.id',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Allow
);

new ConditionalPolicyRule(
    condition: 'resource.status == "under_review" && subject.role == "reviewer"',
    resource: 'article:*',
    action: 'review',
    effect: Effect::Allow
);

new ConditionalPolicyRule(
    condition: 'resource.status == "published"',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Deny
);
```

## Best Practices

1. **Keep Conditions Simple**: Complex expressions are hard to debug and maintain
2. **Document Expressions**: Comment complex conditions with their intent
3. **Test Thoroughly**: ABAC is dynamic - test edge cases extensively
4. **Performance**: Cache condition results when possible
5. **Attribute Consistency**: Ensure attributes are always available and typed correctly
6. **Audit Logging**: Log which conditions triggered allow/deny decisions
7. **Fallback Rules**: Always have default deny as fallback
8. **Attribute Validation**: Validate attribute existence before comparison

## When to Use ABAC

✅ **Good for:**
- Ownership-based permissions
- Time-sensitive access
- Location-based restrictions
- Dynamic relationships (manager-employee)
- Context-aware permissions
- Complex business logic
- State-based workflows

❌ **Avoid for:**
- Simple static permissions (use ACL or RBAC)
- Performance-critical paths (evaluate overhead)
- When attributes are unavailable
- Very simple permission models

## Performance Optimization

```php
// Cache attribute resolution
class CachedAttributeResolver extends AttributeResolver
{
    public function resolve(Subject $subject, Resource $resource): array
    {
        $cacheKey = "attributes:{$subject->id()}:{$resource->id()}";

        return Cache::remember($cacheKey, 300, function () use ($subject, $resource) {
            return parent::resolve($subject, $resource);
        });
    }
}

// Pre-filter with simple rules
$policy = new Policy([
    // Fast ACL check first
    new PolicyRule('role:admin', '*', '*', Effect::Allow),

    // Then ABAC for complex cases
    new ConditionalPolicyRule(
        condition: 'resource.owner_id == subject.id',
        resource: '*',
        action: '*',
        effect: Effect::Allow
    ),
]);
```

## Related Models

- [ACL](./acl.md) - Simple static permissions
- [RBAC](./rbac.md) - Role-based structure
- [Deny-Override](./deny-override.md) - Explicit denies with ABAC
- [Priority-Based](./priority-based.md) - Rule ordering with ABAC




---

<div style="page-break-before: always;"></div>

# RESTful Authorization

HTTP method and path-based authorization for API endpoints.

## Overview

RESTful authorization maps HTTP methods (GET, POST, PUT, DELETE) and URL paths to permissions. This model is specifically designed for REST APIs where authorization decisions are based on the HTTP verb and resource path rather than abstract actions.

## Basic Concept

```
HTTP Method + URL Path = Permission
(subject, method, path) → allow/deny
```

## Use Cases

- REST API authorization
- HTTP endpoint access control
- API gateway policies
- Microservice authorization
- Public API access control
- Webhook authorization
- GraphQL endpoint protection (via HTTP)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, RestfulRuleMatcher, EffectResolver};

$policy = new Policy([
    // Anyone can GET public endpoints
    new PolicyRule(
        subject: '*',
        resource: '/api/public/*',
        action: 'GET',
        effect: Effect::Allow
    ),

    // Authenticated users can GET protected endpoints
    new PolicyRule(
        subject: 'authenticated',
        resource: '/api/users/*',
        action: 'GET',
        effect: Effect::Allow
    ),

    // Only admins can POST/PUT/DELETE
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'POST',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'PUT',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'DELETE',
        effect: Effect::Allow
    ),

    // Users can modify their own resources
    new PolicyRule(
        subject: 'user-1',
        resource: '/api/users/1',
        action: 'PUT',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

// Check GET request
$result = $evaluator->evaluate(
    $policy,
    new Subject('user-1'),
    new Resource('/api/users/1', 'endpoint'),
    new Action('GET')
);
// => Effect::Allow
```

## Patterns

### Public vs Protected Endpoints

```php
// Public read-only endpoints
new PolicyRule('*', '/api/articles', 'GET', Effect::Allow);
new PolicyRule('*', '/api/articles/*', 'GET', Effect::Allow);
new PolicyRule('*', '/api/categories', 'GET', Effect::Allow);

// Protected write endpoints
new PolicyRule('authenticated', '/api/articles', 'POST', Effect::Allow);
new PolicyRule('authenticated', '/api/articles/*', 'PUT', Effect::Allow);

// Admin-only endpoints
new PolicyRule('role:admin', '/api/admin/*', '*', Effect::Allow);
```

### CRUD Permissions per Resource

```php
// Read permissions
new PolicyRule('role:viewer', '/api/projects', 'GET', Effect::Allow);
new PolicyRule('role:viewer', '/api/projects/*', 'GET', Effect::Allow);

// Create permissions
new PolicyRule('role:editor', '/api/projects', 'POST', Effect::Allow);

// Update permissions
new PolicyRule('role:editor', '/api/projects/*', 'PUT', Effect::Allow);
new PolicyRule('role:editor', '/api/projects/*', 'PATCH', Effect::Allow);

// Delete permissions
new PolicyRule('role:admin', '/api/projects/*', 'DELETE', Effect::Allow);
```

### Nested Resource Paths

```php
// Organization resources
new PolicyRule('role:member', '/api/orgs/*/projects', 'GET', Effect::Allow);
new PolicyRule('role:admin', '/api/orgs/*/projects', 'POST', Effect::Allow);

// Deeply nested
new PolicyRule('role:member', '/api/orgs/*/projects/*/tasks', 'GET', Effect::Allow);
new PolicyRule('role:member', '/api/orgs/*/projects/*/tasks/*', 'GET', Effect::Allow);
```

## Laravel Integration

### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', ['authenticated' => false]);
    }

    return new Subject($user->id, [
        'authenticated' => true,
        'roles' => $user->roles->pluck('name')->all(),
        'api_token' => $user->api_token,
    ]);
});
```

### Resource Resolver for HTTP Requests

```php
Patrol::resolveResource(function ($resource) {
    // For HTTP requests, use the path as resource
    if ($resource instanceof \Illuminate\Http\Request) {
        return new Resource(
            $resource->path(),
            'endpoint',
            [
                'method' => $resource->method(),
                'ip' => $resource->ip(),
                'authenticated' => auth()->check(),
            ]
        );
    }

    return new Resource($resource->id ?? $resource, get_class($resource));
});
```

### Middleware

```php
// Automatic REST authorization
class RestfulPatrolMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/' . $request->path();
        $method = $request->method();

        $resource = new Resource($path, 'endpoint');
        $action = new Action($method);

        if (!Patrol::check($resource, $action)) {
            abort(403, 'API access denied');
        }

        return $next($request);
    }
}

// Apply to API routes
Route::middleware(['api', 'restful-patrol'])->prefix('api')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
});
```

### Route-Based Authorization

```php
// routes/api.php

// Public endpoints
Route::get('/api/public/health', function () {
    return ['status' => 'ok'];
});

// Authenticated endpoints
Route::middleware(['patrol-rest'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    Route::get('/api/users/{id}', [UserController::class, 'show']);
});

// Admin endpoints
Route::middleware(['patrol-rest'])->prefix('api/admin')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
```

## Real-World Example: REST API

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // ============= Public Endpoints =============
    // Health check
    new PolicyRule('*', '/api/health', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/status', 'GET', Effect::Allow),

    // Public documentation
    new PolicyRule('*', '/api/docs', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/docs/*', 'GET', Effect::Allow),

    // Public articles (read-only)
    new PolicyRule('*', '/api/articles', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/articles/*', 'GET', Effect::Allow),

    // ============= Authenticated User Endpoints =============
    // User profile
    new PolicyRule('authenticated', '/api/user', 'GET', Effect::Allow),
    new PolicyRule('authenticated', '/api/user', 'PUT', Effect::Allow),

    // User's own resources
    new PolicyRule('authenticated', '/api/user/articles', 'GET', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles', 'POST', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles/*', 'PUT', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles/*', 'DELETE', Effect::Allow),

    // ============= Editor Endpoints =============
    new PolicyRule('role:editor', '/api/articles', 'POST', Effect::Allow),
    new PolicyRule('role:editor', '/api/articles/*', 'PUT', Effect::Allow),

    // ============= Admin Endpoints =============
    // User management
    new PolicyRule('role:admin', '/api/admin/users', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/users', 'POST', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/users/*', '*', Effect::Allow),

    // System settings
    new PolicyRule('role:admin', '/api/admin/settings', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/settings', 'PUT', Effect::Allow),

    // Analytics
    new PolicyRule('role:admin', '/api/admin/analytics/*', 'GET', Effect::Allow),

    // ============= Deny Overrides =============
    // Block dangerous operations in production
    new PolicyRule('*', '/api/admin/system/reset', 'POST', Effect::Deny),
]);
```

## Database Storage

```php
// Migration
Schema::create('api_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('subject'); // '*', 'authenticated', 'role:admin', 'user-123'
    $table->string('path_pattern'); // '/api/articles/*'
    $table->string('http_method'); // GET, POST, PUT, DELETE, *
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->integer('priority')->default(0);
    $table->timestamps();

    $table->index(['path_pattern', 'http_method']);
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class RestfulRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('api_permissions')
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject,
                resource: $row->path_pattern,
                action: $row->http_method,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## API Controller Example

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleApiController extends Controller
{
    public function index(Request $request)
    {
        // Check if user can GET /api/articles
        $resource = new Resource('/api/articles', 'endpoint');

        if (!Patrol::check($resource, new Action('GET'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $articles = Article::paginate();
        return response()->json($articles);
    }

    public function store(Request $request)
    {
        // Check if user can POST /api/articles
        $resource = new Resource('/api/articles', 'endpoint');

        if (!Patrol::check($resource, new Action('POST'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $article = Article::create($request->validated());
        return response()->json($article, 201);
    }

    public function update(Request $request, Article $article)
    {
        // Check if user can PUT /api/articles/{id}
        $path = "/api/articles/{$article->id}";
        $resource = new Resource($path, 'endpoint');

        if (!Patrol::check($resource, new Action('PUT'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $article->update($request->validated());
        return response()->json($article);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows public GET requests to public endpoints', function () {
    $policy = new Policy([
        new PolicyRule('*', '/api/public/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('guest'),
        new Resource('/api/public/articles', 'endpoint'),
        new Action('GET')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies POST requests to public endpoints', function () {
    $policy = new Policy([
        new PolicyRule('*', '/api/public/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('guest'),
        new Resource('/api/public/articles', 'endpoint'),
        new Action('POST')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports wildcard path matching', function () {
    $policy = new Policy([
        new PolicyRule('authenticated', '/api/users/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['authenticated' => true]);

    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('/api/users/123', 'endpoint'),
        new Action('GET')
    );

    expect($result)->toBe(Effect::Allow);
});
```

## Rate Limiting Integration

```php
use Illuminate\Support\Facades\RateLimiter;

class RateLimitedRestfulMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/' . $request->path();
        $method = $request->method();

        // Check authorization first
        $resource = new Resource($path, 'endpoint');
        $action = new Action($method);

        if (!Patrol::check($resource, $action)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Apply rate limiting based on endpoint
        $key = $this->getRateLimitKey($request, $path);
        $limit = $this->getRateLimitForPath($path);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['error' => 'Too Many Requests'], 429);
        }

        RateLimiter::hit($key);

        return $next($request);
    }

    private function getRateLimitForPath(string $path): int
    {
        return match (true) {
            str_starts_with($path, '/api/admin/') => 100,
            str_starts_with($path, '/api/user/') => 300,
            str_starts_with($path, '/api/public/') => 1000,
            default => 500,
        };
    }
}
```

## API Key Authorization

```php
// Add API key support
Patrol::resolveSubject(function () {
    // Check for API key
    $apiKey = request()->header('X-API-Key');

    if ($apiKey) {
        $apiToken = ApiToken::where('token', $apiKey)->first();

        if ($apiToken) {
            return new Subject("api-token:{$apiToken->id}", [
                'type' => 'api_token',
                'scopes' => $apiToken->scopes,
                'owner_id' => $apiToken->user_id,
            ]);
        }
    }

    // Fall back to session auth
    $user = auth()->user();

    return $user
        ? new Subject($user->id, ['type' => 'user'])
        : new Subject('guest', ['type' => 'guest']);
});

// Scope-based permissions
$policy = new Policy([
    // API token with 'read' scope
    new PolicyRule('scope:read', '/api/*', 'GET', Effect::Allow),

    // API token with 'write' scope
    new PolicyRule('scope:write', '/api/*', 'POST', Effect::Allow),
    new PolicyRule('scope:write', '/api/*', 'PUT', Effect::Allow),

    // API token with 'delete' scope
    new PolicyRule('scope:delete', '/api/*', 'DELETE', Effect::Allow),
]);
```

## Best Practices

1. **Path Consistency**: Use consistent URL patterns across your API
2. **Method Semantics**: Follow REST conventions (GET=read, POST=create, etc.)
3. **Wildcard Strategy**: Use wildcards judiciously for maintainability
4. **Version APIs**: Include version in path (/api/v1/*, /api/v2/*)
5. **Rate Limiting**: Combine with rate limiting for API protection
6. **Audit Logging**: Log all API access attempts
7. **Clear Errors**: Return informative 403 errors
8. **Documentation**: Document API permissions in your API docs

## When to Use RESTful Authorization

✅ **Good for:**
- REST API authorization
- Public API access control
- Microservice authorization
- HTTP-based services
- API gateways
- Webhook authorization

❌ **Avoid for:**
- Non-HTTP authorization (use ACL/RBAC)
- Complex business logic (use ABAC)
- Fine-grained resource permissions (combine with ACL)
- Non-REST APIs (use appropriate model)

## Versioned API Permissions

```php
// Different permissions per API version
$policy = new Policy([
    // v1 API - more restrictive
    new PolicyRule('role:user', '/api/v1/articles', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/v1/articles', 'POST', Effect::Allow),

    // v2 API - more permissive
    new PolicyRule('role:user', '/api/v2/articles', 'GET', Effect::Allow),
    new PolicyRule('role:user', '/api/v2/articles', 'POST', Effect::Allow),
    new PolicyRule('role:admin', '/api/v2/articles', '*', Effect::Allow),

    // Deprecated v1 admin endpoint
    new PolicyRule('*', '/api/v1/admin/legacy', '*', Effect::Deny),
]);
```

## Related Models

- [ACL](./acl.md) - Resource-specific permissions
- [RBAC](./rbac.md) - Role-based API access
- [ABAC](./abac.md) - Attribute-based API rules
- [Deny-Override](./deny-override.md) - Block dangerous endpoints




---

<div style="page-break-before: always;"></div>

# Deny-Override

Explicit deny rules that override all allow rules for security-critical access control.

## Overview

Deny-Override is a pattern where explicit DENY rules take precedence over ALLOW rules, regardless of order or specificity. This creates a security model where you can grant broad permissions while maintaining the ability to explicitly block specific actions, ensuring that critical restrictions cannot be bypassed.

## Basic Concept

```
DENY always wins
Allow + Deny = Deny
```

## Use Cases

- Security-critical operations (prevent accidental admin actions)
- Compliance requirements (enforce regulatory restrictions)
- Temporary access suspension (suspended users)
- Emergency lockdowns (disable all write operations)
- Sensitive resource protection (block access to classified data)
- Audit requirements (block operations during audit periods)
- IP blacklisting (block specific addresses)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, DenyOverrideEffectResolver};

$policy = new Policy([
    // Grant admin full access
    new PolicyRule(
        subject: 'role:admin',
        resource: '*',
        action: '*',
        effect: Effect::Allow
    ),

    // But explicitly deny deletion of critical resources
    new PolicyRule(
        subject: '*',
        resource: 'system:critical:*',
        action: 'delete',
        effect: Effect::Deny
    ),

    // Grant user access to documents
    new PolicyRule(
        subject: 'user-1',
        resource: 'document:*',
        action: '*',
        effect: Effect::Allow
    ),

    // But deny if user is suspended
    new PolicyRule(
        subject: 'status:suspended',
        resource: '*',
        action: '*',
        effect: Effect::Deny
    ),
]);

// Use DenyOverrideEffectResolver instead of standard EffectResolver
$evaluator = new PolicyEvaluator(
    new AclRuleMatcher(),
    new DenyOverrideEffectResolver() // Deny always wins
);

// Even admin cannot delete critical resources
$result = $evaluator->evaluate(
    $policy,
    new Subject('admin-1', ['roles' => ['admin']]),
    new Resource('system-critical-1', 'system', ['critical' => true]),
    new Action('delete')
);
// => Effect::Deny (explicit deny overrides admin allow)
```

## Patterns

### Security Overrides

```php
// Allow broad access
new PolicyRule('role:admin', '*', '*', Effect::Allow);

// But deny dangerous operations
new PolicyRule('*', 'database:*', 'drop', Effect::Deny);
new PolicyRule('*', 'user:*', 'permanent-delete', Effect::Deny);
new PolicyRule('*', 'system:*', 'reset', Effect::Deny);
```

### User Status Overrides

```php
// Normal permissions
new PolicyRule('role:user', 'article:*', 'read', Effect::Allow);
new PolicyRule('role:user', 'article:*', 'create', Effect::Allow);

// But suspended users are blocked
new PolicyRule('status:suspended', '*', '*', Effect::Deny);

// Banned users completely blocked
new PolicyRule('status:banned', '*', '*', Effect::Deny);
```

### Time-Based Restrictions

```php
// Allow normal operations
new PolicyRule('role:editor', 'content:*', 'edit', Effect::Allow);

// But deny during maintenance window
new PolicyRule('*', '*', 'edit', Effect::Deny, [
    'condition' => 'environment.maintenance_mode == true'
]);

// Deny write operations during audit
new PolicyRule('*', '*', 'write', Effect::Deny, [
    'condition' => 'environment.audit_in_progress == true'
]);
```

### Location-Based Denies

```php
// Allow access from office
new PolicyRule('role:employee', 'system:*', '*', Effect::Allow);

// But deny from blacklisted IPs
new PolicyRule('*', '*', '*', Effect::Deny, [
    'condition' => 'environment.ip in blacklist'
]);

// Deny access from restricted countries
new PolicyRule('*', 'sensitive:*', '*', Effect::Deny, [
    'condition' => 'environment.country in ["XX", "YY"]'
]);
```

## Laravel Integration

### Subject Resolver with Status

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest');
    }

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
        'status' => $user->status, // active, suspended, banned
        'is_suspended' => $user->status === 'suspended',
        'is_banned' => $user->status === 'banned',
    ]);
});
```

### Custom Effect Resolver

```php
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\ValueObjects\Effect;

class DenyOverrideEffectResolver extends EffectResolver
{
    public function resolve(array $effects): Effect
    {
        // If any deny exists, return deny
        if (in_array(Effect::Deny, $effects)) {
            return Effect::Deny;
        }

        // Otherwise check for allow
        if (in_array(Effect::Allow, $effects)) {
            return Effect::Allow;
        }

        // Default deny
        return Effect::Deny;
    }
}

// Register in service provider
app()->bind(EffectResolver::class, DenyOverrideEffectResolver::class);
```

### Middleware

```php
class MaintenanceDenyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Check if in maintenance mode
        if (app()->isDownForMaintenance() && !$this->isWhitelisted($request)) {
            abort(403, 'System is in maintenance mode');
        }

        return $next($request);
    }

    private function isWhitelisted(Request $request): bool
    {
        // Admins can access during maintenance
        return auth()->user()?->hasRole('super-admin');
    }
}
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class DocumentController extends Controller
{
    public function destroy(Document $document)
    {
        // Check with deny-override logic
        if (!Patrol::check($document, 'delete')) {
            // Check if it was denied due to critical status
            if ($document->is_critical) {
                abort(403, 'Critical documents cannot be deleted');
            }

            // Check if user is suspended
            if (auth()->user()->status === 'suspended') {
                abort(403, 'Your account is suspended');
            }

            abort(403, 'Access denied');
        }

        $document->delete();

        return redirect()->route('documents.index');
    }
}
```

## Real-World Example: Production System

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // ============= Allow Rules =============
    // Admins have broad access
    new PolicyRule('role:admin', '*', '*', Effect::Allow),

    // Editors can manage content
    new PolicyRule('role:editor', 'content:*', '*', Effect::Allow),

    // Users can manage their own content
    new PolicyRule('*', 'content:owned:*', '*', Effect::Allow),

    // ============= Deny Override Rules =============
    // 1. Status-based denies (highest priority)
    new PolicyRule('status:banned', '*', '*', Effect::Deny),
    new PolicyRule('status:suspended', '*', 'write', Effect::Deny),
    new PolicyRule('status:suspended', '*', 'delete', Effect::Deny),

    // 2. Critical resource protection
    new PolicyRule('*', 'system:critical:*', 'delete', Effect::Deny),
    new PolicyRule('*', 'database:production:*', 'drop', Effect::Deny),
    new PolicyRule('*', 'config:security:*', 'modify', Effect::Deny),

    // 3. Compliance restrictions
    new PolicyRule('*', 'audit-log:*', 'delete', Effect::Deny),
    new PolicyRule('*', 'financial:*', 'modify', Effect::Deny, [
        'condition' => 'resource.is_finalized == true'
    ]),

    // 4. Maintenance mode restrictions
    new PolicyRule('*', '*', 'write', Effect::Deny, [
        'condition' => 'environment.maintenance_mode == true && subject.role != "super-admin"'
    ]),

    // 5. IP-based restrictions
    new PolicyRule('*', '*', '*', Effect::Deny, [
        'condition' => 'environment.ip in environment.ip_blacklist'
    ]),

    // 6. Time-based restrictions
    new PolicyRule('*', 'sensitive:*', '*', Effect::Deny, [
        'condition' => 'environment.hour < 6 || environment.hour > 22'
    ]),

    // 7. Archived resource protection
    new PolicyRule('*', '*:archived:*', 'edit', Effect::Deny),
    new PolicyRule('*', '*:archived:*', 'delete', Effect::Deny),
]);
```

## Database Storage

```php
// Migration
Schema::create('deny_override_rules', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Human-readable name
    $table->string('subject_pattern')->default('*');
    $table->string('resource_pattern')->default('*');
    $table->string('action')->default('*');
    $table->enum('effect', ['allow', 'deny']);
    $table->text('condition')->nullable(); // Optional ABAC condition
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->text('reason')->nullable(); // Why this rule exists
    $table->timestamps();

    $table->index(['is_active', 'effect', 'priority']);
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class DenyOverrideRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('deny_override_rules')
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderByRaw("CASE WHEN effect = 'deny' THEN 0 ELSE 1 END") // Denies first
            ->get()
            ->map(fn($row) => $row->condition
                ? new ConditionalPolicyRule(
                    condition: $row->condition,
                    resource: $row->resource_pattern,
                    action: $row->action,
                    effect: Effect::from($row->effect),
                )
                : new PolicyRule(
                    subject: $row->subject_pattern,
                    resource: $row->resource_pattern,
                    action: $row->action,
                    effect: Effect::from($row->effect),
                )
            )
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('denies access even when allow rule exists', function () {
    $policy = new Policy([
        new PolicyRule('role:admin', '*', '*', Effect::Allow),
        new PolicyRule('*', 'critical:*', 'delete', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new DenyOverrideEffectResolver()
    );

    $subject = new Subject('admin-1', ['roles' => ['admin']]);
    $resource = new Resource('critical-data', 'critical');

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('delete'));

    expect($result)->toBe(Effect::Deny);
});

it('allows access when no deny rule exists', function () {
    $policy = new Policy([
        new PolicyRule('role:admin', '*', '*', Effect::Allow),
        new PolicyRule('*', 'critical:*', 'delete', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new DenyOverrideEffectResolver()
    );

    $subject = new Subject('admin-1', ['roles' => ['admin']]);
    $resource = new Resource('normal-data', 'data');

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Allow);
});

it('blocks suspended users despite other permissions', function () {
    $policy = new Policy([
        new PolicyRule('user-1', 'article:*', '*', Effect::Allow),
        new PolicyRule('status:suspended', '*', '*', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new DenyOverrideEffectResolver()
    );

    $subject = new Subject('user-1', ['status' => 'suspended']);
    $resource = new Resource('article-1', 'article');

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('edit'));

    expect($result)->toBe(Effect::Deny);
});
```

## Emergency Lockdown

```php
class EmergencyLockdownService
{
    public function enableLockdown(string $reason): void
    {
        // Create deny-all rule
        DB::table('deny_override_rules')->insert([
            'name' => 'Emergency Lockdown',
            'subject_pattern' => '*',
            'resource_pattern' => '*',
            'action' => 'write',
            'effect' => 'deny',
            'priority' => 9999,
            'is_active' => true,
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear policy cache
        Cache::tags(['patrol', 'policy'])->flush();

        Log::critical('Emergency lockdown enabled', ['reason' => $reason]);
    }

    public function disableLockdown(): void
    {
        DB::table('deny_override_rules')
            ->where('name', 'Emergency Lockdown')
            ->delete();

        Cache::tags(['patrol', 'policy'])->flush();

        Log::info('Emergency lockdown disabled');
    }
}
```

## Audit Trail

```php
class DenyOverrideAuditLogger
{
    public function logDeny(Subject $subject, Resource $resource, Action $action, PolicyRule $denyRule): void
    {
        DB::table('access_denials')->insert([
            'subject_id' => $subject->id(),
            'resource_type' => $resource->type(),
            'resource_id' => $resource->id(),
            'action' => $action->name(),
            'deny_rule_id' => $denyRule->id ?? null,
            'reason' => $denyRule->reason ?? 'Explicit deny override',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);

        Log::warning('Access denied by override rule', [
            'subject' => $subject->id(),
            'resource' => $resource->id(),
            'action' => $action->name(),
            'rule' => $denyRule->reason ?? 'Unknown',
        ]);
    }
}
```

## Best Practices

1. **Document Deny Rules**: Always include a reason for each deny rule
2. **Minimal Denies**: Use deny rules sparingly, only for critical restrictions
3. **Audit Everything**: Log all deny override decisions
4. **Clear Messages**: Provide specific error messages for deny reasons
5. **Review Regularly**: Periodically review and clean up deny rules
6. **Priority Order**: Ensure deny rules have highest priority
7. **Testing**: Thoroughly test that denies cannot be bypassed
8. **Emergency Access**: Maintain super-admin bypass for emergencies

## Security Considerations

### Bypass Prevention

```php
// Ensure no bypass is possible
class SecureDenyOverrideResolver extends DenyOverrideEffectResolver
{
    private array $criticalDenyPatterns = [
        'system:critical:*',
        'database:production:*',
        'audit-log:*',
    ];

    public function resolve(array $effects): Effect
    {
        // Check for critical resource deny
        foreach ($this->criticalDenyPatterns as $pattern) {
            if ($this->matchesCriticalPattern($pattern)) {
                // Log bypass attempt
                Log::critical('Attempted bypass of critical deny rule');

                // Always deny, no exceptions
                return Effect::Deny;
            }
        }

        return parent::resolve($effects);
    }
}
```

## When to Use Deny-Override

✅ **Good for:**
- Security-critical systems
- Compliance requirements
- Production environments
- Multi-layered security
- User suspension/blocking
- Emergency restrictions
- Audit period controls

❌ **Avoid for:**
- Simple permission systems (use standard resolution)
- Development environments (can be too restrictive)
- When explicit allows are sufficient
- Performance-critical paths (adds complexity)

## Compliance Example

```php
// GDPR/CCPA compliance deny rules
$policy = new Policy([
    // Normal permissions
    new PolicyRule('role:admin', 'user-data:*', '*', Effect::Allow),

    // Compliance denies
    // Cannot delete audit logs (required retention)
    new PolicyRule('*', 'audit-log:*', 'delete', Effect::Deny),

    // Cannot modify finalized records (immutability)
    new PolicyRule('*', 'record:finalized:*', 'modify', Effect::Deny),

    // Cannot export to restricted countries
    new PolicyRule('*', 'personal-data:*', 'export', Effect::Deny, [
        'condition' => 'environment.destination_country in ["XX", "YY"]'
    ]),

    // Cannot process data without consent
    new PolicyRule('*', 'personal-data:*', 'process', Effect::Deny, [
        'condition' => 'resource.consent_given != true'
    ]),
]);
```

## Related Models

- [ACL](./acl.md) - Basic permissions with deny-override
- [RBAC](./rbac.md) - Role-based with security denies
- [ABAC](./abac.md) - Conditional denies
- [Priority-Based](./priority-based.md) - Ordered rule evaluation




---

<div style="page-break-before: always;"></div>

# Priority-Based Authorization

Firewall-style rule ordering where rules are evaluated by priority.

## Overview

Priority-Based authorization evaluates rules in a specific order based on priority levels, similar to firewall rules. The first matching rule determines the outcome. This model is useful when you need explicit control over rule evaluation order, especially in complex scenarios with overlapping rules.

## Basic Concept

```
Rules evaluated by priority (highest first)
First match wins
```

## Use Cases

- Firewall-style access control
- Complex override scenarios
- Network security policies
- Cascading permission rules
- Exception handling (high priority exceptions, low priority defaults)
- API gateway policies
- Multi-layered security with clear precedence

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy, Priority};
use Patrol\Core\Engine\{PolicyEvaluator, PriorityRuleMatcher, EffectResolver};

$policy = new Policy([
    // Priority 100 - Emergency override: block all during incident
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(100),
        condition: 'environment.security_incident == true'
    ),

    // Priority 90 - Superuser always allowed
    new PolicyRule(
        subject: 'role:superuser',
        resource: '*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(90)
    ),

    // Priority 80 - Suspended users blocked
    new PolicyRule(
        subject: 'status:suspended',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(80)
    ),

    // Priority 50 - Department-specific rules
    new PolicyRule(
        subject: 'department:engineering',
        resource: 'code:*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(50)
    ),

    // Priority 10 - Default read access for authenticated users
    new PolicyRule(
        subject: 'authenticated',
        resource: '*',
        action: 'read',
        effect: Effect::Allow,
        priority: new Priority(10)
    ),

    // Priority 0 - Default deny
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(0)
    ),
]);

$evaluator = new PolicyEvaluator(new PriorityRuleMatcher(), new EffectResolver());

// Superuser allowed despite lower priority deny
$result = $evaluator->evaluate(
    $policy,
    new Subject('admin-1', ['roles' => ['superuser']]),
    new Resource('sensitive-data', 'data'),
    new Action('delete')
);
// => Effect::Allow (priority 90 rule matches first)
```

## Patterns

### Layered Security Model

```php
// Priority 1000: Emergency lockdown
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(1000), condition: 'environment.lockdown == true');

// Priority 900: Banned users
new PolicyRule('status:banned', '*', '*', Effect::Deny, priority: new Priority(900));

// Priority 800: Maintenance exceptions
new PolicyRule('role:super-admin', '*', '*', Effect::Allow, priority: new Priority(800));

// Priority 700: IP blacklist
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(700), condition: 'environment.ip in blacklist');

// Priority 500: Role-based permissions
new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(500));
new PolicyRule('role:editor', 'content:*', 'edit', Effect::Allow, priority: new Priority(500));

// Priority 100: Default authenticated
new PolicyRule('authenticated', '*', 'read', Effect::Allow, priority: new Priority(100));

// Priority 0: Default deny
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(0));
```

### Exception-Based Model

```php
// High priority exceptions
new PolicyRule('user-123', 'restricted:*', 'access', Effect::Allow, priority: new Priority(100)); // Special access

// Medium priority standard rules
new PolicyRule('role:manager', 'restricted:*', 'view', Effect::Allow, priority: new Priority(50));

// Low priority defaults
new PolicyRule('*', 'restricted:*', '*', Effect::Deny, priority: new Priority(1));
```

### Time-Based Priority

```php
// Priority 100: Business hours - more permissive
new PolicyRule(
    subject: 'role:employee',
    resource: 'office-resource:*',
    action: '*',
    effect: Effect::Allow,
    priority: new Priority(100),
    condition: 'environment.hour >= 9 && environment.hour <= 17'
);

// Priority 50: After hours - restricted
new PolicyRule(
    subject: 'role:employee',
    resource: 'office-resource:*',
    action: 'read',
    effect: Effect::Allow,
    priority: new Priority(50),
    condition: 'environment.hour < 9 || environment.hour > 17'
);

// Priority 10: Night security
new PolicyRule(
    subject: 'role:security',
    resource: '*',
    action: 'monitor',
    effect: Effect::Allow,
    priority: new Priority(10)
);
```

## Laravel Integration

### Priority Service

```php
class PolicyPriorityService
{
    const EMERGENCY = 1000;
    const CRITICAL = 900;
    const HIGH = 800;
    const ELEVATED = 700;
    const MEDIUM = 500;
    const NORMAL = 300;
    const LOW = 100;
    const DEFAULT = 0;

    public static function emergency(): Priority
    {
        return new Priority(self::EMERGENCY);
    }

    public static function critical(): Priority
    {
        return new Priority(self::CRITICAL);
    }

    // ... etc
}
```

### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', [
            'authenticated' => false,
            'priority_level' => 0,
        ]);
    }

    return new Subject($user->id, [
        'authenticated' => true,
        'roles' => $user->roles->pluck('name')->all(),
        'status' => $user->status,
        'department' => $user->department,
        'priority_level' => $this->calculatePriority($user),
    ]);
});
```

### Middleware

```php
class PriorityAuthorizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Get resource from route
        $resource = $request->route('resource');
        $action = $request->method();

        // Evaluate with priority matching
        if (!Patrol::check($resource, $action)) {
            // Get the matching rule priority for error context
            $matchedRule = Patrol::getMatchedRule();

            Log::warning('Access denied by priority rule', [
                'priority' => $matchedRule?->priority?->value(),
                'rule' => $matchedRule?->description,
            ]);

            abort(403);
        }

        return $next($request);
    }
}
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class AdminController extends Controller
{
    public function dangerousOperation()
    {
        // High-priority rules will be checked first
        if (!Patrol::check(new Resource('system', 'system'), 'dangerous-operation')) {
            $matchedRule = Patrol::getMatchedRule();

            if ($matchedRule?->priority?->value() >= 900) {
                abort(403, 'Operation blocked by high-priority security rule');
            }

            abort(403, 'Insufficient permissions');
        }

        // Perform operation
        SystemService::performDangerousOperation();

        return redirect()->back()->with('success', 'Operation completed');
    }
}
```

## Real-World Example: Corporate Network Access

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy, Priority};

$policy = new Policy([
    // ============= EMERGENCY (1000) =============
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(1000),
        condition: 'environment.security_breach == true'
    ),

    // ============= CRITICAL (900) =============
    // Banned users - always denied
    new PolicyRule(
        subject: 'status:banned',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(900)
    ),

    // ============= HIGH (800) =============
    // Super admin bypass
    new PolicyRule(
        subject: 'role:super-admin',
        resource: '*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(800)
    ),

    // ============= ELEVATED (700) =============
    // VPN required for remote access
    new PolicyRule(
        subject: '*',
        resource: 'internal:*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(700),
        condition: 'environment.on_vpn != true && environment.on_premises != true'
    ),

    // IP whitelist for sensitive resources
    new PolicyRule(
        subject: '*',
        resource: 'sensitive:*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(700),
        condition: 'environment.ip in whitelist'
    ),

    // ============= MEDIUM (500) =============
    // Department-specific access
    new PolicyRule(
        subject: 'department:engineering',
        resource: 'repo:*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(500)
    ),

    new PolicyRule(
        subject: 'department:finance',
        resource: 'financial:*',
        action: '*',
        effect: Effect::Allow,
        priority: new Priority(500)
    ),

    // ============= NORMAL (300) =============
    // Role-based permissions
    new PolicyRule(
        subject: 'role:manager',
        resource: 'team:*',
        action: 'manage',
        effect: Effect::Allow,
        priority: new Priority(300)
    ),

    new PolicyRule(
        subject: 'role:employee',
        resource: 'team:*',
        action: 'view',
        effect: Effect::Allow,
        priority: new Priority(300)
    ),

    // ============= LOW (100) =============
    // Default authenticated access
    new PolicyRule(
        subject: 'authenticated',
        resource: 'public:*',
        action: 'read',
        effect: Effect::Allow,
        priority: new Priority(100)
    ),

    // ============= DEFAULT (0) =============
    // Default deny everything else
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: '*',
        effect: Effect::Deny,
        priority: new Priority(0)
    ),
]);
```

## Database Storage

```php
// Migration
Schema::create('priority_rules', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->integer('priority'); // Higher = evaluated first
    $table->string('subject_pattern')->default('*');
    $table->string('resource_pattern')->default('*');
    $table->string('action')->default('*');
    $table->enum('effect', ['allow', 'deny']);
    $table->text('condition')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('effective_from')->nullable();
    $table->timestamp('effective_until')->nullable();
    $table->timestamps();

    $table->index(['is_active', 'priority']);
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class PriorityBasedRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('priority_rules')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            })
            ->orderBy('priority', 'desc') // Highest priority first
            ->orderBy('id', 'asc') // Stable ordering for same priority
            ->get()
            ->map(fn($row) => $row->condition
                ? new ConditionalPolicyRule(
                    condition: $row->condition,
                    resource: $row->resource_pattern,
                    action: $row->action,
                    effect: Effect::from($row->effect),
                    priority: new Priority($row->priority)
                )
                : new PolicyRule(
                    subject: $row->subject_pattern,
                    resource: $row->resource_pattern,
                    action: $row->action,
                    effect: Effect::from($row->effect),
                    priority: new Priority($row->priority)
                )
            )
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('evaluates higher priority rules first', function () {
    $policy = new Policy([
        new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(10)),
        new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(50)),
    ]);

    $evaluator = new PolicyEvaluator(new PriorityRuleMatcher(), new EffectResolver());

    $subject = new Subject('admin-1', ['roles' => ['admin']]);
    $result = $evaluator->evaluate($policy, $subject, new Resource('data', 'data'), new Action('read'));

    // Higher priority allow (50) wins over lower priority deny (10)
    expect($result)->toBe(Effect::Allow);
});

it('stops at first matching rule', function () {
    $policy = new Policy([
        new PolicyRule('role:user', 'public:*', 'read', Effect::Allow, priority: new Priority(100)),
        new PolicyRule('role:user', 'public:*', 'read', Effect::Deny, priority: new Priority(50)),
    ]);

    $evaluator = new PolicyEvaluator(new PriorityRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['roles' => ['user']]);
    $result = $evaluator->evaluate($policy, $subject, new Resource('public-data', 'public'), new Action('read'));

    // First match (priority 100) wins
    expect($result)->toBe(Effect::Allow);
});

it('handles emergency override with highest priority', function () {
    $policy = new Policy([
        new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(500)),
        new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(1000), condition: 'environment.emergency == true'),
    ]);

    $evaluator = new PolicyEvaluator(new PriorityRuleMatcher(), new EffectResolver());
    $evaluator->setEnvironmentAttribute('emergency', true);

    $subject = new Subject('admin-1', ['roles' => ['admin']]);
    $result = $evaluator->evaluate($policy, $subject, new Resource('data', 'data'), new Action('access'));

    // Emergency deny (1000) overrides admin allow (500)
    expect($result)->toBe(Effect::Deny);
});
```

## Priority Management

```php
class PriorityRuleManager
{
    public function addEmergencyRule(string $reason): int
    {
        return DB::table('priority_rules')->insertGetId([
            'name' => 'Emergency Block',
            'description' => $reason,
            'priority' => 1000,
            'subject_pattern' => '*',
            'resource_pattern' => '*',
            'action' => '*',
            'effect' => 'deny',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function addTemporaryException(User $user, string $resource, int $durationMinutes): void
    {
        DB::table('priority_rules')->insert([
            'name' => "Temporary Exception: {$user->name}",
            'priority' => 700,
            'subject_pattern' => $user->id,
            'resource_pattern' => $resource,
            'action' => '*',
            'effect' => 'allow',
            'is_active' => true,
            'effective_from' => now(),
            'effective_until' => now()->addMinutes($durationMinutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear cache
        Cache::tags(['patrol', "user:{$user->id}"])->flush();
    }

    public function listActiveRules(): Collection
    {
        return DB::table('priority_rules')
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();
    }
}
```

## Best Practices

1. **Define Priority Levels**: Use constants for priority ranges (EMERGENCY=1000, CRITICAL=900, etc.)
2. **Document Priority Rationale**: Explain why each priority level was chosen
3. **Avoid Priority Conflicts**: Don't use same priority for conflicting rules
4. **Default Deny**: Always have a low-priority default deny rule
5. **Emergency Overrides**: Reserve highest priorities for emergencies
6. **Test Priority Order**: Thoroughly test rule evaluation order
7. **Audit Changes**: Log all priority rule additions/modifications
8. **Review Regularly**: Periodically review and consolidate priority rules

## When to Use Priority-Based Authorization

✅ **Good for:**
- Firewall-style access control
- Complex override scenarios
- Network security policies
- Emergency lockdown requirements
- Exception-heavy systems
- Multi-layered security
- API gateway policies

❌ **Avoid for:**
- Simple permission systems (use ACL/RBAC)
- When rule order doesn't matter
- High-performance requirements (priority evaluation adds overhead)
- Systems with few rules

## Debugging Priority Rules

```php
class PriorityDebugger
{
    public function explainDecision(Subject $subject, Resource $resource, Action $action): array
    {
        $policy = app(PolicyRepositoryInterface::class)->find();
        $rules = $policy->rules();

        $evaluation = [];

        foreach ($rules as $rule) {
            $matches = $this->ruleMatches($rule, $subject, $resource, $action);

            $evaluation[] = [
                'priority' => $rule->priority?->value() ?? 0,
                'matches' => $matches,
                'effect' => $rule->effect->value,
                'subject' => $rule->subject,
                'resource' => $rule->resource,
                'action' => $rule->action,
                'stopped_here' => $matches, // First match stops evaluation
            ];

            if ($matches) {
                break; // First match wins
            }
        }

        return $evaluation;
    }
}
```

## Related Models

- [Deny-Override](./deny-override.md) - Explicit deny rules
- [ABAC](./abac.md) - Conditional priority rules
- [ACL](./acl.md) - Simple priority ordering
- [RESTful](./restful.md) - API endpoint priorities


