# ACL (Access Control List)

Direct subject-resource-action permissions for explicit authorization.

## Overview

ACL is the simplest authorization model where you explicitly define which subjects can perform which actions on specific resources. It's ideal for straightforward permission systems without roles or complex logic.

## Basic Concept

```
Subject + Resource + Action = Permission
```

## How ACL Works

```
Step 1: Define who can do what
┌─────────┐     ┌──────────┐     ┌──────────┐
│ Subject │────>│ Resource │────>│  Action  │
│ user-1  │     │  doc-1   │     │   read   │
└─────────┘     └──────────┘     └──────────┘

Step 2: Evaluate permission request
┌─────────────────────────────────────────────┐
│ Policy Rule Matcher                         │
│ Does "user-1 + doc-1 + read" match a rule? │
└─────────────────────────────────────────────┘
               │
        ┌──────┴──────┐
        │             │
        ▼             ▼
    ┌───────┐    ┌───────┐
    │ ALLOW │    │ DENY  │
    └───────┘    └───────┘
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

### Wildcard Pattern Examples

```
Wildcard Matching:
┌──────────────────┬─────────────────┬─────────┐
│ Pattern          │ Matches         │ Result  │
├──────────────────┼─────────────────┼─────────┤
│ document:*       │ document:1      │ ✅ Yes  │
│ document:*       │ document:2      │ ✅ Yes  │
│ document:*       │ article:1       │ ❌ No   │
│ document:100     │ document:100    │ ✅ Yes  │
│ document:100     │ document:101    │ ❌ No   │
│ *                │ document:1      │ ✅ Yes  │
│ *                │ anything        │ ✅ Yes  │
└──────────────────┴─────────────────┴─────────┘
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

### Scenario

Alice creates a document and shares it with her team:
- **Alice** (owner) - Can do everything
- **Bob** (viewer) - Can only read
- **Charlie** (editor) - Can read and edit

### The Policy

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

### Let's Test It

```php
$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

// ✅ Alice can delete her file
$result = $evaluator->evaluate(
    $policy,
    new Subject('alice'),
    new Resource('file:1', 'file'),
    new Action('delete')
);
// => Effect::Allow ✅

// ❌ Bob CANNOT delete (he's just a viewer)
$result = $evaluator->evaluate(
    $policy,
    new Subject('bob'),
    new Resource('file:1', 'file'),
    new Action('delete')
);
// => Effect::Deny ❌ (no rule grants this)

// ✅ Charlie can write (he's an editor)
$result = $evaluator->evaluate(
    $policy,
    new Subject('charlie'),
    new Resource('file:1', 'file'),
    new Action('write')
);
// => Effect::Allow ✅

// ❌ Charlie CANNOT delete (not in his permissions)
$result = $evaluator->evaluate(
    $policy,
    new Subject('charlie'),
    new Resource('file:1', 'file'),
    new Action('delete')
);
// => Effect::Deny ❌
```

### What Happens?

```
Alice tries to delete file:1
├─ Check rules for: alice + file:1 + delete
├─ Match found: ✅ new PolicyRule('alice', 'file:1', 'delete', Allow)
└─ Result: ALLOW

Bob tries to delete file:1
├─ Check rules for: bob + file:1 + delete
├─ No matching rule found
└─ Result: DENY (default)

Charlie tries to write to file:1
├─ Check rules for: charlie + file:1 + write
├─ Match found: ✅ new PolicyRule('charlie', 'file:1', 'write', Allow)
└─ Result: ALLOW
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

## Common Mistakes

### ❌ Mistake 1: Over-using Wildcards

```php
// DON'T: Too permissive
new PolicyRule('user-1', '*', '*', Effect::Allow);
```

**Why it's wrong:** Gives user-1 unlimited access to everything.

**✅ DO THIS:**
```php
// Be specific about what they can access
new PolicyRule('user-1', 'document:*', 'read', Effect::Allow);
new PolicyRule('user-1', 'document:*', 'edit', Effect::Allow);
```

---

### ❌ Mistake 2: Inconsistent Resource Naming

```php
// DON'T: Mix naming patterns
new PolicyRule('user-1', 'document-1', 'read', Effect::Allow);
new PolicyRule('user-1', 'doc:2', 'read', Effect::Allow);
new PolicyRule('user-1', 'documents/3', 'read', Effect::Allow);
```

**Why it's wrong:** Wildcard patterns won't work, harder to maintain.

**✅ DO THIS:**
```php
// Use consistent naming: type:id
new PolicyRule('user-1', 'document:1', 'read', Effect::Allow);
new PolicyRule('user-1', 'document:2', 'read', Effect::Allow);
new PolicyRule('user-1', 'document:3', 'read', Effect::Allow);

// Now wildcards work
new PolicyRule('user-1', 'document:*', 'read', Effect::Allow);
```

---

### ❌ Mistake 3: Not Handling Deny Cases

```php
// DON'T: Only check for Allow
if (Patrol::check($doc, 'edit') === Effect::Allow) {
    // Might miss explicit denials!
}
```

**Why it's wrong:** Doesn't account for explicit deny rules.

**✅ DO THIS:**
```php
// Use boolean check (handles both Allow and Deny)
if (Patrol::check($doc, 'edit')) {
    // Correctly evaluates allow/deny
}

// Or throw on deny
Patrol::authorize($doc, 'edit'); // Throws 403 if denied
```

---

### ❌ Mistake 4: Creating Permission Explosion

```php
// DON'T: Individual rule for every combination
new PolicyRule('alice', 'doc:1', 'read', Effect::Allow);
new PolicyRule('alice', 'doc:1', 'edit', Effect::Allow);
new PolicyRule('alice', 'doc:1', 'delete', Effect::Allow);
new PolicyRule('alice', 'doc:2', 'read', Effect::Allow);
new PolicyRule('alice', 'doc:2', 'edit', Effect::Allow);
// ... 100 more rules
```

**Why it's wrong:** Unmanageable, slow, error-prone.

**✅ DO THIS:**
```php
// Use wildcards strategically
new PolicyRule('alice', 'doc:*', '*', Effect::Allow);

// Or migrate to RBAC when you have this pattern
new PolicyRule('role:editor', 'doc:*', '*', Effect::Allow);
// Then assign Alice the 'editor' role
```

---

## Best Practices

1. **Explicit is Better**: Define exact permissions rather than relying on wildcards
2. **Least Privilege**: Grant only the minimum required permissions
3. **Regular Audits**: Review ACL entries periodically to remove stale permissions
4. **Consistent Naming**: Use `type:id` format for all resources
5. **Document Ownership**: Track who created/owns resources for easier permission management
6. **Performance**: Use wildcards to reduce rule count when appropriate

## 📈 When to Upgrade from ACL

You should consider migrating to a different model when you notice these patterns:

### ⚠️ Signs You've Outgrown ACL

- [ ] You're copy-pasting the same permissions for **3+ users**
- [ ] You have **> 10 users** with identical permission patterns
- [ ] You're manually updating **5+ rules** every time requirements change
- [ ] New users ask "what am I allowed to do?" and you can't easily explain
- [ ] You're creating 10+ rules per user
- [ ] Permission management is taking **> 30 minutes per week**

### Upgrade Paths

**If you have repeated permission patterns →** [Migrate to RBAC](./rbac.md#migrating-from-acl-to-rbac)

Example: 10 users all have identical "editor" permissions
```php
// ACL (10 rules)
new PolicyRule('alice', 'article:*', 'edit', Effect::Allow),
new PolicyRule('bob', 'article:*', 'edit', Effect::Allow),
// ... 8 more

// RBAC (1 rule + role assignments)
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),
```

---

**If you need dynamic/ownership-based rules →** [Add ABAC](./abac.md)

Example: Users should edit their own content
```php
// ABAC
new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow),
// Works for all users dynamically
```

---

**If you have multiple organizations/tenants →** [Use RBAC with Domains](./rbac-domains.md)

---

## When to Use ACL

✅ **Good for:**
- Small applications with **< 10 users**
- Simple permission requirements
- Direct user-resource relationships
- File/folder permission systems
- Prototypes and MVPs

❌ **Consider alternatives for:**
- Large user bases (use RBAC)
- Complex permission logic (use ABAC)
- Dynamic authorization (use ABAC)
- Multi-tenant systems (use RBAC with Domains)

## Debugging Authorization Issues

### Problem: User should have access but gets denied

**Step 1: Verify the subject identifier**
```php
// In your controller or tinker
dd(Patrol::getCurrentSubject());
// Check: Is the subject ID what you expect?
```

**Step 2: Check policy rules are loaded**
```php
$policy = app(PolicyRepositoryInterface::class)->find();
dd($policy->rules());
// Verify your ACL rules exist
```

**Step 3: Check for exact match**
```php
// ACL requires EXACT matches
// This rule:
new PolicyRule('user-1', 'document:1', 'read', Effect::Allow)

// Matches this:
Subject: 'user-1'     ✅
Resource: 'document:1' ✅
Action: 'read'        ✅

// Does NOT match:
Subject: 'user-2'      ❌ Different user
Resource: 'document:2' ❌ Different document
Action: 'edit'        ❌ Different action
```

**Step 4: Common ACL issues**

❌ **Resource identifier mismatch**
```php
// Rule uses: 'document:1'
new PolicyRule('user-1', 'document:1', 'read', Effect::Allow);

// But checking: 'document-1' (note the dash)
Patrol::check(new Resource('document-1', 'document'), 'read');
// Result: DENY (no match)
```

❌ **Missing wildcard**
```php
// Rule: Specific document
new PolicyRule('user-1', 'document:1', 'read', Effect::Allow);

// Check: Different document
Patrol::check(new Resource('document:2', 'document'), 'read');
// Result: DENY

// Fix: Use wildcard if needed
new PolicyRule('user-1', 'document:*', 'read', Effect::Allow);
```

---

### Problem: Getting unexpected Allow when should Deny

Check for overly permissive wildcards:

```php
// This gives user-1 access to EVERYTHING:
new PolicyRule('user-1', '*', '*', Effect::Allow);

// Be more specific:
new PolicyRule('user-1', 'document:*', 'read', Effect::Allow);
```

---

### Performance: Too many rules

**Symptom:** Authorization checks are slow (> 100ms)

**Diagnosis:**
```php
$policy = app(PolicyRepositoryInterface::class)->find();
$ruleCount = count($policy->rules());

if ($ruleCount > 1000) {
    // Consider migrating to RBAC
}
```

**Solution:** Use wildcards or migrate to RBAC

```php
// Before: 100 individual rules
new PolicyRule('user-1', 'document:1', 'read', Effect::Allow),
new PolicyRule('user-1', 'document:2', 'read', Effect::Allow),
// ... 98 more

// After: 1 rule with wildcard
new PolicyRule('user-1', 'document:*', 'read', Effect::Allow),
```

---

## Related Models

- [ACL with Superuser](./acl-superuser.md) - Add admin bypass
- [RBAC](./rbac.md) - Role-based permissions
- [ABAC](./abac.md) - Attribute-based logic
