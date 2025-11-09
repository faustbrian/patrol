# Batch Authorization - Eliminate N+1 Query Problems

Evaluate authorization for multiple resources in a single operation, dramatically improving performance for list filtering, pagination, and bulk operations.

## Problem: N+1 Authorization Queries

When authorizing multiple resources (e.g., filtering a list of documents), naive implementations check each resource individually:

```php
// ❌ BAD: N+1 query problem
$documents = Document::all();
$authorizedDocs = [];

foreach ($documents as $doc) {
    if (Patrol::check($doc, 'read') === Effect::Allow) {
        $authorizedDocs[] = $doc;
    }
}
// Results in N database queries (one per document)
```

**Performance impact:**
- 100 resources = 100 authorization queries
- Typical latency: ~3ms per query × 100 = 300ms
- Database connection overhead multiplies the problem

---

## Solution: Batch Policy Evaluator

Load all policies in a single optimized query, then evaluate in memory:

```php
use Patrol\Core\Engine\BatchPolicyEvaluator;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;

// Create batch evaluator
$batchEvaluator = new BatchPolicyEvaluator($repository, $policyEvaluator);

// Prepare resources for batch evaluation
$resources = $documents->map(fn($doc) =>
    new Resource("document:{$doc->id}", 'Document')
)->toArray();

// Single batch operation replaces N queries
$effects = $batchEvaluator->evaluateBatch(
    $subject,
    $resources,
    new Action('read')
);

// Filter authorized resources
$authorizedDocs = $documents->filter(fn($doc) =>
    $effects["document:{$doc->id}"] === Effect::Allow
);
```

**Performance improvement:**
- 100 resources = 1 database query
- Typical latency: ~50ms total (1 query + memory evaluation)
- **6x faster** than naive approach

---

## Core Concepts

### How Batch Evaluation Works

1. **Single Database Query**: Load all policies for all resources using optimized batch query with `WHERE IN` clauses
2. **In-Memory Evaluation**: Evaluate each resource's policy sequentially without additional I/O
3. **Consistent Results**: Same evaluation logic as single-resource authorization
4. **Default Deny**: Resources without policies automatically return `Deny`

### Performance Characteristics

| Metric | Single Resource | Batch (100 resources) | Improvement |
|--------|----------------|----------------------|-------------|
| Database queries | N | 1 | N× reduction |
| Network round-trips | N | 1 | N× reduction |
| Total latency (typical) | 3ms × N | 50ms | 6× faster |
| Memory usage | O(1) | O(N) | Linear growth |
| Evaluation complexity | O(M) | O(N × M) | Same per resource |

**Where:**
- N = number of resources
- M = average rules per policy

---

## Implementation Examples

### 1. API List Filtering

Filter paginated API responses to show only authorized resources:

```php
use Patrol\Laravel\Facades\Patrol as PatrolFacade;
use Patrol\Core\Engine\BatchPolicyEvaluator;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;

class DocumentController
{
    public function index(Request $request)
    {
        // Fetch ALL documents for the page
        $documents = Document::query()
            ->where('published', true)
            ->limit(100)
            ->get();

        // Batch authorize all documents
        $subject = PatrolFacade::resolveSubject();
        $resources = $documents->map(fn($doc) =>
            new Resource("document:{$doc->id}", 'Document')
        )->toArray();

        $batchEvaluator = app(BatchPolicyEvaluator::class);
        $effects = $batchEvaluator->evaluateBatch(
            $subject,
            $resources,
            new Action('read')
        );

        // Filter to authorized only
        $authorized = $documents->filter(fn($doc) =>
            $effects["document:{$doc->id}"] === Effect::Allow
        );

        return DocumentResource::collection($authorized);
    }
}
```

### 2. Bulk Operation Preflight

Check permissions before executing bulk operations:

```php
public function bulkDelete(Request $request)
{
    $documentIds = $request->input('document_ids');
    $documents = Document::find($documentIds);

    // Batch authorization check
    $subject = PatrolFacade::resolveSubject();
    $resources = $documents->map(fn($doc) =>
        new Resource("document:{$doc->id}", 'Document')
    )->toArray();

    $batchEvaluator = app(BatchPolicyEvaluator::class);
    $effects = $batchEvaluator->evaluateBatch(
        $subject,
        $resources,
        new Action('delete')
    );

    // Separate authorized from unauthorized
    $authorized = [];
    $denied = [];

    foreach ($documents as $doc) {
        $resourceId = "document:{$doc->id}";
        if ($effects[$resourceId] === Effect::Allow) {
            $authorized[] = $doc;
        } else {
            $denied[] = $doc->id;
        }
    }

    // Delete only authorized documents
    Document::whereIn('id', collect($authorized)->pluck('id'))->delete();

    return response()->json([
        'deleted' => count($authorized),
        'denied' => $denied,
    ]);
}
```

### 3. Admin Interface Permissions Grid

Show permission matrix for multiple users and resources:

```php
public function permissionsMatrix()
{
    $users = User::limit(50)->get();
    $documents = Document::limit(20)->get();
    $actions = ['read', 'write', 'delete'];

    $batchEvaluator = app(BatchPolicyEvaluator::class);
    $matrix = [];

    foreach ($users as $user) {
        $subject = new Subject("user:{$user->id}", [
            'roles' => $user->roles->pluck('name')->map(fn($r) => "role:{$r}")->toArray()
        ]);

        $resources = $documents->map(fn($doc) =>
            new Resource("document:{$doc->id}", 'Document')
        )->toArray();

        foreach ($actions as $action) {
            $effects = $batchEvaluator->evaluateBatch(
                $subject,
                $resources,
                new Action($action)
            );

            $matrix[$user->id][$action] = $effects;
        }
    }

    return view('admin.permissions-matrix', [
        'matrix' => $matrix,
        'users' => $users,
        'documents' => $documents,
    ]);
}
```

### 4. Nested Resource Hierarchies

Authorize folder contents with inherited permissions:

```php
public function folderContents(Folder $folder)
{
    // Get all documents in folder
    $documents = $folder->documents;

    // Create resources with folder hierarchy for inheritance
    $resources = $documents->map(fn($doc) =>
        new Resource("folder:{$folder->id}/document:{$doc->id}", 'Document')
    )->toArray();

    $subject = PatrolFacade::resolveSubject();
    $batchEvaluator = app(BatchPolicyEvaluator::class);

    $effects = $batchEvaluator->evaluateBatch(
        $subject,
        $resources,
        new Action('read')
    );

    // Filter authorized documents
    $authorized = $documents->filter(fn($doc) => {
        $resourceId = "folder:{$folder->id}/document:{$doc->id}";
        return $effects[$resourceId] === Effect::Allow;
    });

    return view('folder.contents', [
        'folder' => $folder,
        'documents' => $authorized,
    ]);
}
```

---

## Repository Implementation

To support batch authorization, implement `getPoliciesForBatch()` in your repository:

### Database Repository Example

```php
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Subject;

class DatabasePolicyRepository implements PolicyRepositoryInterface
{
    public function getPoliciesForBatch(Subject $subject, array $resources): array
    {
        // Extract resource IDs for WHERE IN clause
        $resourceIds = array_map(fn($r) => $r->id, $resources);

        // Single query to load all policies
        $policyRules = DB::table('patrol_policies')
            ->where(function ($query) use ($subject) {
                // Match subject or wildcard
                $query->where('subject', $subject->id)
                      ->orWhere('subject', '*');
            })
            ->where(function ($query) use ($resourceIds) {
                // Match any resource ID or wildcard
                $query->whereIn('resource', $resourceIds)
                      ->orWhere('resource', '*')
                      ->orWhere('resource', 'LIKE', '%*%'); // Pattern matching
            })
            ->get();

        // Group rules by resource ID
        $policiesByResource = [];

        foreach ($resources as $resource) {
            // Filter rules matching this specific resource
            $matchingRules = $policyRules->filter(function ($rule) use ($resource, $subject) {
                return $this->ruleMatches($rule, $subject, $resource);
            });

            // Convert to PolicyRule objects
            $rules = $matchingRules->map(fn($r) => $this->toPolicyRule($r))->toArray();

            $policiesByResource[$resource->id] = new Policy($rules);
        }

        return $policiesByResource;
    }

    private function ruleMatches($rule, Subject $subject, Resource $resource): bool
    {
        // Subject matching
        if ($rule->subject !== '*' && $rule->subject !== $subject->id) {
            return false;
        }

        // Resource matching (including wildcards)
        if ($rule->resource === '*') {
            return true;
        }

        if ($rule->resource === $resource->id) {
            return true;
        }

        // Pattern matching for wildcards
        if (str_contains($rule->resource, '*')) {
            $pattern = str_replace('*', '.*', preg_quote($rule->resource, '/'));
            return (bool) preg_match("/^{$pattern}$/", $resource->id);
        }

        return false;
    }
}
```

### File Repository Example

```php
class FilePolicyRepository implements PolicyRepositoryInterface
{
    public function getPoliciesForBatch(Subject $subject, array $resources): array
    {
        // Load all policies once
        $allPolicies = $this->loadAllPolicies();

        $policiesByResource = [];

        foreach ($resources as $resource) {
            // Filter policies matching this resource
            $matchingRules = array_filter(
                $allPolicies->rules,
                fn($rule) => $this->ruleMatches($rule, $subject, $resource)
            );

            $policiesByResource[$resource->id] = new Policy($matchingRules);
        }

        return $policiesByResource;
    }
}
```

---

## Best Practices

### 1. Chunk Large Batches

For very large resource sets, chunk them to manage memory:

```php
$resources = /* 10,000 resources */;
$chunkSize = 100;
$allEffects = [];

foreach (array_chunk($resources, $chunkSize) as $chunk) {
    $effects = $batchEvaluator->evaluateBatch($subject, $chunk, $action);
    $allEffects = array_merge($allEffects, $effects);
}
```

### 2. Cache Batch Results

For repeated batch operations, cache the results:

```php
$cacheKey = "batch_auth:{$subject->id}:{$action->value}:".md5(json_encode($resourceIds));

$effects = Cache::remember($cacheKey, 300, function () use ($batchEvaluator, $subject, $resources, $action) {
    return $batchEvaluator->evaluateBatch($subject, $resources, $action);
});
```

### 3. Eager Load Related Data

When filtering collections, eager load relationships to avoid N+1 on other queries:

```php
$documents = Document::with(['author', 'tags'])->get();

// Batch authorize
$effects = $batchEvaluator->evaluateBatch(...);

// Filter without triggering additional queries
$authorized = $documents->filter(fn($doc) =>
    $effects["document:{$doc->id}"] === Effect::Allow
);

// Relationships already loaded
foreach ($authorized as $doc) {
    echo $doc->author->name; // No query
}
```

### 4. Index Resource Fields

Ensure database indexes support efficient batch queries:

```sql
-- Index for subject matching
CREATE INDEX idx_policies_subject ON patrol_policies(subject);

-- Index for resource matching
CREATE INDEX idx_policies_resource ON patrol_policies(resource);

-- Composite index for common queries
CREATE INDEX idx_policies_subject_resource ON patrol_policies(subject, resource);
```

---

## Performance Benchmarks

Real-world performance comparison:

### Naive N+1 Approach
```
Resources: 100
Queries: 100
Latency: ~300ms
Database load: High (100 connections)
```

### Batch Authorization
```
Resources: 100
Queries: 1
Latency: ~50ms
Database load: Low (1 connection)
```

### Batch + Caching
```
Resources: 100
Queries: 0 (cache hit)
Latency: ~5ms
Database load: None
```

---

## When to Use Batch Authorization

**Use batch authorization when:**
- ✅ Filtering lists or collections
- ✅ Paginating API responses
- ✅ Bulk operations (delete, update, export)
- ✅ Permission matrices or grids
- ✅ Resource hierarchies with inheritance
- ✅ Admin interfaces showing permissions

**Don't use batch authorization when:**
- ❌ Authorizing a single resource (use standard `authorize()`)
- ❌ Real-time streaming (evaluate as resources arrive)
- ❌ Infinite scroll with dynamic loading (batch each page separately)

---

## Testing

```php
use Patrol\Core\Engine\BatchPolicyEvaluator;
use Patrol\Core\ValueObjects\Effect;

test('batch authorization filters unauthorized resources', function () {
    $repository = new FilePolicyRepository();
    $evaluator = new PolicyEvaluator(new RbacRuleMatcher());
    $batchEvaluator = new BatchPolicyEvaluator($repository, $evaluator);

    $subject = new Subject('user:123', ['roles' => ['role:editor']]);
    $resources = [
        new Resource('document:1', 'Document'),
        new Resource('document:2', 'Document'),
        new Resource('document:3', 'Document'),
    ];

    $effects = $batchEvaluator->evaluateBatch(
        $subject,
        $resources,
        new Action('read')
    );

    expect($effects)->toHaveCount(3);
    expect($effects['document:1'])->toBe(Effect::Allow);
    expect($effects['document:2'])->toBe(Effect::Allow);
    expect($effects['document:3'])->toBe(Effect::Deny); // Restricted
});
```

---

## Related Documentation

- **Performance Optimization** (coming soon) - Caching and optimization strategies
- **[Rate Limiting](./rate-limiting.md)** - Prevent authorization abuse
- **[Testing](./testing.md)** - Test authorization logic
- **[Policy Repositories](../guides/persisting-policies.md)** - Storage implementations

---

**Performance tip:** Combine batch authorization with policy caching for maximum performance. The first request pays the database query cost, subsequent requests serve from cache in <5ms.
