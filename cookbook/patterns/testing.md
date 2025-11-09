# Testing Authorization - Complete Guide

Comprehensive testing strategies for Patrol authorization policies, including the new PolicyFactory helper for minimal-boilerplate test fixtures.

## PolicyFactory - Simplified Test Policy Creation

### Quick Start

The `PolicyFactory` provides fluent methods for creating test policies without verbose rule construction:

```php
use Patrol\Laravel\Testing\Factories\PolicyFactory;

// Single rule
$policy = PolicyFactory::allow('admin', 'document:*', 'delete');

// Multiple rules via cartesian product
$policy = PolicyFactory::allow(
    ['admin', 'editor'],  // 2 subjects
    'document:*',         // 1 resource  
    ['read', 'write']     // 2 actions
);
// Creates 4 rules: admin→read, admin→write, editor→read, editor→write
```

### Factory Methods

#### `allow()` - Create Allow Rules

```php
// Single permission
PolicyFactory::allow('role:editor', 'post:*', 'edit');

// Cartesian product (12 rules = 3×2×2)
PolicyFactory::allow(
    ['admin', 'editor', 'author'],
    ['post:*', 'draft:*'],
    ['create', 'edit']
);

// Custom priority
PolicyFactory::allow('admin', '*', '*', priority: 100);
```

#### `deny()` - Create Deny Rules

```php
// Block access
PolicyFactory::deny('guest', 'admin:*', '*');

// Block multiple combinations (4 rules)
PolicyFactory::deny(
    ['suspended', 'banned'],
    'content:*',
    ['create', 'edit']
);

// High-priority deny (deny-override)
PolicyFactory::deny('contractor', 'payroll:*', '*', priority: 1000);
```

#### `empty()` - Empty Policy

```php
// Default-deny baseline
$policy = PolicyFactory::empty();
// All authorizations return Deny
```

#### `merge()` - Combine Policies

```php
// Compose complex scenarios
$policy = PolicyFactory::merge(
    PolicyFactory::allow('*', 'public:*', 'read'),      // Public read
    PolicyFactory::allow('admin', '*', '*'),             // Admin full access
    PolicyFactory::deny('suspended', '*', '*', 100)      // Override with deny
);
```

---

## Testing Patterns

### Unit Testing with PolicyFactory

```php
use Patrol\Laravel\Testing\Factories\PolicyFactory;

test('editors can edit and publish posts', function () {
    $policy = PolicyFactory::allow('role:editor', 'post:*', ['edit', 'publish']);

    $result = Patrol::authorize(
        new Subject('user:123', ['roles' => ['role:editor']]),
        new Resource('post:456', 'Post'),
        new Action('edit')
    );

    expect($result)->toBe(Effect::Allow);
});

test('viewers cannot delete posts', function () {
    $policy = PolicyFactory::allow('role:viewer', 'post:*', 'read');
    $repository->save($policy);

    $result = Patrol::authorize(
        new Subject('user:789', ['roles' => ['role:viewer']]),
        new Resource('post:456', 'Post'),
        new Action('delete')
    );

    expect($result)->toBe(Effect::Deny); // No matching allow rule
});
```

### Testing Deny-Override

```php
test('deny rules override allow rules with higher priority', function () {
    $policy = PolicyFactory::merge(
        PolicyFactory::allow('admin', '*', '*', 10),
        PolicyFactory::deny('admin', 'restricted:*', '*', 100) // Higher priority
    );
    $repository->save($policy);

    // Admin blocked from restricted resources
    $result = Patrol::authorize(
        new Subject('user:admin', ['roles' => ['role:admin']]),
        new Resource('restricted:data', 'Restricted'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Deny);
});
```

### Testing Complex Policies

```php
test('multi-role user gets combined permissions', function () {
    $policy = PolicyFactory::merge(
        PolicyFactory::allow('role:editor', 'post:*', 'edit'),
        PolicyFactory::allow('role:publisher', 'post:*', 'publish')
    );
    $repository->save($policy);

    $userWithBothRoles = new Subject('user:123', [
        'roles' => ['role:editor', 'role:publisher']
    ]);

    // Can edit (from editor role)
    expect(Patrol::authorize($userWithBothRoles, $post, new Action('edit')))
        ->toBe(Effect::Allow);

    // Can publish (from publisher role)
    expect(Patrol::authorize($userWithBothRoles, $post, new Action('publish')))
        ->toBe(Effect::Allow);
});
```

---

## Integration Testing

### Database Repository Testing

```php
test('policies persist and load correctly', function () {
    $policy = PolicyFactory::allow(['admin', 'editor'], 'document:*', 'read');

    $repository->save($policy);

    $loaded = $repository->getPoliciesFor(
        new Subject('user:admin', ['roles' => ['role:admin']]),
        new Resource('document:123', 'Document')
    );

    expect($loaded->rules)->toHaveCount(2);
});
```

### Cache Testing

```php
test('cached policies match uncached', function () {
    $policy = PolicyFactory::allow('admin', 'document:*', 'read');

    $cachedRepo = new CachedPolicyRepository($baseRepo, $cache);
    $cachedRepo->save($policy);

    // First call hits database
    $result1 = $cachedRepo->getPoliciesFor($subject, $resource);

    // Second call hits cache
    $result2 = $cachedRepo->getPoliciesFor($subject, $resource);

    expect($result1->rules)->toEqual($result2->rules);
});
```

---

## Performance Testing

```php
test('batch authorization faster than N individual checks', function () {
    $policy = PolicyFactory::allow('role:user', 'document:*', 'read');
    $repository->save($policy);

    $documents = Document::factory()->count(100)->create();

    // Individual checks (N queries)
    $start = microtime(true);
    foreach ($documents as $doc) {
        Patrol::check($doc, 'read');
    }
    $individualTime = microtime(true) - $start;

    // Batch check (1 query)
    $start = microtime(true);
    $batchEvaluator->evaluateBatch($subject, $documents->all(), new Action('read'));
    $batchTime = microtime(true) - $start;

    expect($batchTime)->toBeLessThan($individualTime / 2); // At least 2x faster
});
```

---

## Related Documentation

- **[Policy Simulation](./policy-simulation.md)** - Test policies before deployment
- **[Policy Comparison](./policy-comparison.md)** - Compare policy versions

---

**Testing tip:** Use PolicyFactory extensively in tests. The cartesian product feature eliminates repetitive rule creation while keeping tests readable.
