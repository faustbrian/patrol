# RESTful Authorization

HTTP method and path-based authorization for API endpoints.

## Overview

RESTful authorization maps HTTP methods (GET, POST, PUT, DELETE) and URL paths to permissions. This model is specifically designed for REST APIs where authorization decisions are based on the HTTP verb and resource path rather than abstract actions.

## Basic Concept

```
HTTP Method + URL Path = Permission
(subject, method, path) → allow/deny
```

## How RESTful Authorization Works

```
Step 1: HTTP Request arrives
┌────────────────────────────────────┐
│ GET /api/users/123                 │
│ Authorization: Bearer token123     │
└────────────────────────────────────┘

Step 2: Extract method, path, and subject
┌──────────┐  ┌─────────────────┐  ┌──────────┐
│ Method   │  │ Path            │  │ Subject  │
│ GET      │  │ /api/users/123  │  │ user-1   │
└──────────┘  └─────────────────┘  └──────────┘

Step 3: Match against policy rules
┌─────────────────────────────────────────────────┐
│ Does rule match?                                │
│ ✅ subject: 'user-1'                            │
│ ✅ resource: '/api/users/*' matches /users/123  │
│ ✅ action: 'GET'                                │
└─────────────────────────────────────────────────┘

Step 4: Return decision
    ┌───────┐
    │ ALLOW │ → 200 OK (process request)
    └───────┘

    ┌───────┐
    │ DENY  │ → 403 Forbidden
    └───────┘
```

## Use Cases

- REST API authorization
- HTTP endpoint access control
- API gateway policies
- Microservice authorization
- Public API access control
- Webhook authorization
- GraphQL endpoint protection (via HTTP)

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, RestfulRuleMatcher, EffectResolver};

$policy = new Policy([
    // Anyone can GET public endpoints
    new PolicyRule(
        subject: '*',
        resource: '/api/public/*',
        action: 'GET',
        effect: Effect::Allow
    ),

    // Authenticated users can GET protected endpoints
    new PolicyRule(
        subject: 'authenticated',
        resource: '/api/users/*',
        action: 'GET',
        effect: Effect::Allow
    ),

    // Only admins can POST/PUT/DELETE
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'POST',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'PUT',
        effect: Effect::Allow
    ),
    new PolicyRule(
        subject: 'role:admin',
        resource: '/api/users/*',
        action: 'DELETE',
        effect: Effect::Allow
    ),

    // Users can modify their own resources
    new PolicyRule(
        subject: 'user-1',
        resource: '/api/users/1',
        action: 'PUT',
        effect: Effect::Allow
    ),
]);

$evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

// Check GET request
$result = $evaluator->evaluate(
    $policy,
    new Subject('user-1'),
    new Resource('/api/users/1', 'endpoint'),
    new Action('GET')
);
// => Effect::Allow
```

## Patterns

### Public vs Protected Endpoints

```php
// Public read-only endpoints
new PolicyRule('*', '/api/articles', 'GET', Effect::Allow);
new PolicyRule('*', '/api/articles/*', 'GET', Effect::Allow);
new PolicyRule('*', '/api/categories', 'GET', Effect::Allow);

// Protected write endpoints
new PolicyRule('authenticated', '/api/articles', 'POST', Effect::Allow);
new PolicyRule('authenticated', '/api/articles/*', 'PUT', Effect::Allow);

// Admin-only endpoints
new PolicyRule('role:admin', '/api/admin/*', '*', Effect::Allow);
```

### CRUD Permissions per Resource

```php
// Read permissions
new PolicyRule('role:viewer', '/api/projects', 'GET', Effect::Allow);
new PolicyRule('role:viewer', '/api/projects/*', 'GET', Effect::Allow);

// Create permissions
new PolicyRule('role:editor', '/api/projects', 'POST', Effect::Allow);

// Update permissions
new PolicyRule('role:editor', '/api/projects/*', 'PUT', Effect::Allow);
new PolicyRule('role:editor', '/api/projects/*', 'PATCH', Effect::Allow);

// Delete permissions
new PolicyRule('role:admin', '/api/projects/*', 'DELETE', Effect::Allow);
```

### Nested Resource Paths

```php
// Organization resources
new PolicyRule('role:member', '/api/orgs/*/projects', 'GET', Effect::Allow);
new PolicyRule('role:admin', '/api/orgs/*/projects', 'POST', Effect::Allow);

// Deeply nested
new PolicyRule('role:member', '/api/orgs/*/projects/*/tasks', 'GET', Effect::Allow);
new PolicyRule('role:member', '/api/orgs/*/projects/*/tasks/*', 'GET', Effect::Allow);
```

## Laravel Integration

### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    $user = auth()->user();

    if (!$user) {
        return new Subject('guest', ['authenticated' => false]);
    }

    return new Subject($user->id, [
        'authenticated' => true,
        'roles' => $user->roles->pluck('name')->all(),
        'api_token' => $user->api_token,
    ]);
});
```

### Resource Resolver for HTTP Requests

```php
Patrol::resolveResource(function ($resource) {
    // For HTTP requests, use the path as resource
    if ($resource instanceof \Illuminate\Http\Request) {
        return new Resource(
            $resource->path(),
            'endpoint',
            [
                'method' => $resource->method(),
                'ip' => $resource->ip(),
                'authenticated' => auth()->check(),
            ]
        );
    }

    return new Resource($resource->id ?? $resource, get_class($resource));
});
```

### Middleware

```php
// Automatic REST authorization
class RestfulPatrolMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/' . $request->path();
        $method = $request->method();

        $resource = new Resource($path, 'endpoint');
        $action = new Action($method);

        if (!Patrol::check($resource, $action)) {
            abort(403, 'API access denied');
        }

        return $next($request);
    }
}

// Apply to API routes
Route::middleware(['api', 'restful-patrol'])->prefix('api')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::post('/articles', [ArticleController::class, 'store']);
});
```

### Route-Based Authorization

```php
// routes/api.php

// Public endpoints
Route::get('/api/public/health', function () {
    return ['status' => 'ok'];
});

// Authenticated endpoints
Route::middleware(['patrol-rest'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
    Route::get('/api/users/{id}', [UserController::class, 'show']);
});

// Admin endpoints
Route::middleware(['patrol-rest'])->prefix('api/admin')->group(function () {
    Route::post('/users', [UserController::class, 'store']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
});
```

## Real-World Example: REST API

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // ============= Public Endpoints =============
    // Health check
    new PolicyRule('*', '/api/health', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/status', 'GET', Effect::Allow),

    // Public documentation
    new PolicyRule('*', '/api/docs', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/docs/*', 'GET', Effect::Allow),

    // Public articles (read-only)
    new PolicyRule('*', '/api/articles', 'GET', Effect::Allow),
    new PolicyRule('*', '/api/articles/*', 'GET', Effect::Allow),

    // ============= Authenticated User Endpoints =============
    // User profile
    new PolicyRule('authenticated', '/api/user', 'GET', Effect::Allow),
    new PolicyRule('authenticated', '/api/user', 'PUT', Effect::Allow),

    // User's own resources
    new PolicyRule('authenticated', '/api/user/articles', 'GET', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles', 'POST', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles/*', 'PUT', Effect::Allow),
    new PolicyRule('authenticated', '/api/user/articles/*', 'DELETE', Effect::Allow),

    // ============= Editor Endpoints =============
    new PolicyRule('role:editor', '/api/articles', 'POST', Effect::Allow),
    new PolicyRule('role:editor', '/api/articles/*', 'PUT', Effect::Allow),

    // ============= Admin Endpoints =============
    // User management
    new PolicyRule('role:admin', '/api/admin/users', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/users', 'POST', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/users/*', '*', Effect::Allow),

    // System settings
    new PolicyRule('role:admin', '/api/admin/settings', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/admin/settings', 'PUT', Effect::Allow),

    // Analytics
    new PolicyRule('role:admin', '/api/admin/analytics/*', 'GET', Effect::Allow),

    // ============= Deny Overrides =============
    // Block dangerous operations in production
    new PolicyRule('*', '/api/admin/system/reset', 'POST', Effect::Deny),
]);
```

## Database Storage

```php
// Migration
Schema::create('api_permissions', function (Blueprint $table) {
    $table->id();
    $table->string('subject'); // '*', 'authenticated', 'role:admin', 'user-123'
    $table->string('path_pattern'); // '/api/articles/*'
    $table->string('http_method'); // GET, POST, PUT, DELETE, *
    $table->enum('effect', ['allow', 'deny'])->default('allow');
    $table->integer('priority')->default(0);
    $table->timestamps();

    $table->index(['path_pattern', 'http_method']);
});

// Repository Implementation
use Patrol\Core\Contracts\PolicyRepositoryInterface;

class RestfulRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('api_permissions')
            ->orderBy('priority', 'desc')
            ->get()
            ->map(fn($row) => new PolicyRule(
                subject: $row->subject,
                resource: $row->path_pattern,
                action: $row->http_method,
                effect: Effect::from($row->effect),
            ))
            ->all();

        return new Policy($rules);
    }
}
```

## API Controller Example

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleApiController extends Controller
{
    public function index(Request $request)
    {
        // Check if user can GET /api/articles
        $resource = new Resource('/api/articles', 'endpoint');

        if (!Patrol::check($resource, new Action('GET'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $articles = Article::paginate();
        return response()->json($articles);
    }

    public function store(Request $request)
    {
        // Check if user can POST /api/articles
        $resource = new Resource('/api/articles', 'endpoint');

        if (!Patrol::check($resource, new Action('POST'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $article = Article::create($request->validated());
        return response()->json($article, 201);
    }

    public function update(Request $request, Article $article)
    {
        // Check if user can PUT /api/articles/{id}
        $path = "/api/articles/{$article->id}";
        $resource = new Resource($path, 'endpoint');

        if (!Patrol::check($resource, new Action('PUT'))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $article->update($request->validated());
        return response()->json($article);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows public GET requests to public endpoints', function () {
    $policy = new Policy([
        new PolicyRule('*', '/api/public/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('guest'),
        new Resource('/api/public/articles', 'endpoint'),
        new Action('GET')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies POST requests to public endpoints', function () {
    $policy = new Policy([
        new PolicyRule('*', '/api/public/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('guest'),
        new Resource('/api/public/articles', 'endpoint'),
        new Action('POST')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports wildcard path matching', function () {
    $policy = new Policy([
        new PolicyRule('authenticated', '/api/users/*', 'GET', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new RestfulRuleMatcher(), new EffectResolver());

    $subject = new Subject('user-1', ['authenticated' => true]);

    $result = $evaluator->evaluate(
        $policy,
        $subject,
        new Resource('/api/users/123', 'endpoint'),
        new Action('GET')
    );

    expect($result)->toBe(Effect::Allow);
});
```

## Rate Limiting Integration

```php
use Illuminate\Support\Facades\RateLimiter;

class RateLimitedRestfulMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/' . $request->path();
        $method = $request->method();

        // Check authorization first
        $resource = new Resource($path, 'endpoint');
        $action = new Action($method);

        if (!Patrol::check($resource, $action)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Apply rate limiting based on endpoint
        $key = $this->getRateLimitKey($request, $path);
        $limit = $this->getRateLimitForPath($path);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['error' => 'Too Many Requests'], 429);
        }

        RateLimiter::hit($key);

        return $next($request);
    }

    private function getRateLimitForPath(string $path): int
    {
        return match (true) {
            str_starts_with($path, '/api/admin/') => 100,
            str_starts_with($path, '/api/user/') => 300,
            str_starts_with($path, '/api/public/') => 1000,
            default => 500,
        };
    }
}
```

## API Key Authorization

```php
// Add API key support
Patrol::resolveSubject(function () {
    // Check for API key
    $apiKey = request()->header('X-API-Key');

    if ($apiKey) {
        $apiToken = ApiToken::where('token', $apiKey)->first();

        if ($apiToken) {
            return new Subject("api-token:{$apiToken->id}", [
                'type' => 'api_token',
                'scopes' => $apiToken->scopes,
                'owner_id' => $apiToken->user_id,
            ]);
        }
    }

    // Fall back to session auth
    $user = auth()->user();

    return $user
        ? new Subject($user->id, ['type' => 'user'])
        : new Subject('guest', ['type' => 'guest']);
});

// Scope-based permissions
$policy = new Policy([
    // API token with 'read' scope
    new PolicyRule('scope:read', '/api/*', 'GET', Effect::Allow),

    // API token with 'write' scope
    new PolicyRule('scope:write', '/api/*', 'POST', Effect::Allow),
    new PolicyRule('scope:write', '/api/*', 'PUT', Effect::Allow),

    // API token with 'delete' scope
    new PolicyRule('scope:delete', '/api/*', 'DELETE', Effect::Allow),
]);
```

## Common Mistakes

### ❌ Mistake 1: Path Mismatch

```php
// DON'T: Rule path doesn't match actual route
new PolicyRule('user-1', '/api/user/*', 'GET', Effect::Allow); // ❌ /user

// Route is actually: /api/users/123
Route::get('/api/users/{id}', ...); // ❌ /users (plural)
```

**✅ DO THIS:**
```php
// Match exact route paths
new PolicyRule('user-1', '/api/users/*', 'GET', Effect::Allow); // ✅ /users
```

---

### ❌ Mistake 2: Wrong HTTP Method

```php
// DON'T: Allow POST when should be PUT
new PolicyRule('user-1', '/api/users/1', 'POST', Effect::Allow); // ❌ POST

// Request uses PUT for updates
fetch('/api/users/1', { method: 'PUT' }); // ❌ Denied!
```

**✅ DO THIS:**
```php
// Use correct HTTP method
new PolicyRule('user-1', '/api/users/*', 'PUT', Effect::Allow); // ✅ PUT for updates
new PolicyRule('user-1', '/api/users', 'POST', Effect::Allow);   // ✅ POST for creates
```

---

### ❌ Mistake 3: Too Permissive Wildcards

```php
// DON'T: Allow all methods
new PolicyRule('user-1', '/api/*', '*', Effect::Allow); // ❌ Everything!
```

**✅ DO THIS:**
```php
// Be specific about methods
new PolicyRule('user-1', '/api/users/*', 'GET', Effect::Allow);
new PolicyRule('user-1', '/api/posts/*', 'GET', Effect::Allow);
// Explicitly grant each method needed
```

---

### ❌ Mistake 4: Not Using RESTful Matcher

```php
// DON'T: Use wrong matcher
$evaluator = new PolicyEvaluator(
    new AclRuleMatcher(), // ❌ Won't match paths correctly
    new EffectResolver()
);
```

**✅ DO THIS:**
```php
// Use RestfulRuleMatcher for API authorization
$evaluator = new PolicyEvaluator(
    new RestfulRuleMatcher(), // ✅ Handles path matching
    new EffectResolver()
);
```

---

## Best Practices

1. **Path Consistency**: Use consistent URL patterns (/api/resources, not /api/resource)
2. **Method Semantics**: Follow REST (GET=read, POST=create, PUT=update, DELETE=delete)
3. **Wildcard Strategy**: Use wildcards judiciously, be specific when possible
4. **Version APIs**: Include version in path (/api/v1/*, /api/v2/*)
5. **Rate Limiting**: Combine with rate limiting for API protection
6. **Audit Logging**: Log all API access attempts with method + path
7. **Clear Errors**: Return informative 403 with allowed methods
8. **Documentation**: Document API permissions in OpenAPI/Swagger
9. **Path Parameters**: Handle path params correctly (/users/:id → /users/*)
10. **Query Strings**: Ignore query strings in path matching

## When to Use RESTful Authorization

✅ **Good for:**
- REST API authorization
- Public API access control
- Microservice authorization
- HTTP-based services
- API gateways
- Webhook authorization

❌ **Avoid for:**
- Non-HTTP authorization (use ACL/RBAC)
- Complex business logic (use ABAC)
- Fine-grained resource permissions (combine with ACL)
- Non-REST APIs (use appropriate model)

## Versioned API Permissions

```php
// Different permissions per API version
$policy = new Policy([
    // v1 API - more restrictive
    new PolicyRule('role:user', '/api/v1/articles', 'GET', Effect::Allow),
    new PolicyRule('role:admin', '/api/v1/articles', 'POST', Effect::Allow),

    // v2 API - more permissive
    new PolicyRule('role:user', '/api/v2/articles', 'GET', Effect::Allow),
    new PolicyRule('role:user', '/api/v2/articles', 'POST', Effect::Allow),
    new PolicyRule('role:admin', '/api/v2/articles', '*', Effect::Allow),

    // Deprecated v1 admin endpoint
    new PolicyRule('*', '/api/v1/admin/legacy', '*', Effect::Deny),
]);
```

## Debugging API Authorization

### Problem: API returns 403 but should allow

**Step 1: Check the exact path and method**
```php
// Log the actual request
Log::info('API Request', [
    'method' => request()->method(),
    'path' => request()->path(),
    'subject' => Patrol::getCurrentSubject()->id(),
]);

// Compare with rule
new PolicyRule('user-1', '/api/users/*', 'GET', Effect::Allow);
//                        ^^^^^^^^^^^   ^^^
//                        Must match exactly!
```

**Step 2: Verify path pattern matching**
```php
// Request: GET /api/users/123
// Rule: '/api/users/*'

// Does '/api/users/123' match pattern '/api/users/*'?
// Yes! ✅

// Request: GET /api/v1/users/123
// Rule: '/api/users/*'
// No! ❌ Missing /v1/
```

**Step 3: Check HTTP method case**
```php
// Methods are case-sensitive
request()->method(); // "GET"
new PolicyRule('user-1', '/api/users/*', 'get', Effect::Allow); // ❌ lowercase

// Fix: Use uppercase
new PolicyRule('user-1', '/api/users/*', 'GET', Effect::Allow); // ✅
```

---

### Problem: Wildcards not matching

**Common issue: Query strings**
```php
// Request: GET /api/users?page=2
// Path extracted: /api/users?page=2 ❌
// Rule: '/api/users'
// No match!

// Fix: Strip query strings before matching
$path = parse_url(request()->path(), PHP_URL_PATH);
// => /api/users ✅
```

---

### Problem: API allows when it shouldn't

**Check for overly permissive rules:**
```php
// This allows EVERYTHING:
new PolicyRule('*', '/api/*', '*', Effect::Allow); // ❌ Too broad!

// Be specific:
new PolicyRule('authenticated', '/api/users/*', 'GET', Effect::Allow);
new PolicyRule('role:admin', '/api/users/*', 'POST', Effect::Allow);
```

---

## Related Models

- [ACL](./acl.md) - Resource-specific permissions (combine for fine-grained API auth)
- [RBAC](./rbac.md) - Role-based API access (API keys with roles)
- [ABAC](./abac.md) - Attribute-based API rules (rate limits, quotas)
- [Deny-Override](./deny-override.md) - Block dangerous endpoints
