# ACL without Resources

Permission types instead of specific resources for action-based authorization.

## Overview

ACL without Resources is a permission model where you define what types of actions users can perform system-wide, rather than tying permissions to specific resources. This creates a simpler model focused on capabilities (e.g., "write-article", "read-log") instead of resource-action pairs.

## Basic Concept

```
Subject + Permission Type = Capability
```

## How Capability-Based Authorization Works

```
Step 1: Incoming authorization check
┌────────────────────────────────────────┐
│ Subject: user-1                        │
│   - Subscription: "pro"                │
│   - Capabilities: [...]                │
│                                        │
│ Resource: * (wildcard - ignored!)      │
│ Action: export-pdf (the capability)    │
└────────────────────────────────────────┘
                 ▼
Step 2: Match capability rule
┌─────────────────────────────────────────────────┐
│ Rule 1: user-1 → * → export-pdf → ALLOW         │
│   - Subject matches: user-1 = user-1 ✓          │
│   - Resource: * (wildcard - matches any) ✓      │
│   - Action matches: export-pdf = export-pdf ✓   │
│   - Effect: ALLOW                               │
└─────────────────────────────────────────────────┘
                 ▼
Step 3: Grant capability (regardless of resource)
┌─────────────────────────────────┐
│ Result: ALLOW                   │
│ Reason: User has capability     │
│ Resource: N/A (not checked)     │
└─────────────────────────────────┘
```

**Key Insight**: The resource field is ALWAYS `*` (wildcard). Authorization checks **capabilities** (action names) instead of resource-action pairs.

### SaaS Tier Comparison

```
Feature/Capability         Free    Pro    Enterprise
┌────────────────────────┬───────┬──────┬────────────┐
│ create-basic-project   │   ✓   │  ✓   │     ✓      │
│ upload-file            │   ✓   │  ✓   │     ✓      │
│ export-csv             │   ✓   │  ✓   │     ✓      │
├────────────────────────┼───────┼──────┼────────────┤
│ export-pdf             │   ✗   │  ✓   │     ✓      │
│ advanced-analytics     │   ✗   │  ✓   │     ✓      │
│ api-access             │   ✗   │  ✓   │     ✓      │
│ priority-support       │   ✗   │  ✓   │     ✓      │
├────────────────────────┼───────┼──────┼────────────┤
│ custom-integrations    │   ✗   │  ✗   │     ✓      │
│ dedicated-support      │   ✗   │  ✗   │     ✓      │
│ sla-guarantee          │   ✗   │  ✗   │     ✓      │
└────────────────────────┴───────┴──────┴────────────┘
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

## Common Mistakes

### ❌ Mistake 1: Mixing Resource-Specific Rules with Capabilities

**Problem**: Using specific resources instead of wildcards breaks the capability model.

```php
// DON'T: Mix specific resources with capabilities
$policy = new Policy([
    new PolicyRule('user-1', '*', 'export-pdf', Effect::Allow),  // ✓ Capability (correct)
    new PolicyRule('user-1', 'report:123', 'export-pdf', Effect::Allow),  // ❌ Wrong! Resource-specific
]);

// Confusion: Does user have export-pdf capability or not?
// Check 1: Wildcard resource - ALLOWED
Patrol::check(new Resource('*', 'any'), 'export-pdf');  // ALLOW

// Check 2: Different resource - depends on rule order!
Patrol::check(new Resource('report-456', 'report'), 'export-pdf');  // ??? Unclear
```

**Why it's wrong**: Mixes two authorization paradigms (capability-based + resource-based), creating confusing behavior.

**✅ DO THIS:**
```php
// Correct: Use ONLY wildcard resources for capabilities
$policy = new Policy([
    new PolicyRule('user-1', '*', 'export-pdf', Effect::Allow),  // ✓ Capability applies everywhere
]);

// Consistent checks
Patrol::check(new Resource('*', 'any'), 'export-pdf');  // ALLOW
Patrol::check(new Resource('report-456', 'report'), 'export-pdf');  // ALLOW (wildcard matches)
```

---

### ❌ Mistake 2: Not Inheriting Lower Tier Capabilities

**Problem**: Higher subscription tiers missing capabilities from lower tiers.

```php
// DON'T: Pro tier missing free tier capabilities!
$policy = new Policy([
    // Free tier
    new PolicyRule('free-user', '*', 'create-project', Effect::Allow),
    new PolicyRule('free-user', '*', 'upload-file', Effect::Allow),

    // Pro tier (missing basic capabilities!)
    new PolicyRule('pro-user', '*', 'export-pdf', Effect::Allow),  // ❌ Only this!
    new PolicyRule('pro-user', '*', 'api-access', Effect::Allow),
]);

// Pro user tries to create project - DENIED (wrong!)
$subject = new Subject('pro-user');
Patrol::check(new Resource('*', 'any'), 'create-project');  // DENY (missing rule!)
```

**Why it's wrong**: Upgrading to Pro tier removes basic free tier features. Users expect higher tiers to have ALL lower tier capabilities.

**✅ DO THIS:**
```php
// Correct: Higher tiers include ALL lower tier capabilities
$policy = new Policy([
    // Free tier capabilities
    new PolicyRule('free-user', '*', 'create-project', Effect::Allow),
    new PolicyRule('free-user', '*', 'upload-file', Effect::Allow),

    // Pro tier includes free + additional
    new PolicyRule('pro-user', '*', 'create-project', Effect::Allow),  // ✓ Inherited
    new PolicyRule('pro-user', '*', 'upload-file', Effect::Allow),     // ✓ Inherited
    new PolicyRule('pro-user', '*', 'export-pdf', Effect::Allow),      // New capability
    new PolicyRule('pro-user', '*', 'api-access', Effect::Allow),      // New capability
]);

// Or use helper to auto-generate inherited rules
```

---

### ❌ Mistake 3: Inconsistent Capability Naming

**Problem**: Random naming conventions make capabilities hard to discover and maintain.

```php
// DON'T: Inconsistent naming
$policy = new Policy([
    new PolicyRule('user-1', '*', 'exportPDF', Effect::Allow),        // ❌ camelCase
    new PolicyRule('user-1', '*', 'WriteArticle', Effect::Allow),     // ❌ PascalCase
    new PolicyRule('user-1', '*', 'access_crm', Effect::Allow),       // ❌ snake_case
    new PolicyRule('user-1', '*', 'view-logs', Effect::Allow),        // ❌ kebab-case
    new PolicyRule('user-1', '*', 'APPROVE_REQUEST', Effect::Allow),  // ❌ SCREAMING_SNAKE
]);

// Which naming to use when checking?
Patrol::check(new Resource('*', 'any'), 'exportPDF');  // or 'export-pdf'? or 'export_pdf'?
```

**Why it's wrong**: No consistent pattern makes capabilities hard to remember and prone to typos.

**✅ DO THIS:**
```php
// Correct: Consistent kebab-case, verb-noun format
$policy = new Policy([
    new PolicyRule('user-1', '*', 'export-pdf', Effect::Allow),        // ✓ Consistent
    new PolicyRule('user-1', '*', 'write-article', Effect::Allow),     // ✓ Consistent
    new PolicyRule('user-1', '*', 'access-crm', Effect::Allow),        // ✓ Consistent
    new PolicyRule('user-1', '*', 'view-logs', Effect::Allow),         // ✓ Consistent
    new PolicyRule('user-1', '*', 'approve-request', Effect::Allow),   // ✓ Consistent
]);

// Always use same format: {verb}-{noun}
// Examples: create-project, delete-user, export-report, access-module
```

---

### ❌ Mistake 4: Using Actual Resources in Checks

**Problem**: Passing actual resource objects instead of wildcard placeholders.

```php
// DON'T: Pass actual resource when checking capabilities
$report = Report::find(1);
$resource = new Resource("report-{$report->id}", 'report');  // ❌ Specific resource

if (Patrol::check($resource, 'export-pdf')) {
    // Will work if wildcard rule exists, but semantically wrong
    // Capability shouldn't depend on specific resource!
}
```

**Why it's wrong**: Capability checks shouldn't care about specific resources. This creates confusion about whether you're checking a capability or resource permission.

**✅ DO THIS:**
```php
// Correct: Always use wildcard placeholder for capability checks
if (Patrol::check(new Resource('*', 'any'), 'export-pdf')) {
    // Clear: checking if user has export-pdf capability system-wide
    $report = Report::find(1);
    return $report->exportToPdf();
}

// Or create helper method
class CapabilityChecker
{
    public static function can(string $capability): bool
    {
        return Patrol::check(new Resource('*', 'any'), $capability);
    }
}

// Usage
if (CapabilityChecker::can('export-pdf')) {
    // ...
}
```

## Best Practices

1. **Clear Naming**: Use descriptive capability names (verb-noun format: write-article, export-pdf)
2. **Consistent Format**: Always use kebab-case for capability names
3. **Capability Grouping**: Group related capabilities by module or feature
4. **Documentation**: Maintain a capability registry documenting all available capabilities
5. **Tier Inheritance**: Structure tiers so higher tiers inherit lower tier capabilities
6. **Expiration**: Support time-limited capabilities for trials or temporary access
7. **Audit Trail**: Log capability grants and revocations
8. **Feature Discovery**: Provide UI to show available vs restricted capabilities
9. **Helper Methods**: Create helper methods to abstract wildcard resource creation
10. **Capability Registry**: Maintain a centralized list of all valid capabilities

## Debugging Capability Issues

### Problem 1: "User upgraded but still can't access premium features"

**Symptom**: User upgraded to Pro tier but premium capabilities still denied.

**Diagnosis Steps**:

```php
// Step 1: Check subject ID matches tier pattern
$subject = Patrol::currentSubject();
dd([
    'subject_id' => $subject->id(),  // Should be: 'pro-user' or user ID
    'subscription' => auth()->user()->subscription_tier,
]);

// Step 2: Check policy has rules for this tier
$policy = app(PolicyRepositoryInterface::class)->find();
$tierRules = collect($policy->rules())->filter(fn($rule) =>
    $rule->subject === 'pro-user' || $rule->subject === auth()->id()
);

dd([
    'matching_rules' => $tierRules->count(),  // Should be > 0
    'capabilities' => $tierRules->pluck('action')->unique(),
]);

// Step 3: Check cache hasn't stale data
Cache::tags(['patrol', 'user:' . auth()->id()])->flush();

// Try again after cache clear
Patrol::check(new Resource('*', 'any'), 'export-pdf');
```

**Common Causes**:
- Subject ID still reflects old tier (free-user)
- Policy not regenerated after tier change
- Cached policy contains old tier rules
- Missing capability rules for new tier

**Fix**: Clear cache when subscription changes, ensure subject resolver returns correct tier ID.

---

### Problem 2: "How to list all capabilities for a user?"

**Symptom**: Need to show user which features they have access to.

**Diagnosis Steps**:

```php
// Get all capabilities for current user
$policy = app(PolicyRepositoryInterface::class)->find();
$subject = Patrol::currentSubject();

$userCapabilities = collect($policy->rules())
    ->filter(fn($rule) =>
        $rule->subject === $subject->id() &&
        $rule->resource === '*' &&
        $rule->effect === Effect::Allow
    )
    ->pluck('action')
    ->unique()
    ->sort()
    ->values();

dd($userCapabilities->all());
// Output: ['create-project', 'export-csv', 'export-pdf', 'upload-file']
```

**Solution**: Create capability discovery service to enumerate available features per tier.

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
