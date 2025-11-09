# Repository Operations - Bulk & Soft Deletes

Advanced repository operations for managing policies at scale, including bulk operations and soft delete support for audit trails.

## Bulk Operations

### `saveMany()` - Atomic Multi-Policy Save

Save multiple policies in a single transaction:

```php
use Patrol\Laravel\Testing\Factories\PolicyFactory;

$policies = [
    PolicyFactory::allow('admin', '*', '*'),
    PolicyFactory::allow('editor', 'document:*', 'edit'),
    PolicyFactory::allow('viewer', 'document:*', 'read'),
];

// Atomic save (all-or-nothing)
$repository->saveMany($policies);
```

**Benefits:**
- ✅ Single database transaction
- ✅ Better performance than individual saves
- ✅ Atomicity: all policies saved or none

### `getPoliciesForBatch()` - Batch Policy Loading

Load policies for multiple resources in one query:

```php
$resources = [
    new Resource('document:1', 'Document'),
    new Resource('document:2', 'Document'),
    new Resource('document:3', 'Document'),
];

// Single query loads all policies
$policiesByResource = $repository->getPoliciesForBatch($subject, $resources);

// Returns: ['document:1' => Policy, 'document:2' => Policy, ...]
```

**Use for:**
- Batch authorization (avoid N+1)
- List filtering
- Bulk permission checks

**See:** [Batch Authorization Guide](../patterns/batch-authorization.md)

### `deleteMany()` - Bulk Rule Deletion

Delete multiple policy rules by ID:

```php
$ruleIds = ['rule-1', 'rule-2', 'rule-3'];

// Single operation deletes all
$repository->deleteMany($ruleIds);
```

---

## Soft Delete Support

### Overview

Soft deletes mark policies as deleted without permanently removing them, enabling:
- **Audit trails** - Track what permissions existed
- **Data recovery** - Restore accidentally deleted policies  
- **Compliance** - Retain historical access control records

### Basic Operations

#### `softDelete()` - Mark as Deleted

```php
// Soft delete a policy rule
$repository->softDelete('rule-id-123');

// Policy still in database but marked deleted_at
// Normal queries exclude it automatically
```

#### `restore()` - Undelete

```php
// Restore a soft-deleted policy
$repository->restore('rule-id-123');

// Policy active again, deleted_at cleared
```

#### `forceDelete()` - Permanent Deletion

```php
// Permanently remove from database
$repository->forceDelete('rule-id-123');

// Cannot be restored
```

### Querying Soft Deleted Policies

#### `getTrashed()` - Only Soft Deleted

```php
// Get all soft-deleted policies
$trashedPolicy = $repository->getTrashed();

foreach ($trashedPolicy->rules as $rule) {
    echo "Deleted: {$rule->subject} → {$rule->action}\n";
}
```

#### `getWithTrashed()` - Include Soft Deleted

```php
// Get policies including soft-deleted ones
$allPolicies = $repository->getWithTrashed($subject, $resource);

// Useful for admin interfaces showing full history
```

---

## Implementation Examples

### Bulk Policy Import

```php
class PolicyImporter
{
    public function import(array $policyData): void
    {
        $policies = array_map(
            fn($data) => $this->hydratePolicyFromArray($data),
            $policyData
        );

        // Atomic import - all succeed or all fail
        $this->repository->saveMany($policies);

        \Log::info("Imported {count($policies)} policies");
    }
}
```

### Soft Delete with Approval Workflow

```php
class PolicyDeletionService
{
    public function requestDeletion(string $ruleId, User $requestedBy): void
    {
        // Soft delete immediately
        $this->repository->softDelete($ruleId);

        // Create approval request
        PolicyDeletionRequest::create([
            'rule_id' => $ruleId,
            'requested_by' => $requestedBy->id,
            'status' => 'pending',
        ]);

        event(new PolicyDeletionRequested($ruleId));
    }

    public function approveDeletion(string $ruleId): void
    {
        // Permanently delete after approval
        $this->repository->forceDelete($ruleId);

        PolicyDeletionRequest::where('rule_id', $ruleId)
            ->update(['status' => 'approved']);
    }

    public function rejectDeletion(string $ruleId): void
    {
        // Restore policy
        $this->repository->restore($ruleId);

        PolicyDeletionRequest::where('rule_id', $ruleId)
            ->update(['status' => 'rejected']);
    }
}
```

### Audit Trail Interface

```php
class PolicyAuditController
{
    public function history()
    {
        // Show all policies including deleted
        $currentPolicies = $repository->getPoliciesFor($subject, $resource);
        $deletedPolicies = $repository->getTrashed();

        return view('admin.policy-audit', [
            'current' => $currentPolicies->rules,
            'deleted' => $deletedPolicies->rules,
        ]);
    }
}
```

---

## Database Schema (Laravel)

### Migration for Soft Deletes

```php
Schema::create('patrol_policies', function (Blueprint $table) {
    $table->id();
    $table->string('subject');
    $table->string('resource')->nullable();
    $table->string('action');
    $table->enum('effect', ['ALLOW', 'DENY']);
    $table->integer('priority')->default(1);
    $table->string('domain')->nullable();
    $table->text('condition')->nullable();
    $table->timestamps();
    $table->softDeletes(); // Adds deleted_at column

    $table->index(['subject', 'resource', 'action']);
    $table->index('deleted_at'); // For soft delete queries
});
```

### Implementation in Repository

```php
class DatabasePolicyRepository implements PolicyRepositoryInterface
{
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $rules = DB::table('patrol_policies')
            ->whereNull('deleted_at') // Exclude soft-deleted
            ->where(/* ... matching logic ... */)
            ->get();

        return $this->hydratePolicy($rules);
    }

    public function softDelete(string $ruleId): void
    {
        DB::table('patrol_policies')
            ->where('id', $ruleId)
            ->update(['deleted_at' => now()]);
    }

    public function restore(string $ruleId): void
    {
        DB::table('patrol_policies')
            ->where('id', $ruleId)
            ->update(['deleted_at' => null]);
    }

    public function forceDelete(string $ruleId): void
    {
        DB::table('patrol_policies')
            ->where('id', $ruleId)
            ->delete(); // Permanent deletion
    }

    public function getTrashed(): Policy
    {
        $rules = DB::table('patrol_policies')
            ->whereNotNull('deleted_at')
            ->get();

        return $this->hydratePolicy($rules);
    }

    public function getWithTrashed(Subject $subject, Resource $resource): Policy
    {
        $rules = DB::table('patrol_policies')
            // No deleted_at filter - include all
            ->where(/* ... matching logic ... */)
            ->get();

        return $this->hydratePolicy($rules);
    }
}
```

---

## Testing

```php
test('saveMany atomically saves multiple policies', function () {
    $policies = [
        PolicyFactory::allow('admin', 'document:*', 'read'),
        PolicyFactory::allow('editor', 'document:*', 'edit'),
    ];

    $repository->saveMany($policies);

    expect(DB::table('patrol_policies')->count())->toBe(2);
});

test('soft delete marks policy as deleted', function () {
    $policy = PolicyFactory::allow('admin', 'document:*', 'read');
    $repository->save($policy);

    $ruleId = DB::table('patrol_policies')->first()->id;
    $repository->softDelete($ruleId);

    // Policy still exists
    expect(DB::table('patrol_policies')->count())->toBe(1);

    // But marked as deleted
    expect(DB::table('patrol_policies')->whereNotNull('deleted_at')->count())->toBe(1);

    // Not returned in normal queries
    $loaded = $repository->getPoliciesFor($subject, $resource);
    expect($loaded->rules)->toBeEmpty();
});

test('restore recovers soft-deleted policy', function () {
    $policy = PolicyFactory::allow('admin', 'document:*', 'read');
    $repository->save($policy);

    $ruleId = DB::table('patrol_policies')->first()->id;
    $repository->softDelete($ruleId);
    $repository->restore($ruleId);

    // Policy active again
    $loaded = $repository->getPoliciesFor($subject, $resource);
    expect($loaded->rules)->toHaveCount(1);
});
```

---

## Best Practices

### 1. Soft Delete by Default

```php
// ✅ GOOD: Soft delete for audit trail
$repository->softDelete($ruleId);

// ❌ AVOID: Force delete loses history
$repository->forceDelete($ruleId);
```

### 2. Periodic Cleanup

Schedule permanent deletion of old soft-deleted policies:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        // Permanently delete policies soft-deleted >90 days ago
        DB::table('patrol_policies')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', now()->subDays(90))
            ->delete();
    })->daily();
}
```

### 3. Batch Operations in Transactions

```php
DB::transaction(function () use ($policies) {
    $this->repository->saveMany($policies);
});
```

---

## Related Documentation

- **[Batch Authorization](../patterns/batch-authorization.md)** - Optimize bulk checks
- **[Persisting Policies](./persisting-policies.md)** - Storage strategies
- **Audit Logging** (coming soon) - Track authorization decisions

---

**Compliance tip:** Soft deletes are essential for audit trails and compliance (SOC 2, GDPR, HIPAA). Retain deleted policies for at least 90 days before permanent removal.
