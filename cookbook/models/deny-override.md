# Deny-Override

Explicit deny rules that override all allow rules for security-critical access control.

## Overview

Deny-Override is a pattern where explicit DENY rules take precedence over ALLOW rules, regardless of order or specificity. This creates a security model where you can grant broad permissions while maintaining the ability to explicitly block specific actions, ensuring that critical restrictions cannot be bypassed.

## Basic Concept

```
DENY always wins
Allow + Deny = Deny
```

## How Deny-Override Works

```
Step 1: Evaluate all matching rules (both Allow and Deny)
┌─────────────────────────────────────────────────┐
│ Rule 1: Allow admin → * → * (ALLOW)             │
│ Rule 2: Deny * → critical:* → delete (DENY)     │
│ Rule 3: Allow user-1 → doc:* → read (ALLOW)     │
└─────────────────────────────────────────────────┘

Step 2: Check if ANY rule is DENY
┌────────────────────────────────────┐
│ Found matching rules:              │
│ ✅ ALLOW (admin can do everything) │
│ ❌ DENY (critical resources)       │
└────────────────────────────────────┘

Step 3: DENY wins (even if allows exist)
        ┌──────────────────┐
        │  Any DENY found? │
        └──────────────────┘
               │
        ┌──────┴──────┐
        │             │
       YES           NO
        │             │
        ▼             ▼
    ┌───────┐    ┌───────┐
    │ DENY  │    │ ALLOW │
    └───────┘    └───────┘
     (wins)    (only if no denies)

Example:
Admin tries to delete critical:config
├─ Match: role:admin → * → * (ALLOW) ✅
├─ Match: * → critical:* → delete (DENY) ❌
└─ Result: DENY (deny overrides allow)
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

## Common Mistakes

### ❌ Mistake 1: Not Using DenyOverrideEffectResolver

```php
// DON'T: Using standard resolver
$evaluator = new PolicyEvaluator(
    new AclRuleMatcher(),
    new EffectResolver() // ❌ Won't prioritize denies!
);
```

**✅ DO THIS:**
```php
// Use DenyOverrideEffectResolver
$evaluator = new PolicyEvaluator(
    new AclRuleMatcher(),
    new DenyOverrideEffectResolver() // ✅ Deny always wins
);
```

---

### ❌ Mistake 2: Too Many Deny Rules

```php
// DON'T: Deny everything, then allow exceptions
new PolicyRule('*', '*', '*', Effect::Deny), // ❌ Blocks everything!
new PolicyRule('admin', 'doc:1', 'read', Effect::Allow), // Won't work!
```

**Why it's wrong:** Deny overrides allow, so nothing works.

**✅ DO THIS:**
```php
// Allow by default, deny specific critical cases
new PolicyRule('role:editor', 'doc:*', '*', Effect::Allow),
new PolicyRule('*', 'doc:confidential:*', 'read', Effect::Deny), // ✅ Selective deny
```

---

### ❌ Mistake 3: Unclear Deny Reasons

```php
// DON'T: Generic deny
new PolicyRule('*', 'system:*', 'delete', Effect::Deny);
// User gets: "403 Forbidden" - why?
```

**✅ DO THIS:**
```php
new PolicyRule('*', 'system:*', 'delete', Effect::Deny, metadata: [
    'reason' => 'System resources cannot be deleted for data integrity',
    'contact' => 'admin@example.com',
]);

// Return helpful error
if ($result === Effect::Deny) {
    $metadata = $matchedRule->metadata;
    throw new ForbiddenException($metadata['reason']);
}
```

---

### ❌ Mistake 4: Forgetting to Test Deny Override

```php
// Developer adds allow rule, forgets there's a deny
new PolicyRule('role:admin', '*', '*', Effect::Allow); // Added this
// Forgets about:
new PolicyRule('*', 'audit-log:*', 'delete', Effect::Deny); // Existing!

// Admin tries to delete audit log
// Result: DENIED (deny overrides the allow)
```

**✅ DO THIS:**
```php
// Always test that denies work
it('denies even for admins on critical resources', function () {
    $policy = new Policy([
        new PolicyRule('role:admin', '*', '*', Effect::Allow),
        new PolicyRule('*', 'critical:*', 'delete', Effect::Deny),
    ]);

    $result = $evaluator->evaluate(
        $policy,
        new Subject('admin-1', ['roles' => ['admin']]),
        new Resource('critical:config', 'system'),
        new Action('delete')
    );

    expect($result)->toBe(Effect::Deny); // ✅ Deny wins
});
```

---

## Best Practices

1. **Document Deny Rules**: Always include metadata explaining why
2. **Minimal Denies**: Use sparingly, only for critical restrictions
3. **Audit Everything**: Log all deny override decisions with context
4. **Clear Messages**: Return specific error messages with deny reasons
5. **Review Regularly**: Audit deny rules quarterly
6. **Test Thoroughly**: Ensure denies cannot be bypassed
7. **Use Correct Resolver**: Always use DenyOverrideEffectResolver
8. **Metadata**: Include reason, severity, contact in deny rules
9. **Default Allow**: Prefer allowing by default, deny exceptions
10. **Emergency Access**: Plan for emergency bypass if needed

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

## Debugging Deny Issues

### Problem: Getting denied unexpectedly

**Step 1: Check for deny rules**
```php
$policy->rules()->filter(fn($rule) => $rule->effect === Effect::Deny);
// List all deny rules - is there a match?
```

**Step 2: Log all matching rules**
```php
$matches = [];
foreach ($policy->rules() as $rule) {
    if ($matcher->matches($rule, $subject, $resource, $action)) {
        $matches[] = [
            'rule' => $rule,
            'effect' => $rule->effect->value,
        ];
    }
}
dd($matches); // Check: Is there a DENY in the list?
```

**Step 3: Verify resolver type**
```php
// Make sure you're using DenyOverrideEffectResolver
$evaluator = new PolicyEvaluator(
    $matcher,
    new DenyOverrideEffectResolver() // ✅ Must be this one!
);
```

---

### Problem: Deny not working (still allows)

**Check resolver:**
```php
// ❌ Wrong: Standard resolver (all allows required)
new EffectResolver()

// ✅ Right: Deny override resolver
new DenyOverrideEffectResolver()
```

---

## Related Models

- [ACL](./acl.md) - Basic permissions with deny-override
- [RBAC](./rbac.md) - Role-based with security denies
- [ABAC](./abac.md) - Conditional denies
- [Priority-Based](./priority-based.md) - Ordered rule evaluation
