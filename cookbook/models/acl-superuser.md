# ACL with Superuser

Special users with unrestricted access to all resources using wildcard permissions.

## Overview

ACL with Superuser extends basic ACL by designating certain users as administrators who bypass normal permission checks. This is achieved using wildcard rules that match all resources and actions.

## Basic Concept

```
Superuser = * (all resources) + * (all actions)
```

## How Superuser Bypass Works

```
Step 1: Incoming authorization check
┌────────────────────────────────────────┐
│ Subject: user-1                        │
│   - ID: "superuser" (special ID)       │
│   - Attributes: { is_superuser: true } │
│                                        │
│ Resource: document-999                 │
│ Action: delete                         │
└────────────────────────────────────────┘
                 ▼
Step 2: Policy matching
┌─────────────────────────────────────────────────┐
│ Rule 1: superuser → * → * → ALLOW               │
│   - Subject matches: "superuser" = "superuser"  │
│   - Resource matches: * (wildcard) ✓            │
│   - Action matches: * (wildcard) ✓              │
│   - Effect: ALLOW                               │
└─────────────────────────────────────────────────┘
                 ▼
Step 3: Immediate ALLOW (no other rules checked)
┌─────────────────────────────────┐
│ Result: ALLOW                   │
│ Reason: Superuser bypass        │
│ Checks bypassed: All            │
└─────────────────────────────────┘
```

**vs. Regular User:**

```
Step 1: Regular user check
┌────────────────────────────────────────┐
│ Subject: user-1 (regular user)         │
│ Resource: document-999                 │
│ Action: delete                         │
└────────────────────────────────────────┘
                 ▼
Step 2: Policy matching
┌──────────────────────────────────────────────────┐
│ Rule 1: superuser → * → * → ALLOW                │
│   - Subject NO MATCH: "user-1" ≠ "superuser"    │
│                                                  │
│ Rule 2: user-1 → document:1 → read → ALLOW      │
│   - Subject matches: "user-1" = "user-1" ✓      │
│   - Resource NO MATCH: document:1 ≠ document-999│
│                                                  │
│ No rules match!                                  │
└──────────────────────────────────────────────────┘
                 ▼
Step 3: Default DENY
┌─────────────────────────────────┐
│ Result: DENY                    │
│ Reason: No matching rules       │
└─────────────────────────────────┘
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

## Common Mistakes

### ❌ Mistake 1: Using User ID Instead of Special Identifier

**Problem**: Setting `$subject->id()` to user's database ID instead of special "superuser" identifier.

```php
// DON'T: Using database ID as subject ID
Patrol::resolveSubject(function () {
    $user = auth()->user();

    return new Subject(
        $user->id,  // ❌ Wrong! Returns "123", not "superuser"
        ['is_superuser' => $user->is_superuser]
    );
});

// Policy rule won't match
new PolicyRule('superuser', '*', '*', Effect::Allow);

// User with ID 123 tries to access - DENIED (subject "123" ≠ "superuser")
```

**Why it's wrong**: The wildcard rule matches against `subject.id()`. If the ID is "123", it won't match "superuser" pattern.

**✅ DO THIS:**
```php
// Correct: Return "superuser" as subject ID
Patrol::resolveSubject(function () {
    $user = auth()->user();

    if ($user?->is_superuser) {
        return new Subject('superuser');  // ✓ Special identifier
    }

    return new Subject($user->id);  // Regular user ID
});
```

---

### ❌ Mistake 2: No Audit Logging for Superuser Actions

**Problem**: Superuser actions go untracked, creating security blind spots.

```php
// DON'T: No logging for powerful actions
class AdminController extends Controller
{
    public function deleteAllUsers()
    {
        if (!Patrol::check(new Resource('users', 'users'), 'delete-all')) {
            abort(403);
        }

        User::query()->delete();  // ❌ No record of who did this!

        return redirect()->back();
    }
}
```

**Why it's wrong**: Superusers can perform destructive actions with no accountability. If something goes wrong, you can't identify who did it.

**✅ DO THIS:**
```php
// Correct: Log all superuser actions
class AdminController extends Controller
{
    public function deleteAllUsers()
    {
        if (!Patrol::check(new Resource('users', 'users'), 'delete-all')) {
            abort(403);
        }

        // Log before dangerous action
        Log::warning('Superuser deleted all users', [
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now(),
            'user_count' => User::count(),
        ]);

        User::query()->delete();

        return redirect()->back();
    }
}

// Or use AuditingPolicyEvaluator (see examples)
```

---

### ❌ Mistake 3: Permanent Superuser for Temporary Needs

**Problem**: Granting permanent superuser status for one-time tasks.

```php
// DON'T: Permanent superuser for temporary debugging
$user = User::find(5);
$user->update(['is_superuser' => true]);  // ❌ Never revoked!

// User performs debugging...
// User forgets to remove superuser flag
// User now has permanent elevated access (security risk!)
```

**Why it's wrong**: Temporary needs shouldn't result in permanent elevated privileges. Forgotten superuser flags are a major security risk.

**✅ DO THIS:**
```php
// Correct: Time-limited superuser grant
$temporaryService = app(TemporarySuperuserService::class);

// Grant for 30 minutes only
$temporaryService->grantTemporaryAccess($user, minutes: 30);

// Perform debugging...

// Access automatically expires after 30 minutes
// No manual revocation needed
```

---

### ❌ Mistake 4: No Separation Between Admin and Personal Accounts

**Problem**: Daily-use accounts also have superuser privileges.

```php
// DON'T: Same account for daily work and admin tasks
// john@company.com (is_superuser = true)
//   - Checks email
//   - Browses documentation
//   - Clicks random links (phishing risk!)
//   - Has full system access at all times ❌

$john = User::where('email', 'john@company.com')->first();
$john->is_superuser;  // true - always elevated!
```

**Why it's wrong**: Constant elevated privileges increase attack surface. If the account is compromised (phishing, XSS), attacker has full access.

**✅ DO THIS:**
```php
// Correct: Separate accounts for admin and daily use
// john@company.com (is_superuser = false) - Daily work
// john+admin@company.com (is_superuser = true) - Admin tasks only

// Only log into admin account when performing admin tasks
// Use MFA on admin account
// Short session timeout on admin account (15 min)
```

## Best Practices

1. **Minimize Superusers**: Only grant to essential personnel
2. **Separate Accounts**: Use dedicated admin accounts, not daily-use accounts
3. **Audit Everything**: Log all superuser actions with timestamps, IPs, and context
4. **Temporary Access**: Use time-limited superuser grants when possible
5. **Scope Appropriately**: Use resource or action scoping when full access isn't needed
6. **Multi-Factor Auth**: Require additional authentication for superuser accounts
7. **Regular Reviews**: Audit superuser list quarterly
8. **Short Sessions**: Implement aggressive session timeouts (15-30 minutes)
9. **Revocation Process**: Have clear process to immediately revoke superuser access
10. **Alert on Grant**: Send notifications when superuser access is granted/used

## Debugging Superuser Issues

### Problem 1: "Superuser still denied access"

**Symptom**: User flagged as superuser still gets 403 errors.

**Diagnosis Steps**:

```php
// Step 1: Check subject ID in resolver
Patrol::resolveSubject(function () {
    $user = auth()->user();
    $subject = $user?->is_superuser ? new Subject('superuser') : new Subject($user->id);

    // Debug what subject ID is being used
    Log::debug('Subject ID', ['id' => $subject->id()]);
    // Should output: 'superuser' for admins

    return $subject;
});

// Step 2: Check policy contains superuser rule
$policy = app(PolicyRepositoryInterface::class)->find();
$superuserRule = collect($policy->rules())->first(fn($rule) =>
    $rule->subject === 'superuser'
);

dd($superuserRule);
// Should output: PolicyRule(subject: 'superuser', resource: '*', action: '*')
```

**Common Causes**:
- Subject ID is user's database ID (e.g., "123") instead of "superuser"
- No superuser rule in policy
- Typo in subject pattern ("super-user" vs "superuser")

**Fix**: Ensure resolver returns `new Subject('superuser')` for admins.

---

### Problem 2: "How do I know if superuser rule was used?"

**Symptom**: Need to verify if access was granted via superuser bypass.

**Diagnosis Steps**:

```php
// Check matched rules in evaluator
$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());
$result = $evaluator->evaluate($policy, $subject, $resource, $action);

// Get matched rules
$matchedRules = $evaluator->getMatchedRules();

foreach ($matchedRules as $rule) {
    if ($rule->subject === 'superuser') {
        Log::info('Access granted via superuser bypass', [
            'resource' => $resource->id(),
            'action' => $action->name(),
        ]);
    }
}
```

**Solution**: Wrap PolicyEvaluator with AuditingPolicyEvaluator (see Security Considerations).

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
