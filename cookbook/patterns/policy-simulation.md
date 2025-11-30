# Policy Simulation - Test Policies Before Deployment

Validate authorization policies in a safe sandbox environment before deploying them to production. Simulate "what-if" scenarios, debug authorization decisions, and optimize policy performance without affecting real authorization.

## Problem: Deploying Untested Policies

Deploying policies without testing can cause production issues:

```php
// ❌ Deploy policy directly to production
$policy = new Policy([
    new PolicyRule('role:editor', 'document:*', 'delete', Effect::Allow),
]);

$repository->save($policy);

// Oops! Too permissive - editors can now delete ANY document
// No way to test before deployment
// No visibility into evaluation performance
```

**Risks:**
- **Security holes**: Overly permissive policies grant unintended access
- **Access denials**: Overly restrictive policies block legitimate users
- **Performance issues**: Complex rules slow down authorization
- **No validation**: Can't verify behavior before deployment

---

## Solution: Policy Simulator

Test policies in an isolated sandbox with detailed execution insights:

```php
use Patrol\Core\Engine\PolicySimulator;
use Patrol\Core\ValueObjects\Effect;

$simulator = new PolicySimulator($policyEvaluator);

// Create test policy (not saved to database)
$testPolicy = new Policy([
    new PolicyRule('role:editor', 'document:*', 'delete', Effect::Allow, new Priority(10)),
]);

// Simulate authorization
$result = $simulator->simulate(
    $testPolicy,
    new Subject('user:123', ['roles' => ['role:editor']]),
    new Resource('document:456', 'Document'),
    new Action('delete')
);

// Inspect results
echo "Effect: {$result->effect->value}\n";          // Allow or Deny
echo "Execution Time: {$result->executionTime}ms\n"; // Performance metric
echo "Matched Rules: ".count($result->matchedRules)."\n";

// Only deploy if simulation passes
if ($result->effect === Effect::Allow) {
    $repository->save($testPolicy); // Safe to deploy
}
```

**Benefits:**
- ✅ Zero risk testing (no database writes, no audit logs)
- ✅ Performance profiling before deployment
- ✅ Immediate feedback for policy authors
- ✅ Debug authorization decisions in development
- ✅ Validate migration scenarios

---

## Core Concepts

### Simulation Guarantees

The simulator provides these guarantees:

| Aspect | Guarantee |
|--------|-----------|
| **No side effects** | Never modifies policies, database, or audit logs |
| **Production accuracy** | Uses same evaluation logic as real authorization |
| **Performance metrics** | Millisecond-precision execution timing |
| **Thread-safe** | Safe for concurrent simulation requests |
| **Immutable** | All inputs and outputs are immutable |

### Simulation Result Structure

```php
class SimulationResult
{
    public readonly Effect $effect;           // Allow or Deny
    public readonly Policy $policy;           // Simulated policy
    public readonly Subject $subject;         // Test subject
    public readonly Resource $resource;       // Test resource
    public readonly Action $action;           // Test action
    public readonly float $executionTime;     // Milliseconds (microsecond precision)
    public readonly array $matchedRules;      // Rules that matched (future enhancement)
}
```

---

## Implementation Examples

### 1. Pre-Deployment Policy Validation

Validate policies in development before deploying:

```php
use Patrol\Core\Engine\PolicySimulator;
use Patrol\Core\ValueObjects\Effect;

class PolicyDeploymentService
{
    public function __construct(
        private PolicySimulator $simulator,
        private PolicyRepositoryInterface $repository,
    ) {}

    public function deployPolicy(Policy $policy, array $testScenarios): void
    {
        // Run simulation test suite
        foreach ($testScenarios as $scenario) {
            $result = $this->simulator->simulate(
                $policy,
                $scenario['subject'],
                $scenario['resource'],
                $scenario['action']
            );

            // Verify expected outcome
            if ($result->effect !== $scenario['expectedEffect']) {
                throw new PolicyValidationException(
                    "Policy failed test: expected {$scenario['expectedEffect']->value}, got {$result->effect->value}"
                );
            }

            // Verify performance requirements
            if ($result->executionTime > $scenario['maxExecutionTime']) {
                throw new PolicyValidationException(
                    "Policy too slow: {$result->executionTime}ms (max: {$scenario['maxExecutionTime']}ms)"
                );
            }
        }

        // All tests passed - safe to deploy
        $this->repository->save($policy);
        \Log::info('Policy deployed after validation', ['rules' => count($policy->rules)]);
    }
}

// Usage
$testScenarios = [
    [
        'subject' => new Subject('user:123', ['roles' => ['role:editor']]),
        'resource' => new Resource('document:456', 'Document'),
        'action' => new Action('edit'),
        'expectedEffect' => Effect::Allow,
        'maxExecutionTime' => 5.0, // Max 5ms
    ],
    [
        'subject' => new Subject('user:789', ['roles' => ['role:viewer']]),
        'resource' => new Resource('document:456', 'Document'),
        'action' => new Action('delete'),
        'expectedEffect' => Effect::Deny,
        'maxExecutionTime' => 5.0,
    ],
];

$deploymentService->deployPolicy($newPolicy, $testScenarios);
```

### 2. Interactive Policy Builder

Build policies with live feedback:

```php
class PolicyBuilderController
{
    public function simulateRule(Request $request)
    {
        $validator = validator($request->all(), [
            'subject' => 'required|string',
            'resource' => 'required|string',
            'action' => 'required|string',
            'effect' => 'required|in:ALLOW,DENY',
            'test_subject' => 'required|string',
            'test_resource' => 'required|string',
            'test_action' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Build test policy from request
        $testPolicy = new Policy([
            new PolicyRule(
                subject: $request->input('subject'),
                resource: $request->input('resource'),
                action: $request->input('action'),
                effect: Effect::from($request->input('effect')),
                priority: new Priority(10),
            ),
        ]);

        // Simulate with test inputs
        $simulator = app(PolicySimulator::class);
        $result = $simulator->simulate(
            $testPolicy,
            new Subject($request->input('test_subject')),
            new Resource($request->input('test_resource'), 'Resource'),
            new Action($request->input('test_action'))
        );

        return response()->json([
            'effect' => $result->effect->value,
            'execution_time' => $result->executionTime,
            'policy' => [
                'subject' => $request->input('subject'),
                'resource' => $request->input('resource'),
                'action' => $request->input('action'),
                'effect' => $request->input('effect'),
            ],
        ]);
    }
}
```

Frontend integration:

```vue
<template>
  <div class="policy-builder">
    <h2>Policy Rule Builder</h2>

    <!-- Rule definition -->
    <input v-model="rule.subject" placeholder="Subject (e.g., role:editor)" />
    <input v-model="rule.resource" placeholder="Resource (e.g., document:*)" />
    <input v-model="rule.action" placeholder="Action (e.g., edit)" />
    <select v-model="rule.effect">
      <option value="ALLOW">Allow</option>
      <option value="DENY">Deny</option>
    </select>

    <!-- Test scenario -->
    <h3>Test Scenario</h3>
    <input v-model="test.subject" placeholder="Test subject (e.g., user:123)" />
    <input v-model="test.resource" placeholder="Test resource (e.g., document:456)" />
    <input v-model="test.action" placeholder="Test action (e.g., edit)" />

    <button @click="simulate">Simulate</button>

    <!-- Results -->
    <div v-if="result" class="results">
      <p>Effect: <strong>{{ result.effect }}</strong></p>
      <p>Execution Time: {{ result.execution_time.toFixed(3) }}ms</p>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      rule: { subject: '', resource: '', action: '', effect: 'ALLOW' },
      test: { subject: '', resource: '', action: '' },
      result: null,
    };
  },
  methods: {
    async simulate() {
      const response = await fetch('/api/policies/simulate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...this.rule, ...this.test }),
      });

      this.result = await response.json();
    },
  },
};
</script>
```

### 3. Migration Validation

Validate policy migrations before applying:

```php
class PolicyMigration
{
    public function validateMigration(Policy $currentPolicy, Policy $newPolicy): void
    {
        $simulator = app(PolicySimulator::class);

        // Generate test cases from current policy rules
        $testCases = $this->generateTestCases($currentPolicy);

        $failures = [];

        foreach ($testCases as $testCase) {
            // Simulate with current policy
            $currentResult = $simulator->simulate(
                $currentPolicy,
                $testCase['subject'],
                $testCase['resource'],
                $testCase['action']
            );

            // Simulate with new policy
            $newResult = $simulator->simulate(
                $newPolicy,
                $testCase['subject'],
                $testCase['resource'],
                $testCase['action']
            );

            // Detect behavior changes
            if ($currentResult->effect !== $newResult->effect) {
                $failures[] = [
                    'subject' => $testCase['subject']->id,
                    'resource' => $testCase['resource']->id,
                    'action' => $testCase['action']->value,
                    'current_effect' => $currentResult->effect->value,
                    'new_effect' => $newResult->effect->value,
                ];
            }
        }

        if (!empty($failures)) {
            throw new PolicyMigrationException(
                "Migration would change authorization behavior",
                $failures
            );
        }
    }

    private function generateTestCases(Policy $policy): array
    {
        $testCases = [];

        foreach ($policy->rules as $rule) {
            // Create test case for each rule
            $testCases[] = [
                'subject' => new Subject($rule->subject),
                'resource' => new Resource($rule->resource ?? 'test:resource', 'Test'),
                'action' => new Action($rule->action),
            ];
        }

        return $testCases;
    }
}
```

### 4. Performance Profiling

Profile policy performance before deployment:

```php
class PolicyPerformanceProfiler
{
    public function profile(Policy $policy, int $iterations = 1000): array
    {
        $simulator = app(PolicySimulator::class);

        // Sample test cases
        $testCases = $this->generateSampleTestCases();

        $executionTimes = [];

        foreach ($testCases as $testCase) {
            for ($i = 0; $i < $iterations; $i++) {
                $result = $simulator->simulate(
                    $policy,
                    $testCase['subject'],
                    $testCase['resource'],
                    $testCase['action']
                );

                $executionTimes[] = $result->executionTime;
            }
        }

        return [
            'min' => min($executionTimes),
            'max' => max($executionTimes),
            'avg' => array_sum($executionTimes) / count($executionTimes),
            'median' => $this->median($executionTimes),
            'p95' => $this->percentile($executionTimes, 95),
            'p99' => $this->percentile($executionTimes, 99),
            'total_iterations' => count($executionTimes),
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $mid = floor($count / 2);

        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }

    private function percentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil((count($values) * $percentile) / 100) - 1;

        return $values[$index] ?? end($values);
    }
}

// Usage
$profiler = new PolicyPerformanceProfiler();
$stats = $profiler->profile($newPolicy);

echo "Performance Profile:\n";
echo "  Min: {$stats['min']}ms\n";
echo "  Max: {$stats['max']}ms\n";
echo "  Avg: {$stats['avg']}ms\n";
echo "  P95: {$stats['p95']}ms\n";
echo "  P99: {$stats['p99']}ms\n";

// Fail if too slow
if ($stats['p99'] > 10.0) {
    throw new Exception("Policy too slow: P99 {$stats['p99']}ms exceeds 10ms threshold");
}
```

---

## Testing Strategies

### Unit Testing with Simulation

```php
use Patrol\Core\Engine\PolicySimulator;
use Patrol\Core\ValueObjects\Effect;

test('policy allows editors to edit documents', function () {
    $simulator = new PolicySimulator(new PolicyEvaluator(new RbacRuleMatcher()));

    $policy = new Policy([
        new PolicyRule('role:editor', 'document:*', 'edit', Effect::Allow),
    ]);

    $result = $simulator->simulate(
        $policy,
        new Subject('user:123', ['roles' => ['role:editor']]),
        new Resource('document:456', 'Document'),
        new Action('edit')
    );

    expect($result->effect)->toBe(Effect::Allow);
    expect($result->executionTime)->toBeLessThan(5.0); // Performance assertion
});

test('policy denies viewers from deleting documents', function () {
    $simulator = new PolicySimulator(new PolicyEvaluator(new RbacRuleMatcher()));

    $policy = new Policy([
        new PolicyRule('role:viewer', 'document:*', 'read', Effect::Allow),
    ]);

    $result = $simulator->simulate(
        $policy,
        new Subject('user:789', ['roles' => ['role:viewer']]),
        new Resource('document:456', 'Document'),
        new Action('delete')
    );

    expect($result->effect)->toBe(Effect::Deny); // Default deny
});
```

### Integration Testing

```php
test('policy deployment validates against test suite', function () {
    $deploymentService = app(PolicyDeploymentService::class);

    $policy = new Policy([
        new PolicyRule('role:admin', '*', '*', Effect::Allow),
        new PolicyRule('role:editor', 'document:*', 'edit', Effect::Allow),
    ]);

    $testScenarios = [
        [
            'subject' => new Subject('user:admin', ['roles' => ['role:admin']]),
            'resource' => new Resource('secret:data', 'Secret'),
            'action' => new Action('delete'),
            'expectedEffect' => Effect::Allow,
            'maxExecutionTime' => 10.0,
        ],
        [
            'subject' => new Subject('user:editor', ['roles' => ['role:editor']]),
            'resource' => new Resource('document:123', 'Document'),
            'action' => new Action('edit'),
            'expectedEffect' => Effect::Allow,
            'maxExecutionTime' => 10.0,
        ],
    ];

    // Should not throw
    $deploymentService->deployPolicy($policy, $testScenarios);

    // Verify policy was saved
    expect(DB::table('patrol_policies')->count())->toBeGreaterThan(0);
});
```

---

## Best Practices

### 1. Comprehensive Test Coverage

Cover edge cases in simulations:

```php
$testScenarios = [
    // Happy path
    ['subject' => 'role:editor', 'resource' => 'document:*', 'action' => 'edit', 'expected' => Effect::Allow],

    // Boundary cases
    ['subject' => 'role:editor', 'resource' => 'document:1', 'action' => 'edit', 'expected' => Effect::Allow],
    ['subject' => 'role:editor', 'resource' => 'folder:*', 'action' => 'edit', 'expected' => Effect::Deny],

    // Negative cases
    ['subject' => 'role:viewer', 'resource' => 'document:*', 'action' => 'delete', 'expected' => Effect::Deny],
    ['subject' => 'role:guest', 'resource' => 'document:*', 'action' => 'read', 'expected' => Effect::Deny],

    // Wildcard matching
    ['subject' => '*', 'resource' => 'public:*', 'action' => 'read', 'expected' => Effect::Allow],
];
```

### 2. Performance Budgets

Set execution time budgets:

```php
const MAX_EXECUTION_TIME_MS = 5.0;

if ($result->executionTime > MAX_EXECUTION_TIME_MS) {
    throw new PolicyPerformanceException(
        "Policy exceeds performance budget: {$result->executionTime}ms > {MAX_EXECUTION_TIME_MS}ms"
    );
}
```

### 3. Simulation in CI/CD

Integrate simulation into deployment pipelines:

```yaml
# .github/workflows/deploy.yml
- name: Validate Policies
  run: php artisan patrol:simulate --test-suite=tests/policies/
```

### 4. Document Simulation Results

Log simulation outcomes for audit:

```php
$result = $simulator->simulate($policy, $subject, $resource, $action);

\Log::info('Policy simulation', [
    'policy_id' => $policy->name,
    'subject' => $subject->id,
    'resource' => $resource->id,
    'action' => $action->value,
    'effect' => $result->effect->value,
    'execution_time' => $result->executionTime,
]);
```

---

## Related Documentation

- **[Policy Comparison](./policy-comparison.md)** - Compare policy versions
- **[Testing](./testing.md)** - Comprehensive testing guide
- **PolicyFactory** (see testing.md) - Build test policies
- **Performance** (coming soon) - Optimization strategies

---

**Development tip:** Use simulation extensively during policy authoring. The immediate feedback loop helps catch errors early and ensures policies work as intended before deployment.
