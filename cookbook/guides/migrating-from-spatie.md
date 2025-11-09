# Migrating from Spatie Laravel Permission to Patrol

Complete guide to migrating your existing spatie/laravel-permission implementation to Patrol's authorization system.

## Overview

Patrol provides a seamless migration path from Spatie Laravel Permission with:
- ✅ Automated migration command
- ✅ Zero downtime migration strategy
- ✅ Support for both RBAC and direct permissions
- ✅ Incremental migration support
- ✅ Multi-tenant compatibility

**Migration time:** 10-30 minutes depending on data size

---

## Quick Migration (5 minutes)

For most applications, migration is a three-step process:

### Step 1: Preview Migration

```bash
php artisan patrol:migrate-from-spatie --dry-run
```

This shows what will be migrated without making changes:

```
⚠️  DRY RUN MODE - No changes will be persisted

Migrating Spatie permissions to Patrol...

✓ Migrating role permissions
Skipping user role assignments (use Subject attributes at runtime)
✓ Migrating direct user permissions

Found 47 permission(s) to migrate

Sample of rules to be created:
┌────────────┬──────────┬────────┬────────┬──────────┐
│ Subject    │ Resource │ Action │ Effect │ Priority │
├────────────┼──────────┼────────┼────────┼──────────┤
│ role:admin │ *        │ *      │ ALLOW  │ 10       │
│ role:editor│ posts    │ create │ ALLOW  │ 10       │
│ role:editor│ posts    │ edit   │ ALLOW  │ 10       │
│ role:editor│ posts    │ delete │ ALLOW  │ 10       │
│ user:123   │ settings │ view   │ ALLOW  │ 10       │
└────────────┴──────────┴────────┴────────┴──────────┘

⚠️  Dry run complete - no changes were persisted
```

### Step 2: Execute Migration

```bash
php artisan patrol:migrate-from-spatie
```

```
Migrating Spatie permissions to Patrol...

✓ Migrating role permissions
Skipping user role assignments (use Subject attributes at runtime)
✓ Migrating direct user permissions

Found 47 permission(s) to migrate

✓ Persisting policy rules

✅ Migration completed successfully!
```

### Step 3: Update Subject Resolver

Add this to your `AppServiceProvider::boot()` or `config/patrol.php`:

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return null;
    }

    // Load user's roles and format them with "role:" prefix
    $roles = $user->roles->pluck('name')->map(fn($name) => "role:{$name}")->toArray();

    return new Subject(
        id: "user:{$user->id}",
        attributes: ['roles' => $roles]
    );
});
```

**That's it!** Your authorization now uses Patrol while maintaining identical behavior.

---

## What Gets Migrated

The migration command handles three types of Spatie data:

### 1. Role Permissions (RBAC) ✅

**Spatie Schema:**
```
roles: id, name
permissions: id, name
role_has_permissions: role_id, permission_id
```

**Migration:**
```
Spatie: Role "editor" has permission "edit posts"
Patrol: subject="role:editor", resource="posts", action="edit"
```

**Example:**
```php
// Before (Spatie)
$editor = Role::create(['name' => 'editor']);
$editor->givePermissionTo(['edit posts', 'delete posts']);

// After (Patrol - migrated automatically)
// Creates 2 policy rules:
// - subject: "role:editor", resource: "posts", action: "edit"
// - subject: "role:editor", resource: "posts", action: "delete"
```

### 2. Direct User Permissions (ACL) ✅

**Spatie Schema:**
```
model_has_permissions: model_id, permission_id
```

**Migration:**
```
Spatie: User 123 has direct permission "view analytics"
Patrol: subject="user:123", resource="analytics", action="view"
```

**Example:**
```php
// Before (Spatie)
$user->givePermissionTo('view analytics');

// After (Patrol - migrated automatically)
// Creates 1 policy rule:
// - subject: "user:123", resource: "analytics", action: "view"
```

### 3. User Role Assignments (Runtime) ℹ️

**Spatie Schema:**
```
model_has_roles: model_id, role_id
```

**Migration:**
```
NOT migrated as denormalized rules.
Instead, roles are passed in Subject attributes at runtime.
```

**Why?**
- ⚡ **Performance**: 1 rule per role vs 1,000 rules for 1,000 users
- 🔄 **Flexibility**: Add/remove roles without recreating policies
- 📊 **Scalability**: Database size stays constant as users grow
- 🎯 **Maintainability**: Matches Spatie's conceptual model

**Example:**
```php
// Before (Spatie)
$user->assignRole('editor');
$user->hasPermissionTo('edit posts'); // true (via role)

// After (Patrol)
// Subject resolver passes roles at runtime:
$subject = new Subject('user:123', ['roles' => ['role:editor']]);
// RbacRuleMatcher evaluates role permissions automatically
```

---

## Permission Name Parsing

The migration automatically converts various Spatie permission naming conventions:

### Space-Separated (Recommended)
```
"edit posts"      → action="edit",    resource="posts"
"delete comments" → action="delete",  resource="comments"
"view dashboard"  → action="view",    resource="dashboard"
```

### Dot Notation
```
"posts.edit"      → action="edit",    resource="posts"
"comments.delete" → action="delete",  resource="comments"
"dashboard.view"  → action="view",    resource="dashboard"
```

### Dash Notation
```
"posts-edit"      → action="edit",    resource="posts"
"comments-delete" → action="delete",  resource="comments"
"dashboard-view"  → action="view",    resource="dashboard"
```

### Single Word (Capabilities)
```
"admin"           → action="admin",   resource="*"
"export-pdf"      → action="export-pdf", resource="*"
"super-admin"     → action="super-admin", resource="*"
```

**💡 Tip:** For best results, use space-separated format: `"{action} {resource}"`

---

## Migration Options

### Dry Run Preview

Always preview changes before executing:

```bash
php artisan patrol:migrate-from-spatie --dry-run
```

### Custom Priority

Set a different priority for migrated rules (default: 10):

```bash
php artisan patrol:migrate-from-spatie --priority=50
```

Higher priority = evaluated first when multiple rules match.

### Custom Database Connection

Migrate to a different database connection:

```bash
php artisan patrol:migrate-from-spatie --connection=tenant_db
```

### Separate Spatie Connection

Read from one database, write to another:

```bash
php artisan patrol:migrate-from-spatie \
    --spatie-connection=mysql \
    --connection=patrol_db
```

---

## Advanced Scenarios

### Multi-Tenant Migration

Migrate each tenant's permissions separately:

```php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

foreach (Tenant::all() as $tenant) {
    // Switch to tenant's database
    DB::setDefaultConnection("tenant_{$tenant->id}");

    // Migrate tenant's permissions
    Artisan::call('patrol:migrate-from-spatie', [
        '--connection' => "tenant_{$tenant->id}",
    ]);

    $output = Artisan::output();
    logger()->info("Migrated tenant {$tenant->id}: {$output}");
}
```

### Incremental Migration

Migrate in stages while Spatie continues to run:

**Stage 1: Dual-Write**
```php
// Keep writing to both Spatie AND Patrol
$user->assignRole('editor'); // Spatie
$policyRepository->create([/* ... */]); // Patrol
```

**Stage 2: Read from Patrol, Write to Both**
```php
// Authorization uses Patrol
if (Patrol::check($resource, 'edit')) {
    // ...
}

// Still write to both systems
$user->assignRole('editor');
$policyRepository->create([/* ... */]);
```

**Stage 3: Patrol Only**
```php
// Remove Spatie writes
// Use only Patrol
```

### Preserving Timestamps

If you need to preserve original creation times:

```php
// After running migration, update timestamps
DB::table('patrol_policies')
    ->whereNotNull('created_at')
    ->update([
        'created_at' => DB::raw('DATE_SUB(created_at, INTERVAL 1 YEAR)'),
    ]);
```

---

## Runtime Configuration

### Subject Resolver Setup

The Subject resolver is where you connect user roles to Patrol:

**Basic Setup:**
```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return null; // Anonymous/guest
    }

    // Get roles with "role:" prefix
    $roles = $user->roles
        ->pluck('name')
        ->map(fn($name) => "role:{$name}")
        ->toArray();

    return new Subject(
        id: "user:{$user->id}",
        attributes: ['roles' => $roles]
    );
});
```

**With Caching:**
```php
Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return null;
    }

    // Cache roles for 1 hour
    $roles = Cache::remember(
        "user:{$user->id}:roles",
        3600,
        fn() => $user->roles->pluck('name')->map(fn($n) => "role:{$n}")->toArray()
    );

    return new Subject("user:{$user->id}", ['roles' => $roles]);
});
```

**With Additional Attributes:**
```php
Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return null;
    }

    $roles = $user->roles->pluck('name')->map(fn($n) => "role:{$n}")->toArray();

    return new Subject(
        id: "user:{$user->id}",
        attributes: [
            'roles' => $roles,
            'department' => $user->department,
            'clearance_level' => $user->clearance_level,
            'email' => $user->email,
        ]
    );
});
```

### Multi-Tenant Subject Resolver

For multi-tenant apps where users have different roles per tenant:

```php
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();
    $tenant = $user?->currentTenant;

    if (!$user || !$tenant) {
        return null;
    }

    // Get global roles
    $globalRoles = $user->roles
        ->where('global', true)
        ->pluck('name')
        ->map(fn($n) => "role:{$n}")
        ->toArray();

    // Get tenant-specific roles
    $tenantRoles = $user->tenantRoles()
        ->where('tenant_id', $tenant->id)
        ->get()
        ->pluck('name')
        ->map(fn($n) => "role:{$n}")
        ->toArray();

    return new Subject(
        id: "user:{$user->id}",
        attributes: [
            'roles' => array_merge($globalRoles, $tenantRoles),
            'domain' => "tenant:{$tenant->id}",
            'tenant_id' => $tenant->id,
        ]
    );
});
```

---

## Verification & Testing

### Verify Migration Success

After migration, verify permissions still work:

```php
// Test role-based permission
$editor = User::whereHas('roles', fn($q) => $q->where('name', 'editor'))->first();
$this->assertTrue($editor->can('edit', $post));

// Test direct permission
$userWithDirectPerm = User::find(123);
$this->assertTrue($userWithDirectPerm->can('view', 'analytics'));

// Test denial
$viewer = User::whereHas('roles', fn($q) => $q->where('name', 'viewer'))->first();
$this->assertFalse($viewer->can('delete', $post));
```

### Compare Spatie vs Patrol

Before removing Spatie, verify identical behavior:

```php
use Patrol\Laravel\Facades\Patrol as PatrolFacade;

// For each user and permission:
$spatieResult = $user->can('edit posts');

$subject = new Subject("user:{$user->id}", [
    'roles' => $user->roles->pluck('name')->map(fn($r) => "role:{$r}")->toArray()
]);
$patrolResult = PatrolFacade::check(
    new Resource('posts', 'Post'),
    new Action('edit'),
    $subject
);

assert($spatieResult === ($patrolResult === Effect::Allow));
```

### Automated Comparison Test

```php
test('patrol matches spatie permissions', function () {
    $users = User::with('roles')->get();
    $permissions = Permission::all();

    foreach ($users as $user) {
        foreach ($permissions as $permission) {
            [$action, $resource] = explode(' ', $permission->name);

            $spatieResult = $user->hasPermissionTo($permission);

            $subject = new Subject("user:{$user->id}", [
                'roles' => $user->roles->pluck('name')->map(fn($r) => "role:{$r}")->toArray()
            ]);

            $patrolResult = Patrol::check(
                new Resource($resource, ucfirst($resource)),
                new Action($action),
                $subject
            ) === Effect::Allow;

            expect($patrolResult)->toBe($spatieResult,
                "Mismatch for user {$user->id} permission {$permission->name}"
            );
        }
    }
});
```

---

## Migration Checklist

Use this checklist to ensure complete migration:

### Pre-Migration
- [ ] Backup database
- [ ] Run migration in dry-run mode
- [ ] Review migrated rules output
- [ ] Verify permission name parsing is correct
- [ ] Document any custom Spatie logic that needs manual migration

### Migration
- [ ] Run migration command
- [ ] Verify policy rules created in `patrol_policies` table
- [ ] Check migration output for errors/warnings
- [ ] Compare count: Spatie permissions vs Patrol policies

### Post-Migration
- [ ] Update Subject resolver to include roles
- [ ] Test key user permissions work correctly
- [ ] Run automated comparison tests
- [ ] Monitor application logs for authorization errors
- [ ] Update authorization code to use Patrol (if not using Gates)

### Cleanup (after verification period)
- [ ] Remove Spatie package: `composer remove spatie/laravel-permission`
- [ ] Drop Spatie tables (optional - keep for rollback safety)
- [ ] Remove Spatie traits from models
- [ ] Remove Spatie middleware
- [ ] Update documentation

---

## Troubleshooting

### "Spatie permission tables not found"

**Cause:** Spatie migrations haven't been run or wrong database connection.

**Solution:**
```bash
# Verify tables exist
php artisan db:show --table=permissions

# Specify correct connection
php artisan patrol:migrate-from-spatie --spatie-connection=mysql
```

### "No permissions found to migrate"

**Cause:** Spatie tables are empty or no permissions defined.

**Solution:**
```bash
# Check Spatie data
php artisan tinker
> \DB::table('permissions')->count();
> \DB::table('roles')->count();
> \DB::table('role_has_permissions')->count();
```

### Permissions work in Spatie but not Patrol

**Cause:** Subject resolver not returning roles in correct format.

**Solution:**
```php
// Verify Subject has roles with "role:" prefix
$subject = Patrol::resolveSubject();
dd($subject->attributes['roles']);
// Should output: ['role:editor', 'role:admin']
```

### Different permission results

**Cause:** Permission name parsing may differ from Spatie logic.

**Solution:**
```bash
# Check how permissions were parsed
php artisan patrol:migrate-from-spatie --dry-run | grep "permission_name"
```

### Performance degradation after migration

**Cause:** Subject resolver not caching role lookups.

**Solution:**
```php
// Add caching to Subject resolver (see "With Caching" example above)
Cache::remember("user:{$user->id}:roles", 3600, ...);
```

---

## Rollback Strategy

If you need to rollback:

### Keep Spatie During Testing

Don't remove Spatie immediately:

```bash
# Spatie still installed
composer.json: "spatie/laravel-permission": "^6.0"

# Patrol added
composer.json: "patrol/patrol": "^1.0"
```

### Dual-Run Period

Run both systems in parallel:

```php
// Check both systems match
$spatieResult = $user->can('edit', $post);
$patrolResult = Patrol::check($post, 'edit') === Effect::Allow;

if ($spatieResult !== $patrolResult) {
    logger()->warning("Authorization mismatch", [
        'user' => $user->id,
        'resource' => $post->id,
        'spatie' => $spatieResult,
        'patrol' => $patrolResult,
    ]);
}

// Use Spatie result during migration
return $spatieResult;
```

### Complete Rollback

If needed, simply revert to Spatie:

```bash
# Remove migrated policies
php artisan db:seed --class=TruncatePatrolPoliciesSeeder

# Continue using Spatie
# (no changes needed - it's still installed)
```

---

## Performance Comparison

### Spatie (Denormalized)

```
Users: 1,000
Roles: 5
Permissions per role: 20
Total permission records: 1,000 users × 5 roles × 20 perms = 100,000 rows

Query per authorization: JOIN across 100,000 rows
Database size: ~10MB for permissions alone
```

### Patrol (RBAC)

```
Users: 1,000
Roles: 5
Permissions per role: 20
Total policy rules: 5 roles × 20 perms = 100 rules

Query per authorization: Check 100 rules (all in memory)
Database size: ~10KB for policies
```

**Result:** 1000x reduction in policy storage, faster evaluation.

---

## Next Steps

After successful migration:

1. **Learn Advanced Patterns**
   - [RBAC with Domains](../models/rbac-domains.md) - Multi-tenant roles
   - [ABAC](../models/abac.md) - Ownership and dynamic rules
   - [Deny-Override](../models/deny-override.md) - Security-critical denials

2. **Optimize Performance**
   - [Caching Policies](./persisting-policies.md#caching)
   - [Policy Builders](./policy-builders.md)
   - [Configuration Guide](./configuration.md)

3. **Testing**
   - [Testing Authorization](../patterns/testing.md)
   - [Simulation](../patterns/policy-simulation.md)

4. **Monitoring**
   - [CLI Tools](./cli-tools.md) - Debug and inspect policies
   - [Delegation](../delegation.md) - Temporary access grants

---

## Related Documentation

- **[CLI Tools](./cli-tools.md)** - Command reference and examples
- **[RBAC Guide](../models/rbac.md)** - Role-based access control
- **[Getting Started](./getting-started.md)** - Patrol basics
- **[Configuration](./configuration.md)** - System configuration

---

## Support

If you encounter issues during migration:

1. **Check logs** - Laravel logs contain authorization errors
2. **Run dry-run** - Preview migration without changes
3. **Test incrementally** - Migrate one tenant/role at a time
4. **Compare results** - Automated comparison tests (see above)
5. **Open issue** - [GitHub Issues](https://github.com/patrol/patrol/issues)

---

**🎉 Congratulations!** You've successfully migrated from Spatie to Patrol.
