# API Reference

Complete reference for Patrol's core value objects, engines, and Laravel integration.

## Core Value Objects

### PolicyRule

Immutable policy rule with full type safety.

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Priority};

$rule = new PolicyRule(
    subject: 'user-1',              // Subject identifier or expression
    resource: 'document:*',          // Resource identifier with wildcards
    action: 'read',                  // Action to authorize
    effect: Effect::Allow,           // Allow or Deny
    priority: Priority::Normal,      // Rule priority (Critical, High, Normal, Low)
    domain: 'tenant-1',              // Optional domain/tenant
    conditions: ['key' => 'value'],  // Optional conditions
    metadata: ['audit' => true]      // Optional metadata
);
```

**Parameters:**
- `subject` (string) - Subject identifier, role, or ABAC expression
- `resource` (string) - Resource identifier with optional wildcards (`*`)
- `action` (string) - Action to authorize
- `effect` (Effect) - `Effect::Allow` or `Effect::Deny`
- `priority` (Priority, optional) - Rule priority for evaluation order
- `domain` (string|null, optional) - Domain/tenant context
- `conditions` (array, optional) - Additional conditions
- `metadata` (array, optional) - Custom metadata for logging/auditing

---

### Subject

Represents the actor requesting access.

```php
use Patrol\Core\ValueObjects\Subject;

// Basic subject
$subject = new Subject('user-123');

// Subject with roles
$subject = new Subject('user-123', [
    'roles' => ['editor', 'reviewer'],
]);

// Subject with attributes (for ABAC)
$subject = new Subject('user-123', [
    'department' => 'engineering',
    'level' => 5,
    'verified' => true,
]);

// Multi-tenant subject
$subject = new Subject('user-123', [
    'domain' => 'tenant-1',
    'domain_roles' => [
        'tenant-1' => ['admin'],
        'tenant-2' => ['viewer'],
    ],
]);
```

**Constructor:**
```php
new Subject(string $id, array $attributes = [])
```

**Parameters:**
- `id` (string) - Subject identifier
- `attributes` (array, optional) - Subject attributes for ABAC/RBAC

**Common Attributes:**
- `roles` (array) - User roles for RBAC
- `domain` (string) - Current domain/tenant
- `domain_roles` (array) - Roles per domain for multi-tenancy
- Custom attributes for ABAC conditions

**Methods:**
- `id(): string` - Get subject identifier
- `attributes(): array` - Get all attributes
- `getAttribute(string $key): mixed` - Get specific attribute

---

### Resource

Represents the object being accessed.

```php
use Patrol\Core\ValueObjects\Resource;

// Basic resource
$resource = new Resource('doc-1', 'document');

// Resource with attributes (for ABAC)
$resource = new Resource('doc-1', 'document', [
    'owner' => 'user-123',
    'status' => 'published',
    'department' => 'engineering',
]);

// Resource with roles (for RBAC)
$resource = new Resource('doc-1', 'document', [
    'roles' => ['public-document', 'featured'],
]);
```

**Constructor:**
```php
new Resource(string $id, string $type, array $attributes = [])
```

**Parameters:**
- `id` (string) - Resource identifier
- `type` (string) - Resource type (e.g., 'document', 'post', 'user')
- `attributes` (array, optional) - Resource attributes for ABAC

**Methods:**
- `id(): string` - Get resource identifier
- `type(): string` - Get resource type
- `attributes(): array` - Get all attributes
- `getAttribute(string $key): mixed` - Get specific attribute

---

### Action

Represents the operation being performed.

```php
use Patrol\Core\ValueObjects\Action;

$action = new Action('read');
$action = new Action('write');
$action = new Action('delete');

// HTTP methods for RESTful
$action = new Action('GET');
$action = new Action('POST');
```

**Constructor:**
```php
new Action(string $name)
```

**Parameters:**
- `name` (string) - Action name

**Methods:**
- `name(): string` - Get action name
- `matches(string $pattern): bool` - Check if action matches pattern (supports wildcards)

---

### Effect

Authorization decision result.

```php
use Patrol\Core\ValueObjects\Effect;

Effect::Allow  // Grant access
Effect::Deny   // Deny access (overrides Allow)
```

**Enum Values:**
- `Effect::Allow` - Grant permission
- `Effect::Deny` - Deny permission (takes precedence in deny-override logic)

---

### Priority

Rule evaluation priority.

```php
use Patrol\Core\ValueObjects\Priority;

Priority::Critical  // Highest priority (900)
Priority::High      // High priority (700)
Priority::Normal    // Default priority (500)
Priority::Low       // Low priority (300)
```

**Enum Values:**
- `Priority::Critical` - 900 (highest)
- `Priority::High` - 700
- `Priority::Normal` - 500 (default)
- `Priority::Low` - 300

**Methods:**
- `value(): int` - Get numeric priority value
- `from(int $value): Priority` - Create from numeric value

---

### Policy

Collection of policy rules.

```php
use Patrol\Core\ValueObjects\Policy;

$policy = new Policy([
    new PolicyRule('role:admin', '*', '*', Effect::Allow),
    new PolicyRule('role:editor', 'post:*', 'edit', Effect::Allow),
]);
```

**Constructor:**
```php
new Policy(array $rules)
```

**Parameters:**
- `rules` (array<PolicyRule>) - Array of PolicyRule objects

**Methods:**
- `rules(): array` - Get all rules
- `addRule(PolicyRule $rule): void` - Add a rule
- `count(): int` - Count total rules

---

## Policy Evaluation Engine

### PolicyEvaluator

Core authorization engine that evaluates policies against authorization requests.

```php
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\{AclRuleMatcher, RbacRuleMatcher, AbacRuleMatcher, RestfulRuleMatcher};
use Patrol\Core\Engine\EffectResolver;

// Initialize with matcher strategy
$evaluator = new PolicyEvaluator(
    matcher: new AclRuleMatcher(),      // or RbacRuleMatcher, AbacRuleMatcher, RestfulRuleMatcher
    effectResolver: new EffectResolver()
);

// Evaluate authorization
$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// Returns: Effect::Allow or Effect::Deny
```

**Constructor:**
```php
new PolicyEvaluator(
    RuleMatcherInterface $matcher,
    EffectResolverInterface $effectResolver
)
```

**Methods:**
- `evaluate(Policy $policy, Subject $subject, Resource $resource, Action $action): Effect` - Evaluate authorization

**Available Matchers:**
- `AclRuleMatcher` - Basic ACL matching
- `RbacRuleMatcher` - Role-based matching
- `AbacRuleMatcher` - Attribute-based matching with expressions
- `RestfulRuleMatcher` - HTTP path/method matching
- `CompositeRuleMatcher` - Combine multiple matchers

---

### Combining Multiple Matchers

Chain matchers for complex authorization.

```php
use Patrol\Core\Engine\CompositeRuleMatcher;

$compositeMatcher = new CompositeRuleMatcher([
    new AclRuleMatcher(),
    new RbacRuleMatcher(),
    new AbacRuleMatcher(),
    new RestfulRuleMatcher(),
]);

$evaluator = new PolicyEvaluator($compositeMatcher, new EffectResolver());
```

**Use Case:** When you need multiple authorization models in the same application (e.g., RBAC for roles + ABAC for ownership).

---

## Laravel Integration

### Patrol Facade

#### Patrol::check()

Check authorization for a resource.

```php
use Patrol\Laravel\Facades\Patrol;

// Simple check
if (Patrol::check($document, 'read')) {
    // User can read document
}

// With custom subject
if (Patrol::check($document, 'edit', $otherUser)) {
    // Other user can edit document
}
```

**Signature:**
```php
Patrol::check(mixed $resource, string $action, ?Subject $subject = null): bool
```

**Parameters:**
- `resource` (mixed) - Eloquent model, Resource object, or resource identifier
- `action` (string) - Action to check
- `subject` (Subject|null, optional) - Custom subject (defaults to current user)

**Returns:** `bool` - True if allowed, false if denied

---

#### Patrol::authorize()

Throw exception if unauthorized.

```php
// Throws AuthorizationException if denied
Patrol::authorize($document, 'delete');

// Continues if allowed
```

**Signature:**
```php
Patrol::authorize(mixed $resource, string $action, ?Subject $subject = null): void
```

**Throws:** `Illuminate\Auth\Access\AuthorizationException` if denied

---

#### Patrol::filter()

Batch authorization for collections.

```php
// Filter collection to only authorized resources
$authorizedDocs = Patrol::filter($documents, 'read');

// Returns collection of documents the current user can read
```

**Signature:**
```php
Patrol::filter(Collection $resources, string $action, ?Subject $subject = null): Collection
```

**Parameters:**
- `resources` (Collection) - Collection of resources to filter
- `action` (string) - Action to check
- `subject` (Subject|null, optional) - Custom subject

**Returns:** `Collection` - Filtered collection with only authorized resources

---

### Patrol Resolvers

Configure global resolvers for subject, tenant, and resource resolution.

#### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

// Subject resolver (who is making the request)
Patrol::resolveSubject(function () {
    return new Subject(
        auth()->id(),
        [
            'roles' => auth()->user()?->roles->pluck('name')->all() ?? [],
            'department' => auth()->user()?->department,
        ]
    );
});
```

**Signature:**
```php
Patrol::resolveSubject(Closure $callback): void
```

**Callback Signature:**
```php
function (): Subject
```

---

#### Tenant Resolver

```php
// Tenant resolver (for multi-tenancy)
Patrol::resolveTenant(fn() => auth()->user()?->current_tenant_id);
```

**Signature:**
```php
Patrol::resolveTenant(Closure $callback): void
```

**Callback Signature:**
```php
function (): string|int|null
```

---

#### Resource Resolver

```php
// Resource resolver (convert ID to Resource)
Patrol::resolveResource(function ($resourceId, $resourceType) {
    $model = match ($resourceType) {
        'document' => Document::find($resourceId),
        'project' => Project::find($resourceId),
        default => null,
    };

    return $model ? new Resource(
        $model->id,
        $resourceType,
        $model->toArray()
    ) : null;
});
```

**Signature:**
```php
Patrol::resolveResource(Closure $callback): void
```

**Callback Signature:**
```php
function (string|int $resourceId, string $resourceType): ?Resource
```

---

### Model Trait

#### HasPatrolAuthorization

Add Patrol authorization methods to Eloquent models.

```php
use Patrol\Laravel\Concerns\HasPatrolAuthorization;

class User extends Authenticatable
{
    use HasPatrolAuthorization;
}

// Usage
if ($user->can('edit', $post)) {
    // User can edit the post
}

$user->authorize('delete', $document); // Throws if denied

if ($user->canAny(['read', 'edit'], $post)) {
    // User can read OR edit
}
```

**Methods:**
- `can(string $action, mixed $resource): bool` - Check permission
- `cannot(string $action, mixed $resource): bool` - Check denial
- `authorize(string $action, mixed $resource): void` - Authorize or throw
- `canAny(array $actions, mixed $resource): bool` - Check any permission

---

### Laravel Native Authorization

Patrol integrates seamlessly with Laravel's authorization system via `Gate::before()`. Use standard Laravel methods:

```php
public function update(Request $request, Post $post)
{
    // Authorize or throw 403
    $this->authorize('edit', $post);

    // Check without throwing
    if ($request->user()->can('publish', $post)) {
        $post->publish();
    }

    // Using Gate facade
    if (Gate::allows('delete', $post)) {
        $post->delete();
    }
}
```

**Available Methods:**
- `$this->authorize(string $ability, mixed $resource)` - Throws AuthorizationException if denied
- `$user->can(string $ability, mixed $resource): bool` - Returns boolean
- `Gate::allows(string $ability, mixed $resource): bool` - Returns boolean
- `Gate::denies(string $ability, mixed $resource): bool` - Returns boolean

---

### Blade Directives

Use Laravel's standard `@can` directive - Patrol integrates automatically.

```blade
@can('edit', $post)
    <button>Edit Post</button>
@endcan

@cannot('delete', $post)
    <p class="text-muted">You cannot delete this post</p>
@endcannot
```

**Note:** Patrol automatically integrates with Laravel's Gate, so standard `@can` directives work out of the box.

---

### Middleware

Protect routes with authorization checks.

```php
Route::middleware(['patrol:posts:edit'])->get('/posts/{post}/edit', ...);

// With route model binding
Route::middleware(['patrol:{post}:edit'])->get('/posts/{post}/edit', ...);
```

**Syntax:**
```
patrol:<resource>:<action>
patrol:{binding}:<action>  // Uses route model binding
```

---

## Policy Repository

Implement custom policy storage.

### PolicyRepositoryInterface

```php
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\{Policy, PolicyRule};

class CustomPolicyRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        // Load from database
        $rules = DB::table('policy_rules')
            ->where('active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject,
                resource: $row->resource,
                action: $row->action,
                effect: Effect::from($row->effect),
                priority: Priority::from($row->priority),
                domain: $row->domain,
            ))
            ->all();

        return new Policy($rules);
    }

    public function save(Policy $policy): void
    {
        // Persist to database
        foreach ($policy->rules() as $rule) {
            DB::table('policy_rules')->insert([
                'subject' => $rule->subject,
                'resource' => $rule->resource,
                'action' => $rule->action,
                'effect' => $rule->effect->value,
                'priority' => $rule->priority->value,
                'domain' => $rule->domain,
            ]);
        }
    }
}
```

**Methods:**
- `find(): Policy` - Load policy from storage
- `save(Policy $policy): void` - Persist policy to storage

See [Persisting Policies](./persisting-policies.md) for storage implementations.

---

## Interactive Examples

Try these examples in `php artisan tinker`:

### Example 1: Basic ACL

```php
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\{AclRuleMatcher, EffectResolver};
use Patrol\Core\ValueObjects\{Subject, Resource, Action, Policy, PolicyRule, Effect};

$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

$policy = new Policy([
    new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
]);

$subject = new Subject('user-1');
$resource = new Resource('doc-1', 'document');
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow
```

### Example 2: ABAC with Owner Check

```php
$policy = new Policy([
    new PolicyRule(
        'resource.owner == subject.id',
        'document:*',
        'edit',
        Effect::Allow
    ),
]);

$subject = new Subject('user-123', ['id' => 123]);
$resource = new Resource('doc-1', 'document', ['owner' => 123]);
$action = new Action('edit');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow
```

### Example 3: Deny Override

```php
$policy = new Policy([
    new PolicyRule('user-1', 'document:*', 'read', Effect::Allow),
    new PolicyRule('user-1', 'document:secret-*', 'read', Effect::Deny),
]);

$subject = new Subject('user-1');
$resource = new Resource('document:secret-doc', 'document');
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Deny (deny overrides allow)
```

---

## Related Guides

- [Policy Builders](./policy-builders.md) - Fluent API for building policies
- [CLI Tools](./cli-tools.md) - Command-line testing and debugging
- [Persisting Policies](./persisting-policies.md) - Storage implementations
- [Getting Started](./getting-started.md) - Beginner's guide
