# RBAC with Resource Roles

Both users and resources have roles for sophisticated role-based authorization.

## Overview

RBAC with Resource Roles extends traditional RBAC by assigning roles to both users AND resources. This enables sophisticated scenarios where user roles interact with resource roles to determine access. For example, a "member" user role might have different permissions on "public" vs "private" resources.

## Basic Concept

```
User Role + Resource Role + Action = Permission
(subject.role, resource.role, action) → allow/deny
```

## How RBAC with Resource Roles Works

```
Step 1: Incoming authorization check
┌──────────────────────────────────────────────────────┐
│ Subject: user-1                                      │
│   - User Role: "manager"                             │
│   - Clearance: "secret"                              │
│                                                      │
│ Resource: document-456                               │
│   - Resource Role: "confidential"                    │
│   - Classification: "confidential"                   │
│                                                      │
│ Action: read                                         │
└──────────────────────────────────────────────────────┘
                       ▼
Step 2: Match BOTH subject role AND resource role
┌──────────────────────────────────────────────────────┐
│ Looking for rule matching:                          │
│   subject.role = "manager"                           │
│   resource.role = "confidential"                     │
│   action = "read"                                    │
└──────────────────────────────────────────────────────┘
                       ▼
Step 3: Check policy rules (all must match!)
┌───────────────────────────────────────────────────────────┐
│ ❌ Rule 1: role:employee × classification:confidential    │
│    - Subject role NO MATCH (employee ≠ manager)          │
│                                                           │
│ ❌ Rule 2: role:manager × classification:top-secret      │
│    - Resource role NO MATCH (top-secret ≠ confidential) │
│                                                           │
│ ✅ Rule 3: role:manager × classification:confidential    │
│    - Subject role MATCH ✓ (manager = manager)           │
│    - Resource role MATCH ✓ (confidential = confidential)│
│    - Action MATCH ✓ (read = read)                       │
│    - Effect: ALLOW                                       │
└───────────────────────────────────────────────────────────┘
                       ▼
Step 4: Return matched rule effect
┌─────────────────────────────────┐
│ Result: ALLOW                   │
│ Reason: Manager can read        │
│         confidential docs       │
└─────────────────────────────────┘
```

**Key Difference from Basic RBAC**: Basic RBAC only checks user roles. This model creates a **matrix** where permissions depend on BOTH user role AND resource role interacting together.

### Clearance Hierarchy Visualization

```
User Clearance Levels          Can Access Resource Classifications
┌───────────────────┐         ┌────────────────────────────────────┐
│                   │         │                                    │
│ Top Secret (L4)   ├────────►│ Top Secret, Secret, Confidential,  │
│                   │         │ Internal, Public                   │
│                   │         │                                    │
├───────────────────┤         ├────────────────────────────────────┤
│                   │         │                                    │
│ Secret (L3)       ├────────►│ Secret, Confidential, Internal,    │
│                   │         │ Public                             │
│                   │         │                                    │
├───────────────────┤         ├────────────────────────────────────┤
│                   │         │                                    │
│ Confidential (L2) ├────────►│ Confidential, Internal, Public     │
│                   │         │                                    │
├───────────────────┤         ├────────────────────────────────────┤
│                   │         │                                    │
│ Internal (L1)     ├────────►│ Internal, Public                   │
│                   │         │                                    │
├───────────────────┤         ├────────────────────────────────────┤
│                   │         │                                    │
│ None/Guest (L0)   ├────────►│ Public only                        │
│                   │         │                                    │
└───────────────────┘         └────────────────────────────────────┘
```

## Use Cases

- Document classification systems (public/private/confidential documents)
- Multi-level security clearances (user clearance level vs document classification)
- Content visibility levels (subscribers see premium content)
- Organizational hierarchies (managers access department-specific resources)
- Medical records systems (doctor role + patient sensitivity level)
- Military/government systems (clearance-based access)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, RbacRuleMatcher, EffectResolver};

$policy = new Policy([
    // Managers can read department resources
    new PolicyRule(
        subject: 'role:manager',
        resource: 'resource-role:department',
        action: 'read',
        effect: Effect::Allow
    ),

    // Managers can edit department resources
    new PolicyRule(
        subject: 'role:manager',
        resource: 'resource-role:department',
        action: 'edit',
        effect: Effect::Allow
    ),

    // Employees can only read department resources
    new PolicyRule(
        subject: 'role:employee',
        resource: 'resource-role:department',
        action: 'read',
        effect: Effect::Allow
    ),

    // Only admins can access confidential resources
    new PolicyRule(
        subject: 'role:admin',
        resource: 'resource-role:confidential',
        action: '*',
        effect: Effect::Allow
    ),

    // Everyone can read public resources
    new PolicyRule(
        subject: 'role:*',
        resource: 'resource-role:public',
        action: 'read',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

// Manager accessing department resource
$subject = new Subject('user-1', ['roles' => ['manager']]);
$resource = new Resource('doc-123', 'document', ['role' => 'department']);
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Allow

// Employee accessing confidential resource
$subject = new Subject('user-2', ['roles' => ['employee']]);
$resource = new Resource('doc-456', 'document', ['role' => 'confidential']);
$action = new Action('read');

$result = $evaluator->evaluate($policy, $subject, $resource, $action);
// => Effect::Deny
```

## Patterns

### Security Clearance System

```php
// Top Secret clearance can access all levels
new PolicyRule(
    subject: 'clearance:top-secret',
    resource: 'classification:*',
    action: 'read',
    effect: Effect::Allow
);

// Secret clearance can access secret and below
new PolicyRule(
    subject: 'clearance:secret',
    resource: 'classification:secret',
    action: 'read',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'clearance:secret',
    resource: 'classification:confidential',
    action: 'read',
    effect: Effect::Allow
);

// Confidential clearance only
new PolicyRule(
    subject: 'clearance:confidential',
    resource: 'classification:confidential',
    action: 'read',
    effect: Effect::Allow
);
```

### Content Subscription Tiers

```php
// Premium subscribers access all content
new PolicyRule(
    subject: 'subscription:premium',
    resource: 'content-tier:*',
    action: 'view',
    effect: Effect::Allow
);

// Basic subscribers access free and basic content
new PolicyRule(
    subject: 'subscription:basic',
    resource: 'content-tier:free',
    action: 'view',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'subscription:basic',
    resource: 'content-tier:basic',
    action: 'view',
    effect: Effect::Allow
);

// Free users only access free content
new PolicyRule(
    subject: 'subscription:free',
    resource: 'content-tier:free',
    action: 'view',
    effect: Effect::Allow
);
```

### Healthcare Record Access

```php
// Doctors can access general and moderate sensitivity
new PolicyRule(
    subject: 'role:doctor',
    resource: 'sensitivity:general',
    action: 'read',
    effect: Effect::Allow
);

new PolicyRule(
    subject: 'role:doctor',
    resource: 'sensitivity:moderate',
    action: 'read',
    effect: Effect::Allow
);

// Only psychiatrists can access mental health records
new PolicyRule(
    subject: 'role:psychiatrist',
    resource: 'sensitivity:mental-health',
    action: 'read',
    effect: Effect::Allow
);

// Nurses can only access general records
new PolicyRule(
    subject: 'role:nurse',
    resource: 'sensitivity:general',
    action: 'read',
    effect: Effect::Allow
);
```

## Laravel Integration

### Resource Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Resource;

Patrol::resolveResource(function ($resource) {
    if ($resource instanceof \App\Models\Document) {
        return new Resource(
            $resource->id,
            'document',
            [
                'role' => $resource->classification, // public, department, confidential
                'department_id' => $resource->department_id,
                'owner_id' => $resource->owner_id,
            ]
        );
    }

    if ($resource instanceof \App\Models\Article) {
        return new Resource(
            $resource->id,
            'article',
            [
                'role' => $resource->tier, // free, basic, premium
                'published' => $resource->is_published,
            ]
        );
    }

    return new Resource($resource->id, get_class($resource));
});
```

### Subject Resolver

```php
Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', ['roles' => ['guest'], 'clearance' => 'none']);
    }

    return new Subject($user->id, [
        'roles' => $user->roles->pluck('name')->all(),
        'clearance' => $user->clearance_level, // confidential, secret, top-secret
        'department_id' => $user->department_id,
    ]);
});
```

### Middleware

```php
// routes/web.php

// Route requires matching user and resource roles
Route::middleware(['patrol:document:read'])->get('/documents/{document}', function (Document $document) {
    return view('documents.show', compact('document'));
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class DocumentController extends Controller
{
    public function show(Document $document)
    {
        // Patrol automatically checks user role against document classification
        if (!Patrol::check($document, 'read')) {
            abort(403, 'You do not have clearance to view this document');
        }

        return view('documents.show', compact('document'));
    }

    public function index()
    {
        // Filter documents based on user clearance
        $documents = Document::all();
        $accessible = Patrol::filter($documents, 'read');

        return view('documents.index', compact('accessible'));
    }
}

class ArticleController extends Controller
{
    public function show(Article $article)
    {
        // Check user subscription tier against content tier
        if (!Patrol::check($article, 'view')) {
            return redirect()->route('pricing')
                ->with('error', 'This content requires a premium subscription');
        }

        return view('articles.show', compact('article'));
    }
}
```

## Real-World Example: Enterprise Document Management

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Public documents - everyone can read
    new PolicyRule('role:*', 'classification:public', 'read', Effect::Allow),

    // Internal documents - employees and above
    new PolicyRule('role:employee', 'classification:internal', 'read', Effect::Allow),
    new PolicyRule('role:manager', 'classification:internal', '*', Effect::Allow),

    // Confidential - managers and executives
    new PolicyRule('role:manager', 'classification:confidential', 'read', Effect::Allow),
    new PolicyRule('role:executive', 'classification:confidential', '*', Effect::Allow),

    // Top Secret - executives only
    new PolicyRule('role:executive', 'classification:top-secret', '*', Effect::Allow),

    // Department-specific access
    new PolicyRule(
        subject: 'department:sales',
        resource: 'department:sales',
        action: 'read',
        effect: Effect::Allow
    ),

    new PolicyRule(
        subject: 'department:engineering',
        resource: 'department:engineering',
        action: 'read',
        effect: Effect::Allow
    ),

    // Cross-department for managers
    new PolicyRule('role:manager', 'department:*', 'read', Effect::Allow),

    // Admins can access everything
    new PolicyRule('role:admin', 'classification:*', '*', Effect::Allow),
]);
```

## Database Storage

```php
// Migrations
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->enum('classification', ['public', 'internal', 'confidential', 'top-secret']);
    $table->foreignId('department_id')->nullable()->constrained();
    $table->foreignId('owner_id')->constrained('users');
    $table->timestamps();
});

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->enum('clearance_level', ['none', 'confidential', 'secret', 'top-secret']);
    $table->foreignId('department_id')->nullable()->constrained();
    $table->timestamps();
});

Schema::create('role_resource_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('subject_role'); // user role: manager, employee
    $table->string('resource_role'); // resource classification: public, confidential
    $table->string('action');
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->timestamps();

    $table->unique(['subject_role', 'resource_role', 'action'], 'role_resource_permission_unique');
});

// Models
class Document extends Model
{
    protected $fillable = ['title', 'content', 'classification', 'department_id', 'owner_id'];

    public function getResourceRole(): string
    {
        return $this->classification;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}

class User extends Authenticatable
{
    public function hasAccessToClassification(string $classification): bool
    {
        $clearanceHierarchy = [
            'top-secret' => ['top-secret', 'confidential', 'internal', 'public'],
            'secret' => ['confidential', 'internal', 'public'],
            'confidential' => ['confidential', 'internal', 'public'],
            'none' => ['public'],
        ];

        return in_array($classification, $clearanceHierarchy[$this->clearance_level] ?? []);
    }
}

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class ResourceRoleRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('role_resource_permissions')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject_role,
                resource: "classification:{$row->resource_role}",
                action: $row->action,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows users with matching clearance to access classified documents', function () {
    $policy = new Policy([
        new PolicyRule('clearance:secret', 'classification:confidential', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['clearance' => 'secret']);
    $resource = new Resource('doc-1', 'document', ['role' => 'confidential']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Allow);
});

it('denies users without sufficient clearance', function () {
    $policy = new Policy([
        new PolicyRule('clearance:top-secret', 'classification:top-secret', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['clearance' => 'confidential']);
    $resource = new Resource('doc-1', 'document', ['role' => 'top-secret']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));

    expect($result)->toBe(Effect::Deny);
});

it('supports subscription tier based content access', function () {
    $policy = new Policy([
        new PolicyRule('subscription:premium', 'content-tier:premium', 'view', Effect::Allow),
        new PolicyRule('subscription:basic', 'content-tier:premium', 'view', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(new RbacRuleMatcher(), new EffectResolver());

    // Premium user can access
    $subject = new Subject('user-1', ['subscription' => 'premium']);
    $resource = new Resource('article-1', 'article', ['role' => 'premium']);

    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('view'));
    expect($result)->toBe(Effect::Allow);

    // Basic user cannot
    $subject = new Subject('user-2', ['subscription' => 'basic']);
    $result = $evaluator->evaluate($policy, $subject, $resource, new Action('view'));
    expect($result)->toBe(Effect::Deny);
});
```

## Clearance Hierarchy Helper

```php
class ClearanceService
{
    private array $hierarchy = [
        'top-secret' => 4,
        'secret' => 3,
        'confidential' => 2,
        'internal' => 1,
        'public' => 0,
    ];

    public function canAccess(string $userClearance, string $resourceClassification): bool
    {
        $userLevel = $this->hierarchy[$userClearance] ?? -1;
        $resourceLevel = $this->hierarchy[$resourceClassification] ?? 999;

        return $userLevel >= $resourceLevel;
    }

    public function getAccessibleClassifications(string $userClearance): array
    {
        $userLevel = $this->hierarchy[$userClearance] ?? -1;

        return array_keys(array_filter(
            $this->hierarchy,
            fn($level) => $userLevel >= $level
        ));
    }
}

// Usage in repository
class HierarchicalClearanceRepository implements PolicyRepositoryInterface
{
    public function __construct(private ClearanceService $clearanceService)
    {
    }

    public function find(): Policy
    {
        $rules = [];

        foreach ($this->hierarchy as $clearance => $level) {
            $accessible = $this->clearanceService->getAccessibleClassifications($clearance);

            foreach ($accessible as $classification) {
                $rules[] = new PolicyRule(
                    subject: "clearance:{$clearance}",
                    resource: "classification:{$classification}",
                    action: 'read',
                    effect: Effect::Allow
                );
            }
        }

        return new Policy($rules);
    }
}
```

## Common Mistakes

### ❌ Mistake 1: Missing Resource Role in Resource Attributes

**Problem**: Resource created without `role` attribute, breaking authorization checks.

```php
// DON'T: Resource missing the 'role' attribute
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof Document) {
        return new Resource(
            $resource->id,
            'document',
            [
                'title' => $resource->title,  // ❌ Wrong attributes
                'owner_id' => $resource->owner_id,
                // Missing: 'role' => $resource->classification
            ]
        );
    }
});

// Policy rule expects 'classification:confidential' but resource has no role
new PolicyRule('role:manager', 'classification:confidential', 'read', Effect::Allow);

// Authorization check FAILS - resource role not found
```

**Why it's wrong**: The `RbacRuleMatcher` looks for `resource-role:*` patterns and matches them against the `role` attribute in resource metadata. Without this attribute, no rules match.

**✅ DO THIS:**
```php
// Correct: Include 'role' attribute
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof Document) {
        return new Resource(
            $resource->id,
            'document',
            [
                'role' => $resource->classification,  // ✓ Maps to policy rules
                'owner_id' => $resource->owner_id,
            ]
        );
    }
});
```

---

### ❌ Mistake 2: Clearance Hierarchy Not Enforced

**Problem**: Creating one rule per level without enforcing hierarchy (higher clearance can't access lower levels).

```php
// DON'T: No hierarchy - top-secret can't access confidential!
$policy = new Policy([
    new PolicyRule('clearance:top-secret', 'classification:top-secret', 'read', Effect::Allow),
    new PolicyRule('clearance:secret', 'classification:secret', 'read', Effect::Allow),
    new PolicyRule('clearance:confidential', 'classification:confidential', 'read', Effect::Allow),
]);

// Top Secret user trying to read Confidential document - DENIED (wrong!)
$subject = new Subject('user-1', ['clearance' => 'top-secret']);
$resource = new Resource('doc-1', 'document', ['role' => 'confidential']);
$result = $evaluator->evaluate($policy, $subject, $resource, new Action('read'));
// => Effect::Deny (no matching rule!)
```

**Why it's wrong**: Clearance systems should be hierarchical - higher clearance should access lower levels. One rule per exact level breaks this.

**✅ DO THIS:**
```php
// Correct: Create rules for ALL accessible levels
$policy = new Policy([
    // Top Secret can access ALL levels
    new PolicyRule('clearance:top-secret', 'classification:top-secret', 'read', Effect::Allow),
    new PolicyRule('clearance:top-secret', 'classification:secret', 'read', Effect::Allow),
    new PolicyRule('clearance:top-secret', 'classification:confidential', 'read', Effect::Allow),
    new PolicyRule('clearance:top-secret', 'classification:internal', 'read', Effect::Allow),
    new PolicyRule('clearance:top-secret', 'classification:public', 'read', Effect::Allow),

    // Secret can access secret and below
    new PolicyRule('clearance:secret', 'classification:secret', 'read', Effect::Allow),
    new PolicyRule('clearance:secret', 'classification:confidential', 'read', Effect::Allow),
    new PolicyRule('clearance:secret', 'classification:internal', 'read', Effect::Allow),
    new PolicyRule('clearance:secret', 'classification:public', 'read', Effect::Allow),

    // ... etc for each level
]);

// Or use ClearanceService helper (see examples below)
```

---

### ❌ Mistake 3: Confusing Subject Pattern with Subject Attributes

**Problem**: Using subject attributes in policy rules instead of formatted patterns.

```php
// DON'T: Using raw attribute values in rules
$policy = new Policy([
    new PolicyRule(
        subject: 'manager',  // ❌ Wrong! Should be 'role:manager'
        resource: 'confidential',  // ❌ Wrong! Should be 'classification:confidential'
        action: 'read',
        effect: Effect::Allow
    ),
]);

// Subject has 'roles' => ['manager'] attribute
$subject = new Subject('user-1', ['roles' => ['manager']]);

// Rule won't match because 'manager' ≠ 'role:manager' pattern
```

**Why it's wrong**: The `RbacRuleMatcher` expects prefixed patterns like `role:*`, `clearance:*`, `classification:*`. Raw values don't match.

**✅ DO THIS:**
```php
// Correct: Use proper pattern prefixes
$policy = new Policy([
    new PolicyRule(
        subject: 'role:manager',  // ✓ Pattern matches subject.roles attribute
        resource: 'classification:confidential',  // ✓ Pattern matches resource.role
        action: 'read',
        effect: Effect::Allow
    ),
]);

// RbacRuleMatcher checks if 'manager' in subject.roles array
// and if resource.role === 'confidential'
```

---

### ❌ Mistake 4: Not Clearing Cache After Role Changes

**Problem**: User upgraded/downgraded but still has old permissions cached.

```php
// DON'T: Changing user clearance without cache invalidation
public function promote(User $user)
{
    $user->update(['clearance_level' => 'top-secret']);  // ❌ Cache not cleared

    // User tries to access top-secret document
    // Still gets DENIED because old 'secret' clearance is cached!
}
```

**Why it's wrong**: Patrol caches authorization results per subject. When subject attributes change (roles, clearance), the cache holds stale data.

**✅ DO THIS:**
```php
// Correct: Clear cache after attribute changes
public function promote(User $user)
{
    $user->update(['clearance_level' => 'top-secret']);

    // Clear Patrol cache for this user
    Cache::tags(['patrol', "user:{$user->id}"])->flush();  // ✓

    // Or clear all patrol cache (less efficient)
    Cache::tags(['patrol'])->flush();
}

// Also clear when changing document classification
public function reclassify(Document $document, string $newClassification)
{
    $document->update(['classification' => $newClassification]);

    // Clear cache for this resource
    Cache::tags(['patrol', "document:{$document->id}"])->flush();
}
```

## Best Practices

1. **Clear Hierarchies**: Document role hierarchies for both users and resources
2. **Consistent Naming**: Use consistent role names across user and resource roles (e.g., `role:*`, `classification:*`)
3. **Enforce Hierarchies**: Create rules for all accessible levels when using clearance systems
4. **Cache Invalidation**: Always clear Patrol cache when roles or classifications change
5. **Audit Trail**: Log access to sensitive resource roles
6. **Regular Reviews**: Periodically audit user clearances and resource classifications
7. **Least Privilege**: Default to most restrictive resource role
8. **Classification Guidelines**: Provide clear guidelines for classifying resources
9. **Automatic Downgrade**: Consider automatic declassification over time
10. **Access Reports**: Generate reports showing who can access what classifications

## Debugging Resource Role Authorization

### Problem 1: "User should have access but gets denied"

**Symptom**: User with sufficient clearance gets denied on lower-classified documents.

**Diagnosis Steps**:

```php
// Step 1: Check subject attributes
$subject = Patrol::currentSubject();
dd([
    'subject_id' => $subject->id(),
    'attributes' => $subject->attributes(),
]);

// Output:
// [
//   'subject_id' => 'user-1',
//   'attributes' => [
//     'roles' => ['manager'],
//     'clearance' => 'secret',  ← Check this exists
//   ]
// ]

// Step 2: Check resource attributes
$resource = Patrol::resolveResource($document);
dd([
    'resource_id' => $resource->id(),
    'resource_type' => $resource->type(),
    'attributes' => $resource->attributes(),
]);

// Output:
// [
//   'resource_id' => 'doc-123',
//   'resource_type' => 'document',
//   'attributes' => [
//     'role' => 'confidential',  ← Check this exists
//     'owner_id' => 5,
//   ]
// ]

// Step 3: Check matching policy rules
$policy = app(PolicyRepositoryInterface::class)->find();
$matchingRules = collect($policy->rules())->filter(function ($rule) {
    return str_contains($rule->subject, 'clearance:secret')
        && str_contains($rule->resource, 'classification:confidential');
});

dd($matchingRules->count());  // Should be > 0
```

**Common Causes**:
- Resource missing `role` attribute in metadata
- Policy missing rule for this clearance + classification combo
- Subject missing clearance attribute
- Wrong pattern prefix (`clearance:` vs `role:`)

**Fix**:
```php
// Add missing policy rule for this combination
new PolicyRule(
    subject: 'clearance:secret',
    resource: 'classification:confidential',
    action: 'read',
    effect: Effect::Allow
),
```

---

### Problem 2: "Higher clearance can't access lower classification"

**Symptom**: Top Secret user denied on Confidential documents.

**Diagnosis Steps**:

```php
// Step 1: List all rules for this subject clearance
$policy = app(PolicyRepositoryInterface::class)->find();
$rulesForTopSecret = collect($policy->rules())
    ->filter(fn($rule) => str_contains($rule->subject, 'clearance:top-secret'));

foreach ($rulesForTopSecret as $rule) {
    dump("Subject: {$rule->subject} | Resource: {$rule->resource} | Action: {$rule->action}");
}

// Expected output should include:
// Subject: clearance:top-secret | Resource: classification:top-secret | ...
// Subject: clearance:top-secret | Resource: classification:secret | ...
// Subject: clearance:top-secret | Resource: classification:confidential | ...
// Subject: clearance:top-secret | Resource: classification:internal | ...
// Subject: clearance:top-secret | Resource: classification:public | ...

// If you only see ONE rule (top-secret only), hierarchy is broken!
```

**Common Causes**:
- Only exact-match rules created (no hierarchy)
- Missing `ClearanceService` helper usage
- Rules not generated for all accessible levels

**Fix**:
```php
// Use ClearanceService to generate hierarchical rules automatically
$clearanceService = new ClearanceService();
$policy = (new HierarchicalClearanceRepository($clearanceService))->find();

// Or manually add all rules
foreach (['confidential', 'internal', 'public'] as $classification) {
    $rules[] = new PolicyRule(
        subject: 'clearance:top-secret',
        resource: "classification:{$classification}",
        action: 'read',
        effect: Effect::Allow
    );
}
```

---

### Problem 3: "Cache showing stale permissions after role change"

**Symptom**: User promoted to higher clearance but still can't access resources.

**Diagnosis Steps**:

```php
// Step 1: Check current database clearance
$user = User::find($userId);
dd($user->clearance_level);  // e.g., 'top-secret'

// Step 2: Check cached subject attributes
$cacheKey = "patrol:subject:{$userId}";
$cachedSubject = Cache::get($cacheKey);
dd($cachedSubject?->attributes());

// If cached clearance differs from DB clearance, cache is stale!
// Output:
// ['clearance' => 'secret']  ← Stale! Should be 'top-secret'
```

**Common Causes**:
- Cache not cleared after user promotion/demotion
- Cache not cleared after document reclassification
- Long cache TTL (default 1 hour)

**Fix**:
```php
// Clear cache after ANY clearance or classification change
Cache::tags(['patrol', "user:{$user->id}"])->flush();
Cache::tags(['patrol', "document:{$document->id}"])->flush();

// Or force re-evaluation without cache
$result = Patrol::withoutCache()->check($document, 'read');
```

## When to Use RBAC with Resource Roles

✅ **Good for:**
- Security clearance systems
- Document classification systems
- Multi-level content tiers
- Healthcare/medical records
- Financial data with sensitivity levels
- Government/military applications
- Subscription-based content platforms

❌ **Avoid for:**
- Simple permission needs (use basic RBAC or ACL)
- Systems without resource classification
- Highly dynamic permissions (use ABAC)
- Small-scale applications

## Subscription Tier Example

```php
class SubscriptionController extends Controller
{
    public function upgrade(User $user, string $tier)
    {
        $user->update(['subscription_tier' => $tier]);

        // Clear policy cache to reflect new permissions
        Cache::tags(['patrol', "user:{$user->id}"])->flush();

        return redirect()->back()->with('success', "Upgraded to {$tier} tier");
    }
}

// In your resource resolver
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof Article) {
        $tier = match($resource->is_premium) {
            true => 'premium',
            false => $resource->is_paid ? 'basic' : 'free',
        };

        return new Resource($resource->id, 'article', ['role' => $tier]);
    }

    return new Resource($resource->id, get_class($resource));
});
```

## Related Models

- [RBAC](./rbac.md) - Basic role-based access
- [ABAC](./abac.md) - Attribute-based alternative
- [RBAC with Domains](./rbac-domains.md) - Multi-tenant roles
- [Priority-Based](./priority-based.md) - Override clearance in emergencies
