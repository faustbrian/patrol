# Native Delegation Framework

Patrol's native delegation framework enables secure, temporary permission sharing between users with built-in validation, cycle detection, and audit trails.

## Overview

The delegation framework allows one user (delegator) to temporarily grant a subset of their permissions to another user (delegate). Unlike the raw approach of manually creating policy rules, this framework provides:

- **Automatic validation** - Ensures delegators can only delegate permissions they possess
- **Cycle detection** - Prevents circular delegation chains (A→B→C→A)
- **Expiration management** - Automatic cleanup of expired delegations
- **Audit trails** - Built-in metadata tracking and lifecycle management
- **Type safety** - Strongly-typed value objects prevent common errors

## Core Concepts

```
Delegator (has permissions) → Creates Delegation → Delegate (receives temporary permissions)
```

**Key Components:**
- `Delegation` - Immutable value object representing a delegation
- `DelegationScope` - Defines which resources and actions are delegated
- `DelegationManager` - Orchestrates delegation lifecycle operations
- `DelegationValidator` - Validates security constraints
- `DelegationStatus` - Tracks delegation lifecycle (Active, Expired, Revoked)

## Quick Start

### Basic Delegation

```php
use Patrol\Laravel\Delegation;

// Grant document editing permissions for 7 days
$delegation = Delegation::grant(
    delegator: $manager,        // User object with 'id' property
    delegate: $assistant,       // User object with 'id' property
    resources: ['document:*'],  // Resource patterns
    actions: ['read', 'edit'],  // Action patterns
    expiresAt: now()->addDays(7)
);

// Revoke delegation early
Delegation::revoke($delegation->id);

// List user's active delegations
$delegations = Delegation::active($assistant);
```

### Configuration

Enable and configure delegation in `config/patrol.php`:

```php
'delegation' => [
    // Enable delegation system-wide
    'enabled' => env('PATROL_DELEGATION_ENABLED', true),

    // Storage driver: "database" or "cached"
    'driver' => env('PATROL_DELEGATION_DRIVER', 'database'),

    // Maximum delegation duration in days (null = unlimited)
    'max_duration_days' => env('PATROL_DELEGATION_MAX_DAYS', 90),

    // Allow delegates to re-delegate permissions
    'allow_transitive' => env('PATROL_DELEGATION_TRANSITIVE', false),

    // Automatic cleanup via Laravel scheduler
    'auto_cleanup' => env('PATROL_DELEGATION_AUTO_CLEANUP', true),

    // Retention period for expired/revoked delegations
    'retention_days' => env('PATROL_DELEGATION_RETENTION', 90),

    // Cache TTL in seconds (when using "cached" driver)
    'cache_ttl' => env('PATROL_DELEGATION_CACHE_TTL', 3600),

    // Database table name
    'table' => 'patrol_delegations',
],
```

## Common Use Cases

### Vacation Coverage

Manager delegates approval rights to assistant during vacation:

```php
use Patrol\Laravel\Delegation;

$delegation = Delegation::grant(
    delegator: $manager,
    delegate: $assistant,
    resources: [
        'expense:*',
        'timeoff:*',
        'purchase:*',
    ],
    actions: ['approve', 'reject'],
    expiresAt: now()->addWeeks(2),
    metadata: [
        'reason' => 'Vacation coverage',
        'return_date' => '2025-10-18',
    ]
);

// When manager returns early, revoke immediately
Delegation::revoke($delegation->id);
```

### Project Collaboration

Grant read-only access to external collaborators:

```php
$projectId = $project->id;

foreach ($project->collaborators as $collaborator) {
    Delegation::grant(
        delegator: $projectOwner,
        delegate: $collaborator,
        resources: [
            "document:project-{$projectId}:*",
            "report:project-{$projectId}:*",
        ],
        actions: ['read'],
        expiresAt: $project->end_date,
        metadata: [
            'project_id' => $projectId,
            'role' => 'read-only-collaborator',
        ]
    );
}
```

### Emergency Access

Grant temporary admin access to on-call engineer:

```php
$delegation = Delegation::grant(
    delegator: $admin,
    delegate: $onCallEngineer,
    resources: ['system:*'],
    actions: ['*'],
    expiresAt: now()->addHours(12),
    metadata: [
        'reason' => 'Emergency access for incident #12345',
        'incident_id' => '12345',
        'ip_address' => request()->ip(),
    ]
);
```

### Task Handoff

Delegate specific permissions for code review:

```php
Delegation::grant(
    delegator: $developer,
    delegate: $reviewer,
    resources: ["pull-request:{$pr->id}"],
    actions: ['read', 'comment', 'approve'],
    expiresAt: now()->addDays(3),
    metadata: [
        'pr_number' => $pr->number,
        'repository' => $pr->repository,
    ]
);
```

## Advanced Features

### Delegation Scope Matching

`DelegationScope` supports wildcard patterns for flexible permissions:

```php
use Patrol\Core\ValueObjects\DelegationScope;

$scope = new DelegationScope(
    resources: ['document:*'],      // Matches any document
    actions: ['read', 'edit']       // Specific actions only
);

$scope->matches('document:123', 'read');    // true
$scope->matches('document:456', 'edit');    // true
$scope->matches('document:123', 'delete');  // false
$scope->matches('report:123', 'read');      // false

// Wildcard actions
$adminScope = new DelegationScope(
    resources: ['system:*'],
    actions: ['*']                  // All actions
);

$adminScope->matches('system:logs', 'read');    // true
$adminScope->matches('system:config', 'write'); // true
```

### Pre-flight Validation

Check if a user can delegate before attempting:

```php
use Patrol\Laravel\Delegation;

if (Delegation::canDelegate(
    user: $user,
    resources: ['document:*'],
    actions: ['edit']
)) {
    // Show "Delegate Permission" UI
    $delegation = Delegation::grant(...);
} else {
    // User lacks permissions to delegate
    abort(403, 'You cannot delegate permissions you do not have');
}
```

### Transitive Delegations

Allow delegates to re-delegate permissions (requires configuration):

```php
// In config/patrol.php
'delegation' => [
    'allow_transitive' => true,
],

// Grant transitive delegation
$delegation = Delegation::grant(
    delegator: $manager,
    delegate: $teamLead,
    resources: ['project:*'],
    actions: ['read', 'edit'],
    expiresAt: now()->addDays(30),
    transitive: true  // Team lead can further delegate
);

// Team lead can now delegate to team members
Delegation::grant(
    delegator: $teamLead,      // Now acting as delegator
    delegate: $teamMember,
    resources: ['project:alpha'],  // Subset of original scope
    actions: ['read'],              // Subset of original actions
    expiresAt: now()->addDays(7)
);
```

### Direct Manager Usage

For advanced scenarios, use `DelegationManager` directly:

```php
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\ValueObjects\{Subject, DelegationScope};

$manager = app(DelegationManager::class);

$delegation = $manager->delegate(
    delegator: new Subject('user:123'),
    delegate: new Subject('user:456'),
    scope: new DelegationScope(
        resources: ['document:*'],
        actions: ['read', 'edit']
    ),
    expiresAt: now()->addDays(7),
    transitive: false,
    metadata: ['context' => 'value']
);

// Find active delegations
$delegations = $manager->findActiveDelegations(
    new Subject('user:456')
);

// Check delegation capability
$canDelegate = $manager->canDelegate(
    delegator: new Subject('user:123'),
    scope: new DelegationScope(['document:*'], ['edit'])
);
```

## Artisan Commands

### List Active Delegations

```bash
# List delegations for a user
php artisan patrol:delegation:list user:123

# Output:
# Found 2 active delegation(s) for user:123:
# ┌──────────────┬───────────┬─────────────┬─────────────┬─────────────────────┐
# │ ID           │ Delegator │ Resources   │ Actions     │ Expires             │
# ├──────────────┼───────────┼─────────────┼─────────────┼─────────────────────┤
# │ abc-123-def  │ user:100  │ document:*  │ read, edit  │ 2025-10-11 14:30:00 │
# │ xyz-789-ghi  │ user:200  │ report:*    │ read        │ 2025-10-15 09:00:00 │
# └──────────────┴───────────┴─────────────┴─────────────┴─────────────────────┘
```

### Revoke Delegation

```bash
# Revoke by ID
php artisan patrol:delegation:revoke abc-123-def

# Output:
# ✔ Revoking delegation
# Delegation abc-123-def revoked successfully
```

### Cleanup Expired Delegations

```bash
# Manual cleanup
php artisan patrol:delegation:cleanup

# Output:
# ✔ Cleaning up delegations
# Removed 15 delegation(s)
```

### Scheduled Cleanup

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Run daily at 2 AM
    $schedule->command('patrol:delegation:cleanup')
        ->daily()
        ->at('02:00');
}
```

## Security & Validation

### Built-in Validation

The framework automatically validates:

1. **Permission ownership** - Delegators must have the permissions they're delegating
2. **Cycle prevention** - Detects and blocks circular delegation chains
3. **Expiration constraints** - Enforces maximum duration limits
4. **Future timestamps** - Prevents already-expired delegations

```php
// ❌ This will fail - user lacks permission
Delegation::grant(
    delegator: $regularUser,    // Only has 'read' permission
    delegate: $otherUser,
    resources: ['document:*'],
    actions: ['delete'],         // Cannot delegate 'delete'
    expiresAt: now()->addDays(7)
);
// Throws RuntimeException: Delegation validation failed

// ❌ This will fail - creates cycle
Delegation::grant(
    delegator: $userA,          // Already received delegation from $userB
    delegate: $userB,           // Now trying to delegate back
    resources: ['document:*'],
    actions: ['read'],
);
// Throws RuntimeException: Delegation validation failed

// ❌ This will fail - exceeds maximum duration
Delegation::grant(
    delegator: $user,
    delegate: $otherUser,
    resources: ['document:*'],
    actions: ['read'],
    expiresAt: now()->addDays(365)  // Exceeds 90-day limit
);
// Throws RuntimeException: Delegation validation failed
```

### Audit Metadata

Track delegation context for compliance and investigations:

```php
$delegation = Delegation::grant(
    delegator: $manager,
    delegate: $assistant,
    resources: ['expense:*'],
    actions: ['approve'],
    expiresAt: now()->addWeeks(2),
    metadata: [
        'reason' => 'Vacation coverage',
        'approved_by' => 'hr:director:789',
        'ticket_id' => 'HR-2024-1234',
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'business_justification' => 'Manager PTO from 2025-10-04 to 2025-10-18',
    ]
);

// Access metadata later
$reason = $delegation->metadata['reason'];
$ticketId = $delegation->metadata['ticket_id'];
```

### Lifecycle Tracking

Monitor delegation status throughout its lifecycle:

```php
use Patrol\Core\ValueObjects\DelegationStatus;

// Check status
$delegation->status === DelegationStatus::Active;   // Currently usable
$delegation->status === DelegationStatus::Expired;  // Past expiration
$delegation->status === DelegationStatus::Revoked;  // Manually revoked

// Check if active and not expired
$delegation->isActive();  // true/false

// Check if past expiration time
$delegation->isExpired(); // true/false

// Check transitivity
$delegation->canTransit(); // true/false
```

## Performance Optimization

### Cached Driver

For high-traffic applications, use the cached driver:

```php
// config/patrol.php
'delegation' => [
    'driver' => 'cached',
    'cache_ttl' => 3600,  // 1 hour cache
],
```

The cached driver:
- Wraps database queries with cache layer
- Automatically invalidates on create/revoke
- Reduces database load for delegation lookups
- Configurable TTL balances freshness vs. performance

### Database Indexes

The migration creates optimized indexes:

```php
// Automatically included in patrol_delegations migration
$table->index(['delegate_id', 'status']);  // Fast active delegation lookups
$table->index(['delegator_id']);           // Fast delegator queries
$table->index(['status', 'expires_at']);   // Fast cleanup queries
```

### Cleanup Strategy

Configure retention to balance audit needs with database size:

```php
'delegation' => [
    'retention_days' => 90,    // Keep expired/revoked for 90 days
    'auto_cleanup' => true,     // Automatic cleanup via scheduler
],
```

## Best Practices

### 1. Always Set Expiration Dates

```php
// ✅ Good - explicit expiration
Delegation::grant(
    delegator: $user,
    delegate: $otherUser,
    resources: ['document:*'],
    actions: ['read'],
    expiresAt: now()->addDays(7)
);

// ❌ Avoid - permanent delegation creates security risk
Delegation::grant(
    delegator: $user,
    delegate: $otherUser,
    resources: ['document:*'],
    actions: ['read'],
    expiresAt: null  // Never expires
);
```

### 2. Use Specific Scopes

```php
// ✅ Good - specific resources and actions
Delegation::grant(
    delegator: $user,
    delegate: $otherUser,
    resources: ['document:project-123:*'],
    actions: ['read', 'edit']
);

// ❌ Avoid - overly broad delegation
Delegation::grant(
    delegator: $user,
    delegate: $otherUser,
    resources: ['*'],      // All resources
    actions: ['*']         // All actions
);
```

### 3. Track Business Context

```php
// ✅ Good - comprehensive metadata
Delegation::grant(
    delegator: $manager,
    delegate: $assistant,
    resources: ['approval:*'],
    actions: ['approve'],
    expiresAt: now()->addWeeks(2),
    metadata: [
        'reason' => 'Vacation coverage',
        'manager_return_date' => '2025-10-18',
        'approved_by' => 'hr:director:789',
        'department' => 'engineering',
    ]
);
```

### 4. Validate Before Delegating

```php
// ✅ Good - check capability first
if (Delegation::canDelegate($user, ['document:*'], ['edit'])) {
    Delegation::grant(...);
} else {
    throw new UnauthorizedException('Cannot delegate permissions');
}

// ❌ Avoid - try/catch on validation failure
try {
    Delegation::grant(...);
} catch (RuntimeException $e) {
    // Expensive failure path
}
```

### 5. Revoke When No Longer Needed

```php
// ✅ Good - revoke early when context changes
if ($manager->returnsEarly()) {
    Delegation::revoke($delegation->id);
}

// ✅ Good - revoke on role change
if ($user->roleChanged()) {
    foreach (Delegation::active($user) as $delegation) {
        Delegation::revoke($delegation->id);
    }
}
```

### 6. Limit Transitive Delegations

```php
// ✅ Good - non-transitive by default
Delegation::grant(
    delegator: $manager,
    delegate: $assistant,
    resources: ['document:*'],
    actions: ['read'],
    transitive: false  // Assistant cannot re-delegate
);

// ⚠️ Use with caution - transitive delegation
Delegation::grant(
    delegator: $director,
    delegate: $manager,
    resources: ['budget:*'],
    actions: ['approve'],
    transitive: true,  // Manager can delegate to team
    metadata: ['max_chain_depth' => 2]
);
```

## Comparison with Raw Approach

### Before (Raw Policy Rules)

```php
use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Priority};

// Manual delegation via policy rule
DB::table('policies')->insert([
    'subject' => 'user:456',
    'resource' => 'document:*',
    'action' => 'edit',
    'effect' => Effect::Allow->value,
    'priority' => 100,
    'domain' => 'delegation:user:123',
    'metadata' => json_encode([
        'type' => 'delegation',
        'delegator_id' => 'user:123',
        'expires_at' => now()->addDays(7)->toIso8601String(),
    ]),
]);

// Manual validation required
// Manual cycle detection required
// Manual cleanup required
// No type safety
```

### After (Native Framework)

```php
use Patrol\Laravel\Delegation;

// Framework handles everything
$delegation = Delegation::grant(
    delegator: $delegator,
    delegate: $delegate,
    resources: ['document:*'],
    actions: ['edit'],
    expiresAt: now()->addDays(7)
);

// ✅ Automatic validation
// ✅ Automatic cycle detection
// ✅ Automatic cleanup
// ✅ Full type safety
// ✅ Audit trail
```

## Troubleshooting

### Delegation Not Taking Effect

**Symptom:** User still denied after delegation created

**Solutions:**
1. Verify delegation is enabled: `config('patrol.delegation.enabled')`
2. Check delegation status: `$delegation->isActive()`
3. Verify scope matches request: `$delegation->scope->matches($resource, $action)`
4. Clear cache if using cached driver: `php artisan cache:clear`

### Validation Failures

**Symptom:** `RuntimeException: Delegation validation failed`

**Common causes:**
1. Delegator lacks the permission being delegated
2. Cycle would be created
3. Expiration exceeds maximum duration
4. Expiration is in the past

**Debug:**
```php
// Check delegator permissions
Delegation::canDelegate($user, ['resource:*'], ['action']);

// Check for cycles manually
$manager = app(DelegationManager::class);
$validator = app(DelegationValidator::class);
$hasCycle = $validator->detectCycle($delegatorId, $delegateId);
```

### Performance Issues

**Symptom:** Slow authorization checks

**Solutions:**
1. Enable cached driver: `'driver' => 'cached'`
2. Reduce delegation scope complexity
3. Run cleanup regularly: `patrol:delegation:cleanup`
4. Optimize database indexes
5. Consider reducing `cache_ttl` if stale data is acceptable

## Migration Guide

If currently using the raw policy approach from `cookbook/delegation.md`:

### Step 1: Enable Native Delegation

```php
// config/patrol.php
'delegation' => [
    'enabled' => true,
    'driver' => 'database',
    'max_duration_days' => 90,
],
```

### Step 2: Migrate Existing Delegations

```php
use Patrol\Laravel\Delegation;
use Illuminate\Support\Facades\DB;

// Find existing delegation rules
$legacyDelegations = DB::table('policies')
    ->where('domain', 'like', 'delegation:%')
    ->get();

foreach ($legacyDelegations as $legacy) {
    $metadata = json_decode($legacy->metadata, true);

    // Create native delegation
    Delegation::grant(
        delegator: User::find($metadata['delegator_id']),
        delegate: User::where('subject_id', $legacy->subject)->first(),
        resources: [$legacy->resource],
        actions: [$legacy->action],
        expiresAt: isset($metadata['expires_at'])
            ? new DateTimeImmutable($metadata['expires_at'])
            : null,
        metadata: $metadata
    );

    // Remove legacy rule
    DB::table('policies')->where('id', $legacy->id)->delete();
}
```

### Step 3: Update Application Code

```php
// Before
$service = app(DelegationService::class);
$service->delegate($fromId, $toId, $resource, $action, $expiresAt);

// After
Delegation::grant(
    delegator: $from,
    delegate: $to,
    resources: [$resource],
    actions: [$action],
    expiresAt: $expiresAt
);
```

## Additional Resources

- [Patrol Documentation](../README.md)
- Policy Evaluation Guide (coming soon)
- [ABAC Patterns](./abac.md)
- Multi-Tenancy Guide (coming soon)
