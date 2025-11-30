# Priority-Based Authorization

Firewall-style rule ordering where rules are evaluated by priority.

## Overview

Priority-Based authorization evaluates rules in a specific order based on priority levels, similar to firewall rules. The first matching rule determines the outcome. This model is useful when you need explicit control over rule evaluation order, especially in complex scenarios with overlapping rules.

## Basic Concept

```
Rules evaluated by priority (highest first)
First match wins
```

## How Priority-Based Works

```
Step 1: Incoming authorization check
┌────────────────────────────────────────┐
│ Subject: user-1 (role: employee)       │
│ Resource: internal:database            │
│ Action: access                         │
└────────────────────────────────────────┘
                 ▼
Step 2: Load ALL rules, sort by priority (DESC)
┌──────────────────────────────────────────────────────┐
│ Priority 1000 │ [DENY] Emergency lockdown            │
│ Priority  900 │ [DENY] Banned users                  │
│ Priority  800 │ [ALLOW] Super admin bypass           │
│ Priority  700 │ [DENY] VPN required for internal     │◄─ FIRST MATCH!
│ Priority  500 │ [ALLOW] Department-specific          │
│ Priority  300 │ [ALLOW] Role-based permissions       │
│ Priority  100 │ [ALLOW] Default read access          │
│ Priority    0 │ [DENY] Default deny all              │
└──────────────────────────────────────────────────────┘
                 ▼
Step 3: Evaluate rules from highest to lowest priority
┌─────────────────────────────────────────┐
│ Priority 1000: Does NOT match           │
│ Priority  900: Does NOT match           │
│ Priority  800: Does NOT match           │
│ Priority  700: ✓ MATCHES!               │◄─ STOP HERE
│   - Subject matches: * (wildcard)       │
│   - Resource matches: internal:*        │
│   - Action matches: * (wildcard)        │
│   - Condition: user NOT on VPN          │
└─────────────────────────────────────────┘
                 ▼
Step 4: Return the effect of FIRST matching rule
┌─────────────────────────────┐
│ Result: DENY                │
│ Reason: VPN required        │
│ Priority: 700               │
└─────────────────────────────┘
```

**Key Insight**: Unlike other models that evaluate all rules, Priority-Based stops at the **first match**. Higher priority rules act as "circuit breakers" that prevent lower priority rules from being evaluated.

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

## Common Mistakes

### ❌ Mistake 1: Not Using PriorityRuleMatcher

**Problem**: Using `AclRuleMatcher` instead of `PriorityRuleMatcher` ignores priority ordering.

```php
// DON'T: Wrong matcher - ignores priority!
$evaluator = new PolicyEvaluator(
    new AclRuleMatcher(),  // ❌ Evaluates ALL matching rules
    new EffectResolver()
);

$policy = new Policy([
    new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(10)),
    new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(100)),
]);

// Admin gets DENY (wrong!) because AclRuleMatcher collects both rules
```

**Why it's wrong**: `AclRuleMatcher` collects ALL matching rules and then resolves them. It doesn't stop at first match.

**✅ DO THIS:**
```php
// Correct matcher - respects priority
$evaluator = new PolicyEvaluator(
    new PriorityRuleMatcher(),  // ✓ Stops at first match
    new EffectResolver()
);

// Admin gets ALLOW (correct!) because priority 100 rule is evaluated first
```

---

### ❌ Mistake 2: Conflicting Rules at Same Priority

**Problem**: Two rules with same priority but different effects create unpredictable behavior.

```php
// DON'T: Same priority, conflicting effects
new PolicyRule(
    subject: 'role:manager',
    resource: 'report:*',
    action: 'delete',
    effect: Effect::Allow,
    priority: new Priority(500)  // Same priority!
),
new PolicyRule(
    subject: 'role:manager',
    resource: 'report:*',
    action: 'delete',
    effect: Effect::Deny,
    priority: new Priority(500)  // Same priority!
),
```

**Why it's wrong**: Which rule gets evaluated first? Database ordering (ID) becomes the tiebreaker, which is unreliable.

**✅ DO THIS:**
```php
// Use different priorities for clear ordering
new PolicyRule(
    subject: 'role:manager',
    resource: 'report:archived:*',
    action: 'delete',
    effect: Effect::Deny,
    priority: new Priority(600)  // Higher priority deny for archived
),
new PolicyRule(
    subject: 'role:manager',
    resource: 'report:*',
    action: 'delete',
    effect: Effect::Allow,
    priority: new Priority(500)  // Lower priority allow for active
),
```

---

### ❌ Mistake 3: No Default Deny Rule

**Problem**: Missing low-priority default deny allows unexpected access.

```php
// DON'T: No catch-all deny
$policy = new Policy([
    new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(100)),
    new PolicyRule('role:user', 'public:*', 'read', Effect::Allow, priority: new Priority(50)),
    // ❌ What happens if no rules match? ALLOW by default!
]);

// Guest user accessing private data - no rule matches - ALLOWED (wrong!)
$result = $evaluator->evaluate(
    $policy,
    new Subject('guest'),
    new Resource('private-data', 'data'),
    new Action('delete')
);
// => Effect::Allow (no matching rule defaults to allow)
```

**Why it's wrong**: If no rules match, PolicyEvaluator defaults to ALLOW, creating a security hole.

**✅ DO THIS:**
```php
// Always add default deny at priority 0
$policy = new Policy([
    new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(100)),
    new PolicyRule('role:user', 'public:*', 'read', Effect::Allow, priority: new Priority(50)),
    new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(0)),  // ✓ Catch-all
]);

// Guest user - denied by default rule
```

---

### ❌ Mistake 4: Wrong Priority Scale

**Problem**: Using narrow priority ranges makes inserting new rules difficult.

```php
// DON'T: No room for new rules
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(3)),   // Emergency
new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(2)),  // Admin
new PolicyRule('role:user', '*', 'read', Effect::Allow, priority: new Priority(1)), // User
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(0)),   // Default

// Where do you add "banned users" rule? Between 3 and 2?
// Where do you add "VPN required"? No space!
```

**Why it's wrong**: Future requirements will need intermediate priorities. You'll have to renumber all rules.

**✅ DO THIS:**
```php
// Use wide gaps (100s) for easy insertion
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(1000)),  // Emergency
new PolicyRule('role:admin', '*', '*', Effect::Allow, priority: new Priority(800)), // Admin
new PolicyRule('role:user', '*', 'read', Effect::Allow, priority: new Priority(300)), // User
new PolicyRule('*', '*', '*', Effect::Deny, priority: new Priority(0)),     // Default

// Easy to insert new rules at 900 (banned), 700 (VPN), 500 (departments), etc.
```

## Best Practices

1. **Define Priority Levels**: Use constants for priority ranges (EMERGENCY=1000, CRITICAL=900, etc.)
2. **Use Wide Gaps**: Space priorities by 100+ to allow future insertions
3. **Document Priority Rationale**: Explain why each priority level was chosen
4. **Avoid Priority Conflicts**: Never use same priority for conflicting rules
5. **Default Deny**: Always have a low-priority (0) default deny rule
6. **Emergency Overrides**: Reserve highest priorities (900-1000) for emergencies
7. **Test Priority Order**: Thoroughly test rule evaluation order
8. **Audit Changes**: Log all priority rule additions/modifications
9. **Use Correct Matcher**: Always use `PriorityRuleMatcher`, not `AclRuleMatcher`
10. **Review Regularly**: Periodically review and consolidate priority rules

## Debugging Priority Rules

### Problem 1: "Admin gets denied but should be allowed"

**Symptom**: High-privilege users get unexpected denials.

**Diagnosis Steps**:

```php
// Step 1: Check which rule matched
$evaluator = new PolicyEvaluator(new PriorityRuleMatcher(), new EffectResolver());
$result = $evaluator->evaluate($policy, $subject, $resource, $action);

// Step 2: Get the matched rule details
$matchedRule = $evaluator->getMatchedRule();

dd([
    'result' => $result,
    'matched_priority' => $matchedRule?->priority?->value(),
    'matched_subject' => $matchedRule?->subject,
    'matched_effect' => $matchedRule?->effect->value,
]);

// Example output:
// [
//   'result' => 'deny',
//   'matched_priority' => 700,  ← Higher priority rule blocked it!
//   'matched_subject' => '*',
//   'matched_effect' => 'deny',
// ]
```

**Common Causes**:
- High-priority deny rule (emergency, banned, VPN) blocking access
- Admin rule has LOWER priority than blocking rule
- Condition on admin rule not met

**Fix**:
```php
// Solution: Put admin rule at HIGHER priority than blockers
new PolicyRule('role:super-admin', '*', '*', Effect::Allow, priority: new Priority(950)),  // Higher than VPN
new PolicyRule('*', 'internal:*', '*', Effect::Deny, priority: new Priority(700)),  // VPN rule
```

---

### Problem 2: "First match not stopping evaluation"

**Symptom**: Multiple rules seem to be evaluated instead of stopping at first match.

**Diagnosis Steps**:

```php
// Step 1: Verify you're using PriorityRuleMatcher
$evaluator = new PolicyEvaluator(
    new PriorityRuleMatcher(),  // ← MUST be PriorityRuleMatcher
    new EffectResolver()
);

// Step 2: Check actual matcher being used
$reflection = new \ReflectionClass($evaluator);
$matcherProperty = $reflection->getProperty('ruleMatcher');
$matcherProperty->setAccessible(true);
$actualMatcher = $matcherProperty->getValue($evaluator);

dd(get_class($actualMatcher));
// Should output: Patrol\Core\Engine\PriorityRuleMatcher
```

**Common Causes**:
- Using `AclRuleMatcher` instead of `PriorityRuleMatcher`
- Wrong matcher injected via container
- Multiple evaluators with different matchers

**Fix**:
```php
// Explicitly use PriorityRuleMatcher
use Patrol\Core\Engine\PriorityRuleMatcher;

$evaluator = new PolicyEvaluator(
    new PriorityRuleMatcher(),  // ✓ Correct
    new EffectResolver()
);
```

---

### Problem 3: "Same priority rules behaving inconsistently"

**Symptom**: Rules with same priority return different results on different requests.

**Diagnosis Steps**:

```php
// Step 1: Find conflicting priorities
$policy = app(PolicyRepositoryInterface::class)->find();
$rules = $policy->rules();

$priorityGroups = collect($rules)->groupBy(fn($rule) => $rule->priority?->value() ?? 0);

$conflicts = $priorityGroups->filter(function ($group) {
    // Check if same priority has different effects
    $effects = $group->pluck('effect.value')->unique();
    return $effects->count() > 1;
});

foreach ($conflicts as $priority => $rules) {
    dump("Priority {$priority} has conflicting rules:");
    foreach ($rules as $rule) {
        dump("  - {$rule->subject} → {$rule->resource} → {$rule->effect->value}");
    }
}
```

**Common Causes**:
- Multiple rules at same priority with different effects
- Database ordering (ID) becoming tiebreaker
- Race condition when inserting rules concurrently

**Fix**:
```php
// Assign unique priorities to conflicting rules
DB::table('priority_rules')
    ->where('priority', 500)
    ->where('effect', 'deny')
    ->update(['priority' => 510]);  // Bump deny to higher priority
```

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

## Related Models

- [Deny-Override](./deny-override.md) - Explicit deny rules
- [ABAC](./abac.md) - Conditional priority rules
- [ACL](./acl.md) - Simple priority ordering
- [RESTful](./restful.md) - API endpoint priorities
