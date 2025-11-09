# Getting Started with Patrol

A progressive learning path from simple to advanced authorization models.

## Learning Path (15 minutes)

New to authorization? Follow this path to understand Patrol's capabilities:

**ACL → RBAC → ABAC** (simple → grouped → dynamic)

---

## Step 1: ACL Basics (5 min)

Start with the simplest model: **[ACL (Access Control List)](../models/acl.md)**

### What You'll Learn
- Direct user → resource permissions
- Basic allow/deny rules
- Wildcard patterns

### Quick Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Alice can read document-1
    new PolicyRule('alice', 'document-1', 'read', Effect::Allow),

    // Bob can edit document-1
    new PolicyRule('bob', 'document-1', 'edit', Effect::Allow),

    // Charlie can do anything with document-2
    new PolicyRule('charlie', 'document-2', '*', Effect::Allow),
]);
```

### When to Use ACL
✅ Small apps (< 10 users)
✅ Simple permission needs
✅ Direct user-resource relationships

**👉 Read the full guide:** [ACL Documentation](../models/acl.md)

---

## Step 2: Add Roles (5 min)

When users share permissions, use: **[RBAC (Role-Based Access Control)](../models/rbac.md)**

### What You'll Learn
- Group permissions into roles
- Assign roles to users
- Reduce permission duplication

### Quick Example

```php
// Instead of giving 20 editors individual permissions...
// Create one "editor" role:

new PolicyRule('editor', 'article:*', 'read', Effect::Allow),
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('editor', 'article:*', 'publish', Effect::Allow),

// Then assign users to the "editor" role
$subject = new Subject('alice', ['roles' => ['editor']]);
```

### Migration from ACL

**Before (ACL):**
```php
new PolicyRule('alice', 'article:*', 'edit', Effect::Allow),
new PolicyRule('bob', 'article:*', 'edit', Effect::Allow),
new PolicyRule('charlie', 'article:*', 'edit', Effect::Allow),
// ... 20 more users
```

**After (RBAC):**
```php
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),
// Alice, Bob, Charlie all have role: 'editor'
```

### When to Use RBAC
✅ Teams with job functions
✅ 10+ users with similar permissions
✅ Clear organizational roles

**👉 Read the full guide:** [RBAC Documentation](../models/rbac.md)

**💡 Migrating from Spatie?** Run `php artisan patrol:migrate-from-spatie` to automatically convert your existing roles and permissions. [Complete Migration Guide →](./migrating-from-spatie.md)

---

## Step 3: Dynamic Rules (5 min)

For ownership & context-aware permissions: **[ABAC (Attribute-Based Access Control)](../models/abac.md)**

### What You'll Learn
- Ownership-based access (users edit their own content)
- Time-based rules (office hours only)
- Attribute matching (same department)

### Quick Example

```php
// Users can edit their own articles
new PolicyRule(
    subject: 'resource.author_id == subject.id',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Allow
);

// Managers can edit their team's articles
new PolicyRule(
    subject: 'resource.author.manager_id == subject.id',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Allow
);

// Only edit published articles during business hours
new PolicyRule(
    subject: 'resource.status == "published" && hour >= 9 && hour <= 17',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Allow
);
```

### Combining RBAC + ABAC

Most apps use both:

```php
$policy = new Policy([
    // RBAC: Editors can edit articles
    new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),

    // ABAC: Anyone can edit their own articles
    new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow),

    // ABAC: Prevent editing archived articles
    new PolicyRule('resource.status != "archived"', 'article:*', 'edit', Effect::Allow),
]);
```

### When to Use ABAC
✅ Ownership-based permissions
✅ Dynamic rules (time, location)
✅ Complex conditions

**👉 Read the full guide:** [ABAC Documentation](../models/abac.md)

---

## What's Next?

You've learned the three core models. Now specialize based on your needs:

### Multi-Tenant SaaS
Users have different roles in different organizations/workspaces.

**👉 [RBAC with Domains](../models/rbac-domains.md)**

```php
// Alice is admin in tenant-1, viewer in tenant-2
$subject = new Subject('alice', [
    'domain' => 'tenant-1',
    'domain_roles' => [
        'tenant-1' => ['admin'],
        'tenant-2' => ['viewer'],
    ],
]);
```

---

### API Authorization
Authorize HTTP requests by path and method.

**👉 [RESTful Authorization](../models/restful.md)**

```php
// Alice can GET from /api/posts
new PolicyRule('alice', '/api/posts/*', 'GET', Effect::Allow),

// Bob can POST to /api/posts
new PolicyRule('bob', '/api/posts', 'POST', Effect::Allow),
```

---

### Security-Critical Systems
Explicit denials that override all allows.

**👉 [Deny-Override](../models/deny-override.md)**

```php
// Allow editors to edit articles
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),

// But deny suspended users everything (overrides allow)
new PolicyRule('status:suspended', '*', '*', Effect::Deny),
```

---

### Public APIs / No Authentication
Systems without user accounts.

**👉 [ACL without Users](../models/acl-without-users.md)**

```php
// Anyone can read public blog posts
new PolicyRule('*', 'blog-post:*', 'read', Effect::Allow),
```

---

### Feature Flags / SaaS Tiers
Permission types instead of specific resources.

**👉 [ACL without Resources](../models/acl-without-resources.md)**

```php
// Premium users can export to PDF
new PolicyRule('subscription:premium', '*', 'export-pdf', Effect::Allow),

// Free users can create up to 5 projects
new PolicyRule('subscription:free', '*', 'create-project', Effect::Allow),
```

---

## Complete Documentation

### 📖 Full Cookbook
Comprehensive guides with Laravel integration, testing, and real-world examples:
**[Cookbook README](../README.md)**

### 🚀 Quick Reference
Decision tree and common patterns:
**[Quick Reference](./quick-reference.md)**

### 📋 Model Chooser
Answer 3 questions to find your model:
**[Interactive Chooser](./chooser.md)**

---

## Common Patterns

### Pattern 1: Start Simple, Scale Up

```
Week 1:  ACL (< 10 users)
Month 3: Add RBAC (growing team)
Month 6: Add ABAC (ownership rules)
Year 1:  Add Domains (multi-tenant)
```

### Pattern 2: Combine Models

```php
// Enterprise SaaS: RBAC + ABAC + Deny-Override + Domains
$policy = new Policy([
    // Domain RBAC: Admin in tenant-1
    new PolicyRule('admin', 'project:*', '*', Effect::Allow, domain: 'tenant-1'),

    // ABAC: Ownership
    new PolicyRule('resource.owner_id == subject.id', 'project:*', '*', Effect::Allow),

    // Deny override: Suspended users
    new PolicyRule('status:suspended', '*', '*', Effect::Deny, priority: Priority::Critical),
]);
```

### Pattern 3: Progressive Enhancement

**Start:**
```php
// Simple ACL
new PolicyRule('alice', 'doc-1', 'edit', Effect::Allow),
```

**Add roles:**
```php
// RBAC
new PolicyRule('editor', 'doc:*', 'edit', Effect::Allow),
// Alice gets role: 'editor'
```

**Add ownership:**
```php
// RBAC + ABAC
new PolicyRule('editor', 'doc:*', 'edit', Effect::Allow),
new PolicyRule('resource.owner == subject.id', 'doc:*', '*', Effect::Allow),
```

---

## Testing Your Implementation

Every model includes Pest test examples. Here's a basic pattern:

```php
it('allows authorized access', function () {
    $policy = new Policy([
        new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('doc-1', 'document'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Allow);
});
```

---

## Laravel Integration

All models work seamlessly with Laravel:

### Subject Resolver
```php
Patrol::resolveSubject(function () {
    return new Subject(auth()->id(), [
        'roles' => auth()->user()?->roles->pluck('name')->all() ?? [],
    ]);
});
```

### Middleware
```php
Route::middleware(['patrol:article:edit'])->group(function () {
    Route::post('/articles/{id}', [ArticleController::class, 'update']);
});
```

### Controller
```php
use Patrol\Laravel\Facades\Patrol;

if (!Patrol::check($article, 'edit')) {
    abort(403);
}
```

### Blade
```blade
@can('edit', $article)
    <button>Edit Article</button>
@endcan
```

---

## Need Help?

1. **Stuck choosing a model?** → [Interactive Chooser](./chooser.md)
2. **Want quick examples?** → [Quick Reference](./quick-reference.md)
3. **Need comprehensive guides?** → [Full Cookbook](../README.md)
4. **Looking for specific use case?** → [Cookbook by Use Case](../README.md#by-use-case)

---

Happy authorizing! 🔐
