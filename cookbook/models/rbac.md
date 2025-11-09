# RBAC (Role-Based Access Control)

Assign permissions to roles, then assign roles to users for scalable authorization.

## Overview

RBAC (Role-Based Access Control) is an authorization model where permissions are assigned to roles rather than individual users. Users are then assigned roles, inheriting all permissions associated with those roles. This creates a more maintainable and scalable permission system.

## Basic Concept

```
User → Role → Permissions
Subject has Role → Role allows Action on Resource
```

## How RBAC Works

```
Step 1: Assign users to roles
┌──────┐     ┌──────────┐
│ Alice│────>│  Editor  │
└──────┘     └──────────┘
┌──────┐            │
│ Bob  │────────────┘
└──────┘

Step 2: Assign permissions to roles
┌──────────┐     ┌────────────────────┐
│  Editor  │────>│ Can edit articles  │
└──────────┘     │ Can create articles│
                 │ Can view articles  │
                 └────────────────────┘

Step 3: Evaluate permission (user inherits from role)
┌──────────────────────────────────────────────┐
│ Does Alice's role (Editor) allow the action? │
└──────────────────────────────────────────────┘
               │
        ┌──────┴──────┐
        │             │
        ▼             ▼
    ┌───────┐    ┌───────┐
    │ ALLOW │    │ DENY  │
    └───────┘    └───────┘
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

## Migrating from ACL to RBAC

If you're coming from ACL and noticing permission duplication, here's how to migrate:

### Step 1: Identify Permission Patterns

**Before (ACL with duplication):**
```php
// 20 editors all have identical permissions
new PolicyRule('alice', 'article:*', 'read', Effect::Allow),
new PolicyRule('alice', 'article:*', 'edit', Effect::Allow),
new PolicyRule('alice', 'article:*', 'create', Effect::Allow),

new PolicyRule('bob', 'article:*', 'read', Effect::Allow),
new PolicyRule('bob', 'article:*', 'edit', Effect::Allow),
new PolicyRule('bob', 'article:*', 'create', Effect::Allow),

new PolicyRule('charlie', 'article:*', 'read', Effect::Allow),
new PolicyRule('charlie', 'article:*', 'edit', Effect::Allow),
new PolicyRule('charlie', 'article:*', 'create', Effect::Allow),

// ... 17 more users with same pattern (51 total rules)
```

**Pattern identified:** "Editor" role - can read/edit/create articles

### Step 2: Create Role-Based Rules

```php
// Replace 60 ACL rules with 3 RBAC rules
new PolicyRule('role:editor', 'article:*', 'read', Effect::Allow),
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:editor', 'article:*', 'create', Effect::Allow),
```

### Step 3: Update Subject Resolver

```php
// Before (ACL)
Patrol::resolveSubject(fn() => new Subject(auth()->id()));

// After (RBAC)
Patrol::resolveSubject(function () {
    return new Subject(auth()->id(), [
        'roles' => auth()->user()?->roles->pluck('name')->all() ?? [],
    ]);
});
```

### Step 4: Assign Users to Roles

```php
// In your User model
public function roles()
{
    return $this->belongsToMany(Role::class);
}

// Assign roles
$alice->assignRole('editor');
$bob->assignRole('editor');
$charlie->assignRole('editor');
```

### Result

```
Before: 60 ACL rules (20 users × 3 actions)
After:  3 RBAC rules + role assignments

Maintainability: ⭐⭐⭐⭐⭐
When requirements change, update 3 rules instead of 60
```

---

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

## Common Mistakes

### ❌ Mistake 1: Creating User-Specific Roles

```php
// DON'T: Role per user defeats the purpose
new PolicyRule('role:alice-role', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:bob-role', 'article:*', 'edit', Effect::Allow),
```

**Why it's wrong:** This is just ACL with extra steps.

**✅ DO THIS:**
```php
// Create roles based on job functions
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),

// Assign multiple users to the same role
$alice->assignRole('editor');
$bob->assignRole('editor');
```

---

### ❌ Mistake 2: Role Explosion

```php
// DON'T: Too many similar roles
new PolicyRule('role:junior-editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:senior-editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:lead-editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:chief-editor', 'article:*', 'edit', Effect::Allow),
```

**Why it's wrong:** Hard to maintain, unclear differences.

**✅ DO THIS:**
```php
// Use fewer roles with clear distinctions
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('role:senior-editor', 'article:*', '*', Effect::Allow),

// Or combine RBAC + ABAC for nuance
new PolicyRule('subject.seniority >= resource.required_level', 'article:*', 'publish', Effect::Allow),
```

---

### ❌ Mistake 3: Forgetting Role Prefix

```php
// DON'T: Inconsistent subject identifiers
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),

// Subject resolver returns:
return new Subject($user->id, ['roles' => ['role:editor']]); // Has 'role:' prefix
```

**Why it's wrong:** `'editor'` ≠ `'role:editor'`, no match!

**✅ DO THIS:**
```php
// Rule: Use 'role:' prefix
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),

// Subject: Use 'role:' prefix
return new Subject($user->id, ['roles' => ['role:editor']]);

// Or: No prefix on both sides
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),
return new Subject($user->id, ['roles' => ['editor']]);
```

---

### ❌ Mistake 4: Not Loading User Roles

```php
// DON'T: N+1 query problem
Patrol::resolveSubject(function () {
    $user = auth()->user(); // Doesn't load roles
    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(), // Query here!
    ]);
});
```

**Why it's wrong:** Queries database on every authorization check.

**✅ DO THIS:**
```php
// Eager load roles
Patrol::resolveSubject(function () {
    $user = auth()->user()->load('roles'); // Load once
    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
    ]);
});

// Or cache the subject
Patrol::resolveSubject(function () {
    return Cache::remember('subject:' . auth()->id(), 3600, function () {
        $user = auth()->user()->load('roles');
        return new Subject($user->id, [
            'roles' => $user->roles->pluck('name')->all(),
        ]);
    });
});
```

---

## Best Practices

1. **Role Granularity**: Create roles based on job functions, not individuals
2. **Minimal Roles**: Start with 3-5 core roles (admin, editor, viewer)
3. **Clear Naming**: Use descriptive role names that match business terminology
4. **Document Roles**: Maintain documentation of what each role can do
5. **Audit Trail**: Log role assignments and permission changes
6. **Regular Review**: Periodically review and clean up unused roles
7. **Avoid Role Explosion**: Don't create > 10 roles unless truly necessary
8. **Separation of Duties**: Ensure sensitive operations require multiple roles
9. **Performance**: Eager load roles, cache subject when possible
10. **Consistency**: Use consistent role naming (all with or without `role:` prefix)

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

## Debugging Authorization Issues

### Problem: User has role but still denied

**Step 1: Verify user actually has the role**
```php
$user = auth()->user()->load('roles');
dd($user->roles->pluck('name')->all());
// Check: Does array contain expected role?
```

**Step 2: Check role naming consistency**
```php
// In rules:
$policy->rules(); // Check if using 'role:editor' or 'editor'

// In subject:
Patrol::getCurrentSubject(); // Check if roles match the pattern
```

**Step 3: Verify RbacRuleMatcher is used**
```php
// Make sure you're using the right matcher
$evaluator = new PolicyEvaluator(
    new RbacRuleMatcher(), // NOT AclRuleMatcher!
    new EffectResolver()
);
```

**Common RBAC issues:**

❌ **Role prefix mismatch**
```php
// Rule has prefix
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow);

// Subject missing prefix
new Subject($user->id, ['roles' => ['editor']]); // ❌ Won't match

// Fix: Add prefix
new Subject($user->id, ['roles' => ['role:editor']]); // ✅ Matches
```

❌ **Roles not loaded**
```php
// Check if roles are in subject attributes
$subject = Patrol::getCurrentSubject();
dump($subject->attributes); // Should have 'roles' key
```

---

### Problem: User has multiple roles, only one working

**Check role iteration:**
```php
// RBAC matcher should check ALL roles
$subject = new Subject('user-1', [
    'roles' => ['developer', 'reviewer'], // Both should be checked
]);

// Both of these should work:
Patrol::check($code, 'edit');   // From 'developer' role
Patrol::check($code, 'review'); // From 'reviewer' role
```

**If only one role works:**
```php
// Make sure RbacRuleMatcher checks all roles, not just first
$matcher = new RbacRuleMatcher();
// It should iterate through all roles in subject attributes
```

---

### Performance: Slow authorization checks

**Symptom:** Each `Patrol::check()` takes > 50ms

**Diagnosis:**
```php
// Check if roles are queried on every check
DB::enableQueryLog();
Patrol::check($resource, 'edit');
Patrol::check($resource, 'edit');
Patrol::check($resource, 'edit');
dd(DB::getQueryLog()); // Should only be 1-2 queries, not 6+
```

**Solutions:**

```php
// 1. Eager load roles in middleware
class LoadUserRolesMiddleware
{
    public function handle($request, $next)
    {
        if (auth()->check()) {
            auth()->setUser(
                auth()->user()->load('roles')
            );
        }

        return $next($request);
    }
}

// 2. Cache the policy
class CachedRbacRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        return Cache::remember('rbac-policy', 3600, function () {
            // Load rules from database
            return new Policy($rules);
        });
    }
}

// 3. Cache the subject
Patrol::resolveSubject(function () {
    return Cache::remember('subject:' . auth()->id(), 300, function () {
        $user = auth()->user()->load('roles');
        return new Subject($user->id, [
            'roles' => $user->roles->pluck('name')->all(),
        ]);
    });
});
```

---

## Related Models

- [ACL](./acl.md) - Direct user permissions (simpler, for < 10 users)
- [RBAC with Resource Roles](./rbac-resource-roles.md) - Resources have roles too
- [RBAC with Domains](./rbac-domains.md) - Multi-tenant role sets
- [ABAC](./abac.md) - Attribute-based extension (for ownership/dynamic rules)
