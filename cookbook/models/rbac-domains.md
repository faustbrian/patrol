# RBAC with Domains

Multi-tenant role sets where roles are scoped to specific domains or tenants.

## Overview

RBAC with Domains (also called Multi-Tenant RBAC) extends traditional RBAC by adding a domain/tenant dimension. Users can have different roles in different domains, enabling sophisticated multi-tenant authorization where a user might be an admin in one organization but a viewer in another.

## Basic Concept

```
User + Domain + Role → Permissions
(subject, domain, role, resource, action) → allow/deny
```

## How RBAC with Domains Works

```
Step 1: User belongs to multiple organizations with different roles
┌────────────────────────────────────────────────────┐
│ Alice's Memberships                                │
├────────────────────────────────────────────────────┤
│ Organization 1 (Acme Corp)    → Admin             │
│ Organization 2 (TechCo)       → Viewer            │
│ Organization 3 (StartupXYZ)   → Editor            │
└────────────────────────────────────────────────────┘

Step 2: Permissions are scoped to specific domains
┌─────────────────┬──────────────────────────┐
│ Domain          │ Role Permissions         │
├─────────────────┼──────────────────────────┤
│ Organization 1  │ Admin → Full Access      │
│ Organization 2  │ Viewer → Read Only       │
│ Organization 3  │ Editor → Read + Edit     │
└─────────────────┴──────────────────────────┘

Step 3: Authorization checks current domain + role
┌──────────────────────────────────────────────┐
│ Alice in Organization 1 → Has Admin role     │
│ Can she delete projects?                     │
│ ✅ YES (Admin has full access in Org 1)      │
└──────────────────────────────────────────────┘

┌──────────────────────────────────────────────┐
│ Alice switches to Organization 2 → Viewer    │
│ Can she delete projects?                     │
│ ❌ NO (Viewer can only read in Org 2)        │
└──────────────────────────────────────────────┘
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

## Common Mistakes

### ❌ Mistake 1: Forgetting Current Domain

```php
// DON'T: No domain context
$subject = new Subject('user-1', ['roles' => ['admin']]); // ❌ Which org?
```

**✅ DO THIS:**
```php
$subject = new Subject('user-1', [
    'domain' => 'org-1', // ✅ Current domain
    'domain_roles' => ['org-1' => ['admin']],
]);
```

---

### ❌ Mistake 2: Not Isolating Domain Data

```php
// DON'T: Query without domain filter
$projects = Project::all(); // ❌ Shows ALL tenants!
```

**✅ DO THIS:**
```php
// Always filter by current domain
$projects = Project::where('organization_id', $currentDomain)->get();

// Or use global scope for automatic filtering
```

---

### ❌ Mistake 3: Inconsistent Domain Switching

```php
// DON'T: Multiple sources of truth
session(['current_org' => 'org-1']);
auth()->user()->update(['active_org' => 'org-1']);
Cache::put('user_org', 'org-1'); // ❌ Gets out of sync!
```

**✅ DO THIS:**
```php
// Single source of truth
class DomainSwitcher
{
    public function switch(string $orgId): void
    {
        session(['current_organization_id' => $orgId]); // One place
        Cache::forget('subject:' . auth()->id()); // Clear cache
    }
}
```

---

### ❌ Mistake 4: Not Showing Current Domain

Users perform dangerous actions thinking they're in test org, but actually in production!

**✅ DO THIS:**
```blade
<nav>
    <div class="current-org-indicator">
        📍 {{ session('current_org_name') }}
    </div>
</nav>
```

---

## Best Practices

1. **Clear Domain Context**: Always make domain explicit in UI and API
2. **Prevent Cross-Domain Leakage**: Use global scopes for data isolation
3. **Domain Isolation**: Strictly isolate data between domains
4. **Role Consistency**: Use consistent role names across domains
5. **Default Domain**: Set sensible default domain for users
6. **Audit Trails**: Log domain switches and cross-domain access
7. **Domain Indicator**: Show current domain prominently in UI (color-code prod!)
8. **Permission Cache**: Cache permissions per user per domain
9. **Single Source**: One place to store/retrieve current domain
10. **Validate Access**: Check user has access to domain before switching

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

## Debugging Multi-Tenant Issues

### Problem: User can't access resources

**Step 1: Check current domain**
```php
dd(session('current_organization_id')); // Is domain set?
```

**Step 2: Verify domain-role mapping**
```php
$subject = Patrol::getCurrentSubject();
dd($subject->attributes['domain_roles']); // Has roles for this domain?
```

**Step 3: Check domains match**
```php
// Subject domain == Resource domain?
dd([
    'subject_domain' => $subject->attributes['domain'],
    'resource_domain' => $resource->attributes['domain'],
]);
```

---

### Problem: Data leaking between domains

**Solution: Add global scope**
```php
class Project extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('domain', function ($query) {
            $query->where('organization_id', session('current_organization_id'));
        });
    }
}
```

---

### Problem: Domain switch not working

**Clear caches on switch:**
```php
session(['current_organization_id' => $newOrgId]);
Cache::forget('subject:' . auth()->id()); // ✅ Clear
```

---

## Related Models

- [RBAC](./rbac.md) - Basic role-based access (foundation)
- [ABAC](./abac.md) - Attribute-based for complex logic (combine with domains)
- [ACL](./acl.md) - Per-resource permissions (within domains)
- [RBAC with Resource Roles](./rbac-resource-roles.md) - Resource-level roles
