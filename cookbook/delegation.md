# Delegation (Current Implementation)

Implement permission delegation using existing Patrol primitives without specialized delegation features.

## Overview

Delegation allows one user to temporarily grant their permissions to another user. While Patrol doesn't yet have dedicated delegation features (see [delegation.md](../delegation.md) for future plans), you can implement delegation patterns today using standard policy rules with metadata tracking.

## Basic Concept

```
Delegator → Creates PolicyRule → Delegate receives permission
Alice grants 'document:edit' to Bob → Bob can edit Alice's documents
```

## Use Cases

- Vacation coverage (manager delegates approval rights to backup)
- Task handoff (team member delegates project permissions to collaborator)
- Temporary assistance (user delegates support access to admin)
- Cross-functional collaboration (department head delegates review permissions)
- Emergency access (delegate critical permissions to on-call staff)
- Training scenarios (senior staff delegates read-only access to trainees)

## Core Implementation Pattern

### Simple Delegation

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Priority};
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

class DelegationService
{
    public function __construct(
        private DatabasePolicyRepository $repository
    ) {}

    public function delegate(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        $rule = new PolicyRule(
            subject: $delegateId,
            resource: $resource,
            action: $action,
            effect: Effect::Allow,
            priority: Priority::from(100),
            domain: "delegation:{$delegatorId}"
        );

        // Store in database with metadata
        DB::table('policies')->insert([
            'subject' => $rule->subject,
            'resource' => $rule->resource,
            'action' => $rule->action,
            'effect' => $rule->effect->value,
            'priority' => $rule->priority->value,
            'domain' => $rule->domain,
            'metadata' => json_encode([
                'type' => 'delegation',
                'delegator_id' => $delegatorId,
                'delegate_id' => $delegateId,
                'created_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }

    public function revoke(string $delegatorId, string $delegateId): void
    {
        DB::table('policies')
            ->where('domain', "delegation:{$delegatorId}")
            ->where('subject', $delegateId)
            ->delete();
    }
}

// Usage
$service = new DelegationService(app(DatabasePolicyRepository::class));

$service->delegate(
    delegatorId: 'alice:123',
    delegateId: 'bob:456',
    resource: 'document:*',
    action: 'edit',
    expiresAt: now()->addDays(7)
);
```

### Time-Limited Delegation

```php
class TimeLimitedDelegationService extends DelegationService
{
    public function delegate(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        \DateTimeImmutable $expiresAt
    ): void {
        parent::delegate($delegatorId, $delegateId, $resource, $action, $expiresAt);
    }

    public function cleanupExpired(): int
    {
        return DB::table('policies')
            ->where('metadata->type', 'delegation')
            ->where('metadata->expires_at', '<', now()->toIso8601String())
            ->delete();
    }

    public function isActive(string $delegatorId, string $delegateId): bool
    {
        $policy = DB::table('policies')
            ->where('domain', "delegation:{$delegatorId}")
            ->where('subject', $delegateId)
            ->first();

        if (!$policy) {
            return false;
        }

        $metadata = json_decode($policy->metadata, true);
        $expiresAt = $metadata['expires_at'] ?? null;

        if (!$expiresAt) {
            return true; // No expiration
        }

        return now()->lessThan(new \DateTimeImmutable($expiresAt));
    }
}

// Usage
$service = new TimeLimitedDelegationService(app(DatabasePolicyRepository::class));

// Delegate for 7 days
$service->delegate(
    delegatorId: 'manager:100',
    delegateId: 'assistant:200',
    resource: 'approval:*',
    action: 'approve',
    expiresAt: now()->addDays(7)
);

// Cleanup expired delegations (run via scheduled job)
$service->cleanupExpired();
```

### Scope-Limited Delegation

```php
class ScopedDelegationService
{
    public function delegateMultiple(
        string $delegatorId,
        string $delegateId,
        array $scopes, // [['resource' => 'doc:*', 'actions' => ['read', 'edit']]]
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        foreach ($scopes as $scope) {
            foreach ($scope['actions'] as $action) {
                $this->delegateSingle(
                    $delegatorId,
                    $delegateId,
                    $scope['resource'],
                    $action,
                    $expiresAt
                );
            }
        }
    }

    private function delegateSingle(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        ?\DateTimeImmutable $expiresAt
    ): void {
        DB::table('policies')->insert([
            'subject' => $delegateId,
            'resource' => $resource,
            'action' => $action,
            'effect' => Effect::Allow->value,
            'priority' => 100,
            'domain' => "delegation:{$delegatorId}",
            'metadata' => json_encode([
                'type' => 'delegation',
                'delegator_id' => $delegatorId,
                'delegate_id' => $delegateId,
                'scope' => compact('resource', 'action'),
                'created_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }
}

// Usage: Delegate read-only access
$service = new ScopedDelegationService();

$service->delegateMultiple(
    delegatorId: 'owner:50',
    delegateId: 'collaborator:60',
    scopes: [
        ['resource' => 'document:project-x:*', 'actions' => ['read']],
        ['resource' => 'report:project-x:*', 'actions' => ['read']],
    ],
    expiresAt: now()->addDays(30)
);
```

## Laravel Integration

### Eloquent Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    protected $table = 'policies';

    protected $fillable = [
        'subject',
        'resource',
        'action',
        'effect',
        'priority',
        'domain',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'priority' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('metadata->expires_at')
              ->orWhere('metadata->expires_at', '>', now()->toIso8601String());
        });
    }

    public function scopeDelegationType($query)
    {
        return $query->where('metadata->type', 'delegation');
    }

    public function scopeForDelegator($query, string $delegatorId)
    {
        return $query->where('domain', "delegation:{$delegatorId}");
    }

    public function scopeForDelegate($query, string $delegateId)
    {
        return $query->where('subject', $delegateId);
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->metadata['expires_at'] ?? null;

        if (!$expiresAt) {
            return false;
        }

        return now()->greaterThan(new \DateTimeImmutable($expiresAt));
    }
}

// Usage
$activeDelegations = Delegation::delegationType()
    ->forDelegate('bob:456')
    ->active()
    ->get();

$myDelegations = Delegation::delegationType()
    ->forDelegator('alice:123')
    ->get();
```

### Facade

```php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Delegation extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'delegation.service';
    }
}

// Register in service provider
$this->app->singleton('delegation.service', DelegationService::class);

// Usage
use App\Facades\Delegation;

Delegation::delegate(
    delegatorId: auth()->id(),
    delegateId: $user->id,
    resource: 'document:*',
    action: 'edit',
    expiresAt: now()->addDays(7)
);
```

### Artisan Command

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupExpiredDelegations extends Command
{
    protected $signature = 'delegation:cleanup';
    protected $description = 'Remove expired delegation rules';

    public function handle(TimeLimitedDelegationService $service)
    {
        $count = $service->cleanupExpired();

        $this->info("Removed {$count} expired delegation(s)");

        return 0;
    }
}

// Schedule in Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('delegation:cleanup')->daily();
}
```

## Advanced Patterns

### Validation Before Delegation

```php
class ValidatedDelegationService extends DelegationService
{
    public function __construct(
        private DatabasePolicyRepository $repository,
        private PolicyEvaluator $evaluator
    ) {
        parent::__construct($repository);
    }

    public function delegate(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        // Ensure delegator has the permission they're trying to delegate
        $policy = $this->repository->findPolicy($delegatorId, $resource);

        $subject = new Subject($delegatorId);
        $resourceObj = new Resource($resource);
        $actionObj = new Action($action);

        $result = $this->evaluator->evaluate($policy, $subject, $resourceObj, $actionObj);

        if ($result !== Effect::Allow) {
            throw new \DomainException(
                "Delegator {$delegatorId} does not have permission to {$action} on {$resource}"
            );
        }

        parent::delegate($delegatorId, $delegateId, $resource, $action, $expiresAt);
    }
}
```

### Delegation Chains (Transitive)

```php
class TransitiveDelegationService extends DelegationService
{
    public function delegateTransitive(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        ?\DateTimeImmutable $expiresAt = null
    ): void {
        // Check for delegation cycles
        if ($this->createsCycle($delegatorId, $delegateId)) {
            throw new \DomainException('Delegation would create a cycle');
        }

        $metadata = [
            'type' => 'delegation',
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'transitive' => true,
            'created_at' => now()->toIso8601String(),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'chain' => $this->buildChain($delegatorId),
        ];

        DB::table('policies')->insert([
            'subject' => $delegateId,
            'resource' => $resource,
            'action' => $action,
            'effect' => Effect::Allow->value,
            'priority' => 100,
            'domain' => "delegation:{$delegatorId}",
            'metadata' => json_encode($metadata),
        ]);
    }

    private function createsCycle(string $from, string $to): bool
    {
        // Check if 'to' has delegated to 'from' (direct or indirect)
        $chain = $this->buildChain($to);

        return in_array($from, $chain, true);
    }

    private function buildChain(string $userId): array
    {
        $chain = [$userId];
        $current = $userId;

        while ($delegator = $this->findDelegator($current)) {
            if (in_array($delegator, $chain, true)) {
                break; // Cycle detected
            }

            $chain[] = $delegator;
            $current = $delegator;
        }

        return $chain;
    }

    private function findDelegator(string $delegateId): ?string
    {
        $result = DB::table('policies')
            ->where('subject', $delegateId)
            ->where('metadata->type', 'delegation')
            ->where('metadata->transitive', true)
            ->first();

        return $result ? json_decode($result->metadata, true)['delegator_id'] : null;
    }
}
```

### Audit Trail

```php
class AuditedDelegationService extends DelegationService
{
    public function delegate(
        string $delegatorId,
        string $delegateId,
        string $resource,
        string $action,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $reason = null
    ): void {
        parent::delegate($delegatorId, $delegateId, $resource, $action, $expiresAt);

        // Log delegation event
        DB::table('delegation_audit_log')->insert([
            'event_type' => 'delegation_created',
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'resource' => $resource,
            'action' => $action,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'reason' => $reason,
            'created_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function revoke(string $delegatorId, string $delegateId, ?string $reason = null): void
    {
        parent::revoke($delegatorId, $delegateId);

        DB::table('delegation_audit_log')->insert([
            'event_type' => 'delegation_revoked',
            'delegator_id' => $delegatorId,
            'delegate_id' => $delegateId,
            'reason' => $reason,
            'created_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}

// Migration for audit log
Schema::create('delegation_audit_log', function (Blueprint $table) {
    $table->id();
    $table->string('event_type'); // created, revoked, expired
    $table->string('delegator_id')->index();
    $table->string('delegate_id')->index();
    $table->string('resource')->nullable();
    $table->string('action')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->text('reason')->nullable();
    $table->timestamp('created_at');
    $table->ipAddress('ip_address')->nullable();
    $table->text('user_agent')->nullable();
});
```

## Real-World Examples

### Example 1: Manager Vacation Coverage

```php
// Manager going on vacation, delegates approval rights to assistant
$delegationService = app(TimeLimitedDelegationService::class);

$delegationService->delegateMultiple(
    delegatorId: "manager:{$manager->id}",
    delegateId: "assistant:{$assistant->id}",
    scopes: [
        ['resource' => 'expense:*', 'actions' => ['approve', 'reject']],
        ['resource' => 'timeoff:*', 'actions' => ['approve', 'reject']],
        ['resource' => 'purchase:*', 'actions' => ['approve']],
    ],
    expiresAt: now()->addDays(14) // Two week vacation
);

// When manager returns, delegations automatically expire
// Or manually revoke early:
$delegationService->revoke(
    delegatorId: "manager:{$manager->id}",
    delegateId: "assistant:{$assistant->id}"
);
```

### Example 2: Project Collaboration

```php
// Project owner delegates read access to collaborators
$service = app(ScopedDelegationService::class);

foreach ($project->collaborators as $collaborator) {
    $service->delegateMultiple(
        delegatorId: "owner:{$project->owner_id}",
        delegateId: "user:{$collaborator->id}",
        scopes: [
            ['resource' => "document:project-{$project->id}:*", 'actions' => ['read']],
            ['resource' => "report:project-{$project->id}:*", 'actions' => ['read']],
        ],
        expiresAt: $project->end_date
    );
}
```

### Example 3: Emergency Access

```php
// On-call engineer gets temporary admin access
$service = app(AuditedDelegationService::class);

$service->delegate(
    delegatorId: 'admin:1',
    delegateId: "engineer:{$onCallEngineer->id}",
    resource: 'system:*',
    action: '*',
    expiresAt: now()->addHours(12),
    reason: 'On-call emergency access for incident #12345'
);

// All actions are audited
```

## Migration to Native Delegation (Future)

When Patrol adds native delegation support (see [delegation.md](../delegation.md)), migration will be straightforward:

```php
// Current approach
$service->delegate($from, $to, $resource, $action, $expiresAt);

// Future native approach
Patrol::delegation()->grant(
    from: $fromSubject,
    to: $toSubject,
    scope: new DelegationScope([$resource], [$action]),
    expiresAt: $expiresAt
);
```

The underlying concepts remain the same, but with better type safety, validation, and lifecycle management.

## Best Practices

1. **Always set expiration dates** to prevent indefinite delegations
2. **Validate delegator permissions** before creating delegation rules
3. **Use descriptive domain names** like `delegation:{delegator_id}` for tracking
4. **Store metadata** (reason, created_at, expires_at) for audit trails
5. **Implement cleanup jobs** to remove expired delegations
6. **Check for cycles** before allowing transitive delegations
7. **Log all delegation events** for security audits
8. **Use unique priorities** to avoid conflicts with regular rules
9. **Test edge cases** like concurrent delegations, expiration timing
10. **Document delegation policies** in your application's security guidelines

## Security Considerations

- Validate delegator has permissions before delegating
- Prevent delegation cycles (A→B→C→A)
- Enforce maximum delegation duration
- Require reason/justification for sensitive delegations
- Log all delegation create/revoke events
- Implement rate limiting to prevent delegation spam
- Review delegations regularly for suspicious patterns
- Auto-revoke on account changes (password reset, role change)

## Performance Tips

- Index `domain` column for fast delegation queries
- Cache active delegations per user (invalidate on create/revoke)
- Run cleanup jobs during off-peak hours
- Use database transactions for multi-rule delegations
- Consider separate table for delegation metadata if high volume

## Limitations of Current Approach

Without native delegation support, you'll need to manually handle:
- Validation that delegator has the permissions being delegated
- Cycle detection for transitive delegations
- Cleanup of expired delegations
- Audit logging of delegation events
- UI/API for delegation management

See [delegation.md](../delegation.md) for the roadmap to native support with these features built-in.
