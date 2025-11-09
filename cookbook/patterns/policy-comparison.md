# Policy Comparison & Diff - Track Policy Changes

Compare policy versions to identify changes for auditing, impact analysis, and safe deployments. Generate comprehensive diffs showing added, removed, and unchanged rules before applying policy updates.

## Problem: Blind Policy Updates

Updating policies without understanding the impact:

```php
// ❌ Deploy new policy without reviewing changes
$repository->save($newPolicy);

// What changed? Which rules were added/removed?
// Will this break existing authorizations?
// No audit trail of modifications
```

## Solution: Policy Comparator

```php
use Patrol\Core\Engine\PolicyComparator;

$comparator = new PolicyComparator();
$diff = $comparator->compare($currentPolicy, $newPolicy);

// Review changes before deployment
echo "Added: ".count($diff->addedRules)."\n";
echo "Removed: ".count($diff->removedRules)."\n";
echo "Unchanged: ".count($diff->unchangedRules)."\n";

if (!$diff->isEmpty()) {
    // Review and approve changes
    $this->reviewChanges($diff);
}

// Deploy with confidence
$repository->save($newPolicy);
```

---

## Core Concepts

### Rule Signature Matching

The comparator identifies rules by their **signature** (subject, resource, action):

| Component | Included in Signature | Reason |
|-----------|----------------------|--------|
| Subject | ✅ Yes | Defines who the rule applies to |
| Resource | ✅ Yes | Defines what the rule protects |
| Action | ✅ Yes | Defines which operation |
| Effect | ❌ No | Configuration, not identity |
| Priority | ❌ No | Configuration, not identity |
| Domain | ❌ No | Metadata, not identity |

**Example:**
```php
// These rules have the same signature (treated as "unchanged"):
$rule1 = new PolicyRule('admin', 'document:*', 'edit', Effect::Allow, new Priority(10));
$rule2 = new PolicyRule('admin', 'document:*', 'edit', Effect::Deny, new Priority(20));
// Signature: "admin:document:*:edit" (same for both)

// These rules have different signatures (treated as add + remove):
$rule3 = new PolicyRule('admin', 'document:*', 'edit', Effect::Allow);
$rule4 = new PolicyRule('admin', 'folder:*', 'edit', Effect::Allow);
// Signatures: "admin:document:*:edit" vs "admin:folder:*:edit" (different)
```

### PolicyDiff Structure

```php
class PolicyDiff
{
    public readonly Policy $oldPolicy;           // Original policy
    public readonly Policy $newPolicy;           // New policy
    public readonly array $addedRules;           // Rules in new but not old
    public readonly array $removedRules;         // Rules in old but not new
    public readonly array $unchangedRules;       // Rules in both

    public function isEmpty(): bool;              // True if no changes
    public function getChangeCount(): int;        // addedRules + removedRules
}
```

---

## Implementation Examples

### 1. Pre-Deployment Review Workflow

```php
class PolicyUpdateService
{
    public function updatePolicy(string $policyId, Policy $newPolicy): void
    {
        $currentPolicy = $this->repository->find($policyId);
        $comparator = new PolicyComparator();
        $diff = $comparator->compare($currentPolicy, $newPolicy);

        if ($diff->isEmpty()) {
            \Log::info('No policy changes detected');
            return;
        }

        // Log changes for audit
        \Log::info('Policy update', [
            'policy_id' => $policyId,
            'added' => count($diff->addedRules),
            'removed' => count($diff->removedRules),
            'unchanged' => count($diff->unchangedRules),
        ]);

        // Require approval for changes
        if (!$this->hasApproval($policyId, $diff)) {
            throw new UnauthorizedException('Policy changes require approval');
        }

        // Deploy approved changes
        $this->repository->save($newPolicy);

        // Notify stakeholders
        event(new PolicyUpdated($policyId, $diff));
    }
}
```

### 2. Change Visualization UI

```php
class PolicyComparisonController
{
    public function compare(Request $request)
    {
        $oldPolicyId = $request->input('old_policy_id');
        $newPolicyId = $request->input('new_policy_id');

        $oldPolicy = PolicyModel::findOrFail($oldPolicyId)->toValueObject();
        $newPolicy = PolicyModel::findOrFail($newPolicyId)->toValueObject();

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($oldPolicy, $newPolicy);

        return view('policies.comparison', [
            'diff' => $diff,
            'added' => $diff->addedRules,
            'removed' => $diff->removedRules,
            'unchanged' => $diff->unchangedRules,
        ]);
    }
}
```

Blade template:

```blade
<div class="policy-diff">
    <h2>Policy Comparison</h2>

    @if($diff->isEmpty())
        <p>No changes detected between policy versions.</p>
    @else
        <div class="summary">
            <span class="badge badge-success">+{{ count($added) }} Added</span>
            <span class="badge badge-danger">-{{ count($removed) }} Removed</span>
            <span class="badge badge-secondary">{{ count($unchanged) }} Unchanged</span>
        </div>

        @if(count($added) > 0)
            <h3>Added Rules</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Resource</th>
                        <th>Action</th>
                        <th>Effect</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($added as $rule)
                        <tr class="added">
                            <td>{{ $rule->subject }}</td>
                            <td>{{ $rule->resource ?? '*' }}</td>
                            <td>{{ $rule->action }}</td>
                            <td>{{ $rule->effect->value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(count($removed) > 0)
            <h3>Removed Rules</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Resource</th>
                        <th>Action</th>
                        <th>Effect</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($removed as $rule)
                        <tr class="removed">
                            <td>{{ $rule->subject }}</td>
                            <td>{{ $rule->resource ?? '*' }}</td>
                            <td>{{ $rule->action }}</td>
                            <td>{{ $rule->effect->value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</div>
```

### 3. Automated Change Detection in CI/CD

```php
// tests/PolicyChangeDetectionTest.php
test('policy migration maintains backward compatibility', function () {
    $currentPolicy = loadPolicyFromProduction();
    $migratedPolicy = runMigration();

    $comparator = new PolicyComparator();
    $diff = $comparator->compare($currentPolicy, $migratedPolicy);

    // Assert no permissions are removed (only additions allowed)
    expect($diff->removedRules)->toBeEmpty();

    // Log added permissions for review
    if (count($diff->addedRules) > 0) {
        \Log::info('New rules added by migration', [
            'count' => count($diff->addedRules),
            'rules' => array_map(fn($r) => [
                'subject' => $r->subject,
                'resource' => $r->resource,
                'action' => $r->action,
            ], $diff->addedRules),
        ]);
    }
});
```

### 4. Rollback Decision Support

```php
class PolicyRollbackService
{
    public function analyzeRollbackImpact(string $policyId): array
    {
        $currentPolicy = $this->repository->find($policyId);
        $previousVersion = $this->getHistoricalVersion($policyId, -1);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($currentPolicy, $previousVersion);

        return [
            'will_lose_rules' => count($diff->removedRules),
            'will_restore_rules' => count($diff->addedRules),
            'impact_summary' => $this->summarizeImpact($diff),
            'affected_subjects' => $this->getAffectedSubjects($diff),
        ];
    }

    private function summarizeImpact(PolicyDiff $diff): string
    {
        $added = count($diff->addedRules);
        $removed = count($diff->removedRules);

        if ($removed > $added) {
            return "Rollback will remove more permissions than it restores. Review carefully.";
        }

        return "Rollback safe: restores {$added} rules, removes {$removed} rules.";
    }
}
```

---

## Testing

```php
use Patrol\Core\Engine\PolicyComparator;

test('comparator detects added rules', function () {
    $oldPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Allow),
    ]);

    $newPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Allow),
        new PolicyRule('admin', 'document:*', 'write', Effect::Allow), // Added
    ]);

    $comparator = new PolicyComparator();
    $diff = $comparator->compare($oldPolicy, $newPolicy);

    expect($diff->addedRules)->toHaveCount(1);
    expect($diff->addedRules[0]->action)->toBe('write');
});

test('comparator detects removed rules', function () {
    $oldPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Allow),
        new PolicyRule('admin', 'document:*', 'write', Effect::Allow),
    ]);

    $newPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Allow),
    ]);

    $comparator = new PolicyComparator();
    $diff = $comparator->compare($oldPolicy, $newPolicy);

    expect($diff->removedRules)->toHaveCount(1);
    expect($diff->removedRules[0]->action)->toBe('write');
});

test('comparator treats effect changes as unchanged', function () {
    $oldPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Allow),
    ]);

    $newPolicy = new Policy([
        new PolicyRule('admin', 'document:*', 'read', Effect::Deny), // Effect changed
    ]);

    $comparator = new PolicyComparator();
    $diff = $comparator->compare($oldPolicy, $newPolicy);

    // Same signature, so treated as unchanged
    expect($diff->addedRules)->toBeEmpty();
    expect($diff->removedRules)->toBeEmpty();
    expect($diff->unchangedRules)->toHaveCount(1);
});
```

---

## Best Practices

### 1. Version Control Integration

Track policy changes in version control:

```php
// Store diff in version control metadata
$diff = $comparator->compare($oldPolicy, $newPolicy);

DB::table('policy_versions')->insert([
    'policy_id' => $policyId,
    'version' => $newVersion,
    'added_rules' => json_encode($diff->addedRules),
    'removed_rules' => json_encode($diff->removedRules),
    'change_count' => $diff->getChangeCount(),
    'created_at' => now(),
]);
```

### 2. Approval Workflows

Require human approval for significant changes:

```php
if ($diff->getChangeCount() > 10) {
    // Large change requires manager approval
    $this->requestApproval($policyId, $diff, 'manager');
} elseif (!$diff->isEmpty()) {
    // Small change requires peer review
    $this->requestApproval($policyId, $diff, 'peer');
}
```

### 3. Automated Alerts

Notify stakeholders of policy changes:

```php
if (count($diff->removedRules) > 0) {
    // Alert when permissions are removed
    \Mail::to($securityTeam)->send(
        new PolicyPermissionsRemovedNotification($policyId, $diff)
    );
}
```

---

## Related Documentation

- **[Policy Simulation](./policy-simulation.md)** - Test policies before deployment
- **Policy Versioning** (coming soon) - Track policy history
- **Audit Logging (coming soon)** - Record authorization decisions
- **[Testing](./testing.md)** - Test authorization logic

---

**Governance tip:** Combine policy comparison with approval workflows and audit logging for complete change management. Every policy update should be traceable and reviewable.
