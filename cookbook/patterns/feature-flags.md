# Feature Flags & Progressive Rollouts

Using Patrol for feature flags, beta access, and progressive rollouts — authorization-first feature management.

## Overview

Feature flags control access to features, which is fundamentally an authorization question: **"Can this user access feature X?"** Patrol handles this through attribute-based conditions and role-based rules, providing fine-grained control over feature access.

## Core Concept

```
Feature Access = Authorization Decision
"Can user access feature X?" → Patrol evaluates → Allow/Deny
```

Traditional feature flags answer "is feature enabled?", while Patrol answers "can THIS user access feature X?" — providing user-level, percentage-based, and attribute-driven feature control.

## Basic Patterns

### Simple Feature Toggle

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect};

$policy = new Policy([
    // Beta users can access new UI
    new PolicyRule(
        subject: 'role:beta-tester',
        resource: 'feature:new-dashboard',
        action: 'access',
        effect: Effect::Allow
    ),

    // Premium users can export PDFs
    new PolicyRule(
        subject: 'tier:premium',
        resource: 'feature:pdf-export',
        action: 'use',
        effect: Effect::Allow
    ),
]);
```

### Attribute-Based Feature Access

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect};

$policy = new Policy([
    // Beta access for opted-in users
    new ConditionalPolicyRule(
        condition: 'subject.beta_access == true',
        resource: 'feature:experimental-*',
        action: 'access',
        effect: Effect::Allow
    ),

    // Feature available to users with specific plan
    new ConditionalPolicyRule(
        condition: 'subject.plan_features contains "advanced-analytics"',
        resource: 'feature:analytics-dashboard',
        action: 'access',
        effect: Effect::Allow
    ),
]);
```

## Progressive Rollout Patterns

### Percentage-Based Rollout

```php
// 25% rollout using user ID modulo
new ConditionalPolicyRule(
    condition: 'subject.id % 100 < 25',
    resource: 'feature:new-search',
    action: 'access',
    effect: Effect::Allow
);

// Consistent bucketing using hash
new ConditionalPolicyRule(
    condition: 'hash(subject.id + "new-feature") % 100 < 50',
    resource: 'feature:redesign',
    action: 'access',
    effect: Effect::Allow
);
```

### Time-Based Feature Rollout

```php
// Feature available after specific date
new ConditionalPolicyRule(
    condition: 'environment.date >= "2025-11-01"',
    resource: 'feature:winter-sale',
    action: 'access',
    effect: Effect::Allow
);

// Limited-time beta access
new ConditionalPolicyRule(
    condition: 'environment.date between "2025-10-01" and "2025-10-31" && subject.beta_tester == true',
    resource: 'feature:october-preview',
    action: 'access',
    effect: Effect::Allow
);
```

### Tiered Rollout Strategy

```php
// Phase 1: Internal team only
new ConditionalPolicyRule(
    condition: 'subject.email endsWith "@company.com"',
    resource: 'feature:internal-preview',
    action: 'access',
    effect: Effect::Allow
);

// Phase 2: Beta testers (10%)
new ConditionalPolicyRule(
    condition: 'subject.beta_tester == true || subject.id % 100 < 10',
    resource: 'feature:beta-release',
    action: 'access',
    effect: Effect::Allow
);

// Phase 3: All premium users
new ConditionalPolicyRule(
    condition: 'subject.tier in ["premium", "enterprise"]',
    resource: 'feature:general-availability',
    action: 'access',
    effect: Effect::Allow
);
```

## Real-World Examples

### SaaS Feature Tiers

```php
use Patrol\Core\ValueObjects\{Policy, PolicyRule, Effect};

$policy = new Policy([
    // Free tier features
    new PolicyRule(
        subject: 'tier:free',
        resource: 'feature:basic-editor',
        action: 'use',
        effect: Effect::Allow
    ),

    // Pro tier features
    new PolicyRule(
        subject: 'tier:pro',
        resource: 'feature:advanced-editor',
        action: 'use',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'tier:pro',
        resource: 'feature:export-pdf',
        action: 'use',
        effect: Effect::Allow
    ),

    // Enterprise tier features
    new PolicyRule(
        subject: 'tier:enterprise',
        resource: 'feature:*', // All features
        action: 'use',
        effect: Effect::Allow
    ),
]);
```

### Beta Program Management

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect};

$policy = new Policy([
    // Explicit beta participants
    new ConditionalPolicyRule(
        condition: 'subject.beta_programs contains "new-api"',
        resource: 'feature:api-v2',
        action: 'access',
        effect: Effect::Allow
    ),

    // Early adopters (joined before date)
    new ConditionalPolicyRule(
        condition: 'subject.created_at < "2025-01-01" && subject.early_adopter == true',
        resource: 'feature:early-access-*',
        action: 'access',
        effect: Effect::Allow
    ),

    // Employee override
    new ConditionalPolicyRule(
        condition: 'subject.role == "employee"',
        resource: 'feature:*',
        action: 'access',
        effect: Effect::Allow
    ),
]);
```

### A/B Testing with Patrol

```php
// Variant A: 50% of users
new ConditionalPolicyRule(
    condition: 'hash(subject.id + "experiment-checkout") % 2 == 0',
    resource: 'feature:checkout-variant-a',
    action: 'access',
    effect: Effect::Allow
);

// Variant B: Other 50%
new ConditionalPolicyRule(
    condition: 'hash(subject.id + "experiment-checkout") % 2 == 1',
    resource: 'feature:checkout-variant-b',
    action: 'access',
    effect: Effect::Allow
);

// Control: Override for QA team
new ConditionalPolicyRule(
    condition: 'subject.role == "qa"',
    resource: 'feature:checkout-variant-*',
    action: 'access',
    effect: Effect::Allow
);
```

### Geographic Rollout

```php
// US-only feature initially
new ConditionalPolicyRule(
    condition: 'subject.country == "US"',
    resource: 'feature:us-payment-methods',
    action: 'access',
    effect: Effect::Allow
);

// EU rollout with GDPR compliance check
new ConditionalPolicyRule(
    condition: 'subject.country in ["DE", "FR", "UK"] && subject.gdpr_consent == true',
    resource: 'feature:eu-data-processing',
    action: 'access',
    effect: Effect::Allow
);

// Global availability (phased by timezone)
new ConditionalPolicyRule(
    condition: 'environment.date >= subject.region_rollout_date',
    resource: 'feature:global-launch',
    action: 'access',
    effect: Effect::Allow
);
```

## Laravel Integration

### Feature Access in Controllers

```php
use Patrol\Laravel\Patrol;

class DashboardController extends Controller
{
    public function newDashboard(Request $request)
    {
        // Check feature access
        if (!Patrol::check('access', 'feature:new-dashboard')) {
            return redirect()->route('dashboard.old');
        }

        return view('dashboard.new');
    }

    public function exportPdf(Request $request)
    {
        // Authorize feature usage
        Patrol::authorize('use', 'feature:pdf-export');

        return PDF::download($request->user()->report);
    }
}
```

### Blade Directives for Features

```blade
@can('access', 'feature:beta-ui')
    <div class="beta-banner">
        You're using our new beta interface!
    </div>
@endcan

@can('use', 'feature:advanced-search')
    <button wire:click="openAdvancedSearch()">
        Advanced Search
    </button>
@else
    <a href="/upgrade">Upgrade for Advanced Search</a>
@endcan

@can('access', 'feature:dark-mode')
    <toggle-dark-mode />
@endcan
```

### Middleware for Feature Gates

```php
// Route gating by feature access
Route::middleware('patrol:feature:api-v2,access')
    ->prefix('api/v2')
    ->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
```

### Feature Helper

```php
namespace App\Helpers;

use Patrol\Laravel\Patrol;

class Feature
{
    public static function enabled(string $feature): bool
    {
        return Patrol::check('access', "feature:{$feature}");
    }

    public static function canUse(string $feature): bool
    {
        return Patrol::check('use', "feature:{$feature}");
    }

    public static function variant(string $experiment): ?string
    {
        $variants = ['a', 'b', 'c'];

        foreach ($variants as $variant) {
            if (Patrol::check('access', "feature:{$experiment}-{$variant}")) {
                return $variant;
            }
        }

        return null;
    }
}

// Usage in code
if (Feature::enabled('new-editor')) {
    return view('editor.new');
}

$variant = Feature::variant('checkout-experiment');
return view("checkout.{$variant}");
```

## Advanced Patterns

### Feature Dependencies

```php
// Advanced analytics requires both base analytics AND premium tier
new ConditionalPolicyRule(
    condition: 'subject.has_feature("analytics") && subject.tier == "premium"',
    resource: 'feature:advanced-analytics',
    action: 'access',
    effect: Effect::Allow
);

// API v2 requires v1 deprecation acknowledgment
new ConditionalPolicyRule(
    condition: 'subject.acknowledged_api_v1_deprecation == true',
    resource: 'feature:api-v2',
    action: 'access',
    effect: Effect::Allow
);
```

### Kill Switch Pattern

```php
// Emergency disable for specific feature
new ConditionalPolicyRule(
    condition: 'environment.feature_flags["new-payment"] == "disabled"',
    resource: 'feature:new-payment-flow',
    action: 'access',
    effect: Effect::Deny // Explicit deny
);

// Override for debugging
new ConditionalPolicyRule(
    condition: 'subject.role == "admin" && environment.debug_mode == true',
    resource: 'feature:*',
    action: 'access',
    effect: Effect::Allow,
    priority: Priority::High // Takes precedence
);
```

### Quota-Based Features

```php
// Feature available until usage quota exceeded
new ConditionalPolicyRule(
    condition: 'subject.api_calls_this_month < subject.plan_api_limit',
    resource: 'feature:api-access',
    action: 'use',
    effect: Effect::Allow
);

// Storage-based feature access
new ConditionalPolicyRule(
    condition: 'subject.storage_used_gb < subject.storage_limit_gb',
    resource: 'feature:file-upload',
    action: 'use',
    effect: Effect::Allow
);
```

## Testing Feature Flags

### Test Different Rollout Scenarios

```php
use Tests\TestCase;
use Patrol\Core\ValueObjects\{Policy, ConditionalPolicyRule, Effect, Subject, Action};

class FeatureFlagTest extends TestCase
{
    /** @test */
    public function beta_users_can_access_new_feature()
    {
        $policy = new Policy([
            new ConditionalPolicyRule(
                condition: 'subject.beta_tester == true',
                resource: 'feature:new-ui',
                action: 'access',
                effect: Effect::Allow
            ),
        ]);

        $betaUser = new Subject('user-1', ['beta_tester' => true]);
        $regularUser = new Subject('user-2', ['beta_tester' => false]);

        $result = $evaluator->evaluate($policy, $betaUser, 'feature:new-ui', 'access');
        expect($result)->toBe(Effect::Allow);

        $result = $evaluator->evaluate($policy, $regularUser, 'feature:new-ui', 'access');
        expect($result)->toBe(Effect::Deny);
    }

    /** @test */
    public function percentage_rollout_is_consistent()
    {
        $policy = new Policy([
            new ConditionalPolicyRule(
                condition: 'subject.id % 100 < 25',
                resource: 'feature:25-percent-rollout',
                action: 'access',
                effect: Effect::Allow
            ),
        ]);

        // User 15 should have access (15 < 25)
        $user15 = new Subject('user-15', ['id' => 15]);
        $result = $evaluator->evaluate($policy, $user15, 'feature:25-percent-rollout', 'access');
        expect($result)->toBe(Effect::Allow);

        // User 50 should NOT have access (50 >= 25)
        $user50 = new Subject('user-50', ['id' => 50]);
        $result = $evaluator->evaluate($policy, $user50, 'feature:25-percent-rollout', 'access');
        expect($result)->toBe(Effect::Deny);
    }
}
```

### Laravel Feature Tests

```php
class FeatureAccessTest extends TestCase
{
    /** @test */
    public function premium_users_can_export_pdf()
    {
        $premiumUser = User::factory()->premium()->create();

        $this->actingAs($premiumUser)
            ->get('/reports/export-pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function free_users_cannot_export_pdf()
    {
        $freeUser = User::factory()->free()->create();

        $this->actingAs($freeUser)
            ->get('/reports/export-pdf')
            ->assertForbidden();
    }

    /** @test */
    public function feature_gates_render_correctly()
    {
        $betaUser = User::factory()->betaTester()->create();

        $this->actingAs($betaUser)
            ->get('/dashboard')
            ->assertSee('You\'re using our new beta interface!');
    }
}
```

## Policy Builders for Features

```php
use Patrol\Laravel\Builders\FeatureFlagPolicyBuilder;

$policy = FeatureFlagPolicyBuilder::make()
    // Simple role-based features
    ->feature('pdf-export')->forRole('premium')
    ->feature('api-access')->forRole('enterprise')

    // Percentage rollout
    ->feature('new-ui')->rolloutPercentage(25)

    // Attribute-based access
    ->feature('beta-features')->when('subject.beta_tester == true')

    // Time-based availability
    ->feature('black-friday-deals')->between('2025-11-24', '2025-11-30')

    // Geographic restrictions
    ->feature('us-only-feature')->forCountries(['US', 'CA'])

    ->build();
```

## CLI Tools for Feature Management

```bash
# Check if user can access feature
php artisan patrol:check user:123 feature:new-dashboard access

# Explain why user cannot access feature
php artisan patrol:explain user:456 feature:pdf-export use

# List all feature flags in policies
php artisan patrol:list-rules --resource='feature:*'

# Test feature rollout percentage
php artisan patrol:test-rollout feature:new-ui --percentage=25
```

## Comparison with Traditional Feature Flags

| Aspect | Traditional Flags | Patrol Feature Flags |
|--------|------------------|---------------------|
| Scope | Global on/off | User-level authorization |
| Rules | Boolean flags | Rich conditions & attributes |
| Rollout | Percentage of traffic | Percentage + attributes + roles |
| Integration | Separate system | Part of authorization |
| Audit | Limited | Full policy audit trail |
| Testing | Mock flag values | Test authorization policies |
| Multi-tenancy | Complex setup | Native domain support |

## Best Practices

1. **Use consistent naming**: `feature:feature-name` for all features
2. **Separate actions**: `access` for availability, `use` for usage quota/limits
3. **Combine with RBAC**: Roles + feature flags for powerful combinations
4. **Version features**: `feature:api-v2`, `feature:ui-redesign-2025`
5. **Audit trail**: File-based policies for version control of feature changes
6. **Kill switches**: Always include emergency disable conditions
7. **Test extensively**: Verify rollout percentages and edge cases
8. **Document dependencies**: When features require other features

## When to Use Patrol vs Traditional Feature Flags

**Use Patrol when:**
- Feature access varies by user attributes
- Need fine-grained rollout control (roles, tiers, geographic)
- Want authorization and features in one system
- Require audit trail for feature access changes
- Multi-tenant feature isolation needed

**Use Traditional Flags when:**
- Simple global on/off toggles
- No user-level differentiation needed
- Operational toggles (not user-facing)
- Need real-time flag updates without deployments

## Related Patterns

- **[ABAC](../models/abac.md)** - Attribute-based conditions for dynamic access
- **[RBAC](../models/rbac.md)** - Role-based feature tiers
- **[ACL without Resources](../models/acl-without-resources.md)** - Capability-based permissions
- **[Priority-Based](../models/priority-based.md)** - Kill switches and overrides
