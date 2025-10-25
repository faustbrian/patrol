# ABAC (Attribute-Based Access Control)

Dynamic authorization using attribute expressions and conditions for fine-grained control.

## Overview

ABAC (Attribute-Based Access Control) evaluates attributes of subjects, resources, actions, and the environment to make authorization decisions. Instead of static rules, ABAC uses expressions and conditions that are evaluated at runtime, enabling highly dynamic and context-aware permissions.

## Basic Concept

```
Attributes + Conditions → Dynamic Permission
if (condition) then allow/deny
```

## How ABAC Works

```
Step 1: Gather attributes from subject, resource, environment
┌─────────────┐  ┌──────────────┐  ┌─────────────────┐
│ Subject     │  │ Resource     │  │ Environment     │
│ id: 1       │  │ author_id: 1 │  │ hour: 14        │
│ dept: eng   │  │ status: draft│  │ ip: 192.168.1.1 │
│ level: 5    │  │ dept: eng    │  │ country: US     │
└─────────────┘  └──────────────┘  └─────────────────┘

Step 2: Evaluate condition expression
┌────────────────────────────────────────────────────┐
│ Condition: resource.author_id == subject.id        │
│                                                    │
│ Substitute values: 1 == 1                         │
│                                                    │
│ Evaluate: TRUE                                    │
└────────────────────────────────────────────────────┘

Step 3: Return effect if condition matches
        ┌──────┴──────┐
        │             │
        ▼             ▼
    ┌───────┐    ┌───────┐
    │ ALLOW │    │ DENY  │
    └───────┘    └───────┘
```

## Use Cases

- Time-based access (office hours only, temporary access)
- Location-based permissions (IP restrictions, geo-fencing)
- Ownership-based access (users edit their own content)
- Dynamic relationships (managers approve subordinate requests)
- Context-aware permissions (read if published, edit if draft)
- Attribute matching (same department, same team)
- Resource state conditions (locked, archived, published)

## Core Example

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AbacRuleMatcher, EffectResolver, AttributeResolver};

$policy = new Policy([
    // Users can edit their own articles
    new ConditionalPolicyRule(
        condition: 'resource.author_id == subject.id',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Managers can edit team member articles
    new ConditionalPolicyRule(
        condition: 'resource.author.manager_id == subject.id',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Users can only access published articles (unless they're the author)
    new ConditionalPolicyRule(
        condition: 'resource.status == "published" || resource.author_id == subject.id',
        resource: 'article:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Editors can edit any article during business hours
    new ConditionalPolicyRule(
        condition: 'subject.role == "editor" && environment.hour >= 9 && environment.hour <= 17',
        resource: 'article:*',
        action: 'edit',
        effect: Effect::Allow
    ),
]);

$attributeResolver = new AttributeResolver();
$evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

// User editing their own article
$subject = new Subject('user-1', ['id' => 1]);
$resource = new Resource('article-1', 'article', ['author_id' => 1]);
$action = new Action('edit');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow (condition matches: 1 == 1)
```

## Patterns

### Ownership-Based Access

```php
// Users own their resources
new ConditionalPolicyRule(
    condition: 'resource.owner_id == subject.id',
    resource: '*',
    action: '*',
    effect: Effect::Allow
);

// Team lead owns team resources
new ConditionalPolicyRule(
    condition: 'resource.team_id == subject.team_id && subject.role == "team_lead"',
    resource: 'project:*',
    action: 'manage',
    effect: Effect::Allow
);
```

### Time-Based Access

```php
// Access only during business hours
new ConditionalPolicyRule(
    condition: 'environment.hour >= 9 && environment.hour <= 17 && environment.weekday == true',
    resource: 'sensitive-data:*',
    action: 'access',
    effect: Effect::Allow
);

// Temporary access with expiration
new ConditionalPolicyRule(
    condition: 'environment.now <= resource.access_expires_at',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);
```

### Location-Based Access

```php
// Office network only
new ConditionalPolicyRule(
    condition: 'environment.ip_address startsWith "192.168.1."',
    resource: 'admin:*',
    action: '*',
    effect: Effect::Allow
);

// Geo-restricted content
new ConditionalPolicyRule(
    condition: 'environment.country in resource.allowed_countries',
    resource: 'content:*',
    action: 'view',
    effect: Effect::Allow
);
```

### Attribute Matching

```php
// Same department access
new ConditionalPolicyRule(
    condition: 'subject.department_id == resource.department_id',
    resource: 'report:*',
    action: 'read',
    effect: Effect::Allow
);

// Clearance level matching
new ConditionalPolicyRule(
    condition: 'subject.clearance_level >= resource.required_clearance',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver with Rich Attributes

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', [
            'id' => null,
            'role' => 'guest',
        ]);
    }

    return new Subject($user->id, [
        'id' => $user->id,
        'email' => $user->email,
        'role' => $user->role,
        'department_id' => $user->department_id,
        'team_id' => $user->team_id,
        'manager_id' => $user->manager_id,
        'clearance_level' => $user->clearance_level,
        'created_at' => $user->created_at,
    ]);
});
```

### Resource Resolver with Attributes

```php
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof \App\Models\Article) {
        return new Resource($resource->id, 'article', [
            'author_id' => $resource->author_id,
            'status' => $resource->status,
            'department_id' => $resource->department_id,
            'is_published' => $resource->is_published,
            'published_at' => $resource->published_at,
            'author' => [
                'id' => $resource->author->id,
                'manager_id' => $resource->author->manager_id,
            ],
        ]);
    }

    if ($resource instanceof \App\Models\Project) {
        return new Resource($resource->id, 'project', [
            'owner_id' => $resource->owner_id,
            'team_id' => $resource->team_id,
            'is_locked' => $resource->is_locked,
            'required_clearance' => $resource->required_clearance,
        ]);
    }

    return new Resource($resource->id, get_class($resource));
});
```

### Environment Attributes Provider

```php
use Patrol\Core\Contracts\AttributeProviderInterface;

class EnvironmentAttributeProvider implements AttributeProviderInterface
{
    public function getAttributes(): array
    {
        return [
            'now' => now(),
            'hour' => now()->hour,
            'weekday' => now()->isWeekday(),
            'ip_address' => request()->ip(),
            'country' => geoip(request()->ip())->country,
            'user_agent' => request()->userAgent(),
            'is_mobile' => request()->isMobile(),
        ];
    }
}

// Register in service provider
Patrol::addAttributeProvider(new EnvironmentAttributeProvider());
```

### Middleware

```php
// routes/web.php

// ABAC automatically evaluates conditions
Route::middleware(['patrol:article:edit'])->group(function () {
    Route::put('/articles/{article}', [ArticleController::class, 'update']);
});

// Conditional access
Route::middleware(['patrol:admin:access'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function edit(Article $article)
    {
        // ABAC checks ownership, status, etc.
        if (!Patrol::check($article, 'edit')) {
            abort(403, 'You can only edit your own articles');
        }

        return view('articles.edit', compact('article'));
    }

    public function publish(Article $article)
    {
        // Only editors or authors can publish
        Patrol::authorize($article, 'publish');

        $article->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('articles.show', $article);
    }
}

class DocumentController extends Controller
{
    public function download(Document $document)
    {
        // Check clearance level, location, time, etc.
        if (!Patrol::check($document, 'download')) {
            abort(403, 'Access denied based on security policy');
        }

        return response()->download($document->path);
    }
}
```

## Real-World Example: Dynamic Document System

```php
use Patrol\Core\ValueObjects\{ConditionalPolicyRule, Effect, Policy};

$policy = new Policy([
    // Own documents - full access
    new ConditionalPolicyRule(
        condition: 'resource.owner_id == subject.id',
        resource: 'document:*',
        action: '*',
        effect: Effect::Allow
    ),

    // Department members - read if published
    new ConditionalPolicyRule(
        condition: 'resource.department_id == subject.department_id && resource.status == "published"',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers - read team documents
    new ConditionalPolicyRule(
        condition: 'resource.owner.manager_id == subject.id',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers - approve team documents
    new ConditionalPolicyRule(
        condition: 'resource.owner.manager_id == subject.id && resource.status == "pending"',
        resource: 'document:*',
        action: 'approve',
        effect: Effect::Allow
    ),

    // Sensitive docs - office network only
    new ConditionalPolicyRule(
        condition: 'resource.sensitivity == "high" && environment.ip_address startsWith "10.0."',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Clearance-based access
    new ConditionalPolicyRule(
        condition: 'subject.clearance_level >= resource.required_clearance',
        resource: 'document:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Time-based restrictions on modifications
    new ConditionalPolicyRule(
        condition: 'environment.hour >= 9 && environment.hour <= 17',
        resource: 'document:*',
        action: 'delete',
        effect: Effect::Allow
    ),

    // Archive restrictions - no edits
    new ConditionalPolicyRule(
        condition: 'resource.is_archived == true',
        resource: 'document:*',
        action: 'edit',
        effect: Effect::Deny
    ),
]);
```

## Database Storage

```php
// Migration
Schema::create('abac_rules', function (Blueprint $table) {
    $table->id();
    $table->text('condition'); // Expression: "resource.owner_id == subject.id"
    $table->string('resource_type')->nullable();
    $table->string('resource_pattern')->default('*');
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->integer('priority')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class AbacRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('abac_rules')
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($row) => new ConditionalPolicyRule(
                condition: $row->condition,
                resource: $row->resource_type
                    ? "{$row->resource_type}:{$row->resource_pattern}"
                    : '*',
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Expression Language

Patrol's ABAC supports a powerful expression language:

### Comparison Operators
- `==` - Equality
- `!=` - Inequality
- `>`, `<`, `>=`, `<=` - Comparisons
- `in` - Membership
- `startsWith`, `endsWith`, `contains` - String operations

### Logical Operators
- `&&` - AND
- `||` - OR
- `!` - NOT

### Example Expressions

```php
// Simple equality
'subject.id == resource.owner_id'

// Complex conditions
'subject.role == "manager" && resource.department_id == subject.department_id'

// Array membership
'subject.id in resource.authorized_users'

// String operations
'environment.ip_address startsWith "192.168."'

// Nested attributes
'resource.author.department_id == subject.department_id'

// Multiple conditions
'(subject.role == "admin" || resource.owner_id == subject.id) && !resource.is_locked'
```

## Testing

```php
use Pest\Tests;

it('allows users to edit their own resources', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'resource.owner_id == subject.id',
            resource: 'article:*',
            action: 'edit',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $subject = new Subject('user-1', ['id' => 1]);
    $resource = new Resource('article-1', 'article', ['owner_id' => 1]);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('edit'));

    expect($result)->toBe(Effect::Allow);
});

it('denies access outside business hours', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'environment.hour >= 9 && environment.hour <= 17',
            resource: 'admin:*',
            action: 'access',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $attributeResolver->setEnvironmentAttributes(['hour' => 20]); // 8 PM

    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('user-1'),
        new Resource('admin-panel', 'admin'),
        new Action('access')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports attribute matching between subject and resource', function () {
    $policy = new Policy([
        new ConditionalPolicyRule(
            condition: 'subject.department_id == resource.department_id',
            resource: 'report:*',
            action: 'read',
            effect: Effect::Allow
        ),
    ]);

    $attributeResolver = new AttributeResolver();
    $evaluator = new PolicyEvaluator(new AbacRuleMatcher($attributeResolver), new EffectResolver());

    $subject = new Subject('user-1', ['department_id' => 5]);
    $resource = new Resource('report-1', 'report', ['department_id' => 5]);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Allow);
});
```

## Advanced Patterns

### Delegation

```php
// Users can delegate access
new ConditionalPolicyRule(
    condition: 'subject.id in resource.delegated_to',
    resource: 'document:*',
    action: 'read',
    effect: Effect::Allow
);

// Model implementation
class Document extends Model
{
    public function delegate(User $user): void
    {
        $delegations = $this->delegated_to ?? [];
        $delegations[] = $user->id;
        $this->update(['delegated_to' => array_unique($delegations)]);
    }
}
```

### Workflow States

```php
// Different permissions based on state
new ConditionalPolicyRule(
    condition: 'resource.status == "draft" && resource.author_id == subject.id',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Allow
);

new ConditionalPolicyRule(
    condition: 'resource.status == "under_review" && subject.role == "reviewer"',
    resource: 'article:*',
    action: 'review',
    effect: Effect::Allow
);

new ConditionalPolicyRule(
    condition: 'resource.status == "published"',
    resource: 'article:*',
    action: 'edit',
    effect: Effect::Deny
);
```

## Common Mistakes

### ❌ Mistake 1: Missing Required Attributes

```php
// DON'T: Condition uses attributes not provided
new PolicyRule('resource.owner_id == subject.id', 'article:*', 'edit', Effect::Allow);

// Subject missing id attribute
$subject = new Subject('user-1'); // ❌ No 'id' attribute!
```

**✅ DO THIS:**
```php
$subject = new Subject('user-1', ['id' => $user->id]); // ✅ Has id
```

---

### ❌ Mistake 2: Overly Complex Conditions

```php
// DON'T: Unreadable mega-condition
new PolicyRule(
    '(subject.dept == resource.dept && subject.level >= 3) || (subject.manager_id == resource.author.manager_id && resource.status != "archived") || subject.role == "admin"',
    'doc:*',
    'edit',
    Effect::Allow
);
```

**✅ DO THIS:**
```php
// Multiple clear rules
new PolicyRule('subject.role == "admin"', 'doc:*', 'edit', Effect::Allow),
new PolicyRule('subject.dept == resource.dept && subject.level >= 3', 'doc:*', 'edit', Effect::Allow),
```

---

### ❌ Mistake 3: Not Handling Null Values

```php
// DON'T: Assumes attribute exists
new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow);

// Breaks when: $resource = new Resource('article-1', 'article', []); // No author_id
```

**✅ DO THIS:**
```php
new PolicyRule('resource.author_id != null && resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow);
```

---

### ❌ Mistake 4: Using ABAC for Static Roles

```php
// DON'T: ABAC for simple role check
new PolicyRule('subject.role == "editor"', 'article:*', 'edit', Effect::Allow);
```

**✅ DO THIS:**
```php
// Use RBAC for static roles (faster)
new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow);

// Reserve ABAC for dynamic conditions
new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow);
```

---

## Best Practices

1. **Keep Conditions Simple**: Max 1-2 comparisons per rule
2. **Document Expressions**: Comment complex conditions
3. **Test Thoroughly**: ABAC is dynamic - test edge cases
4. **Handle Nulls**: Always check for null/missing attributes
5. **Attribute Consistency**: Ensure attributes always available
6. **Audit Logging**: Log which conditions triggered decisions
7. **Fallback Rules**: Always have default deny
8. **Combine with RBAC**: Use RBAC for roles, ABAC for dynamic rules
9. **Performance**: Cache attribute resolution
10. **Clear Naming**: Use descriptive attribute names

## When to Use ABAC

✅ **Good for:**
- Ownership-based permissions
- Time-sensitive access
- Location-based restrictions
- Dynamic relationships (manager-employee)
- Context-aware permissions
- Complex business logic
- State-based workflows

❌ **Avoid for:**
- Simple static permissions (use ACL or RBAC)
- Performance-critical paths (evaluate overhead)
- When attributes are unavailable
- Very simple permission models

## Performance Optimization

```php
// Cache attribute resolution
class CachedAttributeResolver extends AttributeResolver
{
    public function resolve(Subject $subject, Resource $resource): array
    {
        $cacheKey = "attributes:{$subject->id()}:{$resource->id()}";

        return Cache::remember($cacheKey, 300, function () use ($subject, $resource) {
            return parent::resolve($subject, $resource);
        });
    }
}

// Pre-filter with simple rules
$policy = new Policy([
    // Fast ACL check first
    new PolicyRule('role:admin', '*', '*', Effect::Allow),

    // Then ABAC for complex cases
    new ConditionalPolicyRule(
        condition: 'resource.owner_id == subject.id',
        resource: '*',
        action: '*',
        effect: Effect::Allow
    ),
]);
```

## Debugging ABAC Issues

### Problem: Condition should match but doesn't

**Step 1: Dump actual attribute values**
```php
$subject = Patrol::getCurrentSubject();
dd($subject->attributes); // Check: Do attributes exist?
```

**Step 2: Test condition manually**
```php
// Condition: 'resource.author_id == subject.id'
$subjectId = $subject->attributes['id'] ?? 'MISSING';
$authorId = $resource->attributes['author_id'] ?? 'MISSING';
dump("Comparing: {$authorId} == {$subjectId}");
```

**Step 3: Check type mismatches**
```php
// String "1" != Integer 1 in strict comparison
$subject = new Subject('user-1', ['id' => (int) $user->id]); // ✅ Ensure int
```

---

### Problem: Attribute not found errors

❌ **Nested attribute not loaded**
```php
// Condition: resource.author.manager_id
// Fix: Include nested attributes in resolver
Patrol::resolveResource(function ($article) {
    return new Resource($article->id, 'article', [
        'author' => [ // ✅ Include nested object
            'id' => $article->author->id,
            'manager_id' => $article->author->manager_id,
        ],
    ]);
});
```

---

### Problem: ABAC is slow (> 100ms)

**Solutions:**
```php
// 1. Cache subject
Patrol::resolveSubject(function () {
    return Cache::remember('subject:' . auth()->id(), 300, fn() => ...);
});

// 2. Eager load relationships
$resource->load('author');

// 3. Simplify complex conditions (break into multiple rules)
```

---

## Related Models

- [ACL](./ACL.md) - Simple static permissions
- [RBAC](./RBAC.md) - Role-based structure (use with ABAC)
- [Deny-Override](./Deny-Override.md) - Explicit denies with ABAC
- [Priority-Based](./Priority-Based.md) - Rule ordering with ABAC
