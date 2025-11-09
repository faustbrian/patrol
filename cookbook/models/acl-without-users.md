# ACL without Users

Authorization for systems without authentication or user accounts.

## Overview

ACL without Users is designed for applications where resources need access control but there are no user accounts. This uses wildcard subjects (`*`) to grant anonymous or public access to resources based solely on actions and resource types.

## Basic Concept

```
* (anonymous) + Resource + Action = Public Permission
```

## How Anonymous Authorization Works

```
Step 1: Incoming request (no authentication)
┌────────────────────────────────────────┐
│ Subject: * (anonymous)                 │
│   - No user ID                         │
│   - No authentication                  │
│   - IP: 192.168.1.100                  │
│                                        │
│ Resource: blog-post-1                  │
│ Action: read                           │
└────────────────────────────────────────┘
                 ▼
Step 2: Match wildcard subject rules
┌─────────────────────────────────────────────────┐
│ Rule 1: * → blog-post:* → read → ALLOW          │
│   - Subject matches: * (wildcard) ✓             │
│   - Resource matches: blog-post:* ✓             │
│   - Action matches: read ✓                      │
│   - Effect: ALLOW                               │
└─────────────────────────────────────────────────┘
                 ▼
Step 3: Grant public access
┌─────────────────────────────────┐
│ Result: ALLOW                   │
│ Reason: Public resource         │
│ User: Anonymous                 │
└─────────────────────────────────┘
```

**vs. Restricted Action:**

```
Step 1: Anonymous user tries to write
┌────────────────────────────────────────┐
│ Subject: * (anonymous)                 │
│ Resource: blog-post-1                  │
│ Action: delete (destructive)           │
└────────────────────────────────────────┘
                 ▼
Step 2: Check wildcard rules
┌──────────────────────────────────────────────────┐
│ Rule 1: * → blog-post:* → read → ALLOW           │
│   - Action NO MATCH: delete ≠ read              │
│                                                  │
│ Rule 2: * → * → delete → DENY                   │
│   - Subject matches: * ✓                        │
│   - Resource matches: * ✓                       │
│   - Action matches: delete ✓                    │
│   - Effect: DENY                                │
└──────────────────────────────────────────────────┘
                 ▼
Step 3: Deny destructive action
┌─────────────────────────────────┐
│ Result: DENY                    │
│ Reason: Anonymous can't modify  │
└─────────────────────────────────┘
```

## Use Cases

- Public APIs without authentication
- Static content websites
- Public file servers
- Read-only documentation sites
- Open data platforms
- Public voting/polling systems
- Anonymous feedback forms
- Public bulletin boards

## Core Example

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Subject, Resource, Action, Policy};
use Patrol\Core\Engine\{PolicyEvaluator, AclRuleMatcher, EffectResolver};

$policy = new Policy([
    // Anyone can read blog posts
    new PolicyRule(
        subject: '*',
        resource: 'blog-post:*',
        action: 'read',
        effect: Effect::Allow
    ),

    // Anyone can read public documents
    new PolicyRule(
        subject: '*',
        resource: 'document:public-*',
        action: 'read',
        effect: Effect::Allow
    ),

    // No one can delete (not even anonymous)
    new PolicyRule(
        subject: '*',
        resource: '*',
        action: 'delete',
        effect: Effect::Deny
    ),
]);

$evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

// Anonymous user can read blog posts
$result = $evaluator->evaluate(
    $policy,
    new Subject('*'), // or new Subject('anonymous')
    new Resource('blog-post-1', 'blog-post'),
    new Action('read')
);
// => Effect::Allow
```

## Patterns

### Read-Only Public Access

```php
// Public read access to all content
new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'read',
    effect: Effect::Allow
);

// Deny all modifications
new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'write',
    effect: Effect::Deny
);

new PolicyRule(
    subject: '*',
    resource: '*',
    action: 'delete',
    effect: Effect::Deny
);
```

### Selective Resource Access

```php
// Public articles only
new PolicyRule(
    subject: '*',
    resource: 'article:published-*',
    action: 'read',
    effect: Effect::Allow
);

// Public downloads
new PolicyRule(
    subject: '*',
    resource: 'file:public-*',
    action: 'download',
    effect: Effect::Allow
);

// Block private resources
new PolicyRule(
    subject: '*',
    resource: 'file:private-*',
    action: '*',
    effect: Effect::Deny
);
```

### Action-Based Control

```php
// Allow specific actions for anonymous users
new PolicyRule(
    subject: '*',
    resource: 'form:*',
    action: 'submit',
    effect: Effect::Allow
);

new PolicyRule(
    subject: '*',
    resource: 'poll:*',
    action: 'vote',
    effect: Effect::Allow
);
```

## Laravel Integration

### Subject Resolver

```php
use Patrol\Laravel\Patrol;
use Patrol\Core\ValueObjects\Subject;

Patrol::resolveSubject(function () {
    // Always return anonymous subject for public apps
    return new Subject('*', [
        'is_anonymous' => true,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
});
```

### Middleware

```php
// Public read-only route
Route::middleware(['patrol:article:read'])->get('/articles/{id}', function ($id) {
    return Article::findOrFail($id);
});

// Public submission
Route::middleware(['patrol:form:submit'])->post('/feedback', function (Request $request) {
    Feedback::create($request->validated());
    return response()->json(['message' => 'Thank you!']);
});
```

### Controller

```php
use Patrol\Laravel\Facades\Patrol;

class ArticleController extends Controller
{
    public function index()
    {
        // Filter to only public articles
        $articles = Article::all();
        $publicArticles = Patrol::filter($articles, 'read');

        return view('articles.index', compact('publicArticles'));
    }

    public function show(Article $article)
    {
        // Check if anonymous user can view
        if (!Patrol::check($article, 'read')) {
            abort(403, 'This article is not public');
        }

        return view('articles.show', compact('article'));
    }
}
```

## Real-World Example: Public API

```php
use Patrol\Core\ValueObjects\{PolicyRule, Effect, Policy};

$policy = new Policy([
    // Public endpoints - anyone can read
    new PolicyRule('*', 'endpoint:/api/status', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/docs', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/health', 'GET', Effect::Allow),

    // Public data endpoints
    new PolicyRule('*', 'endpoint:/api/articles', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/articles/*', 'GET', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/categories', 'GET', Effect::Allow),

    // Public submission endpoints
    new PolicyRule('*', 'endpoint:/api/feedback', 'POST', Effect::Allow),
    new PolicyRule('*', 'endpoint:/api/contact', 'POST', Effect::Allow),

    // Block all other modifications
    new PolicyRule('*', 'endpoint:*', 'POST', Effect::Deny),
    new PolicyRule('*', 'endpoint:*', 'PUT', Effect::Deny),
    new PolicyRule('*', 'endpoint:*', 'DELETE', Effect::Deny),
]);
```

## Database Storage

```php
// Migration
Schema::create('public_resources', function (Blueprint $table) {
    $table->id();
    $table->string('resource_type');
    $table->string('resource_pattern'); // e.g., 'public-*', 'published-*'
    $table->json('allowed_actions');     // ['read', 'download']
    $table->boolean('is_public')->default(true);
    $table->timestamps();
});

// Repository Implementation
class PublicResourceRepository implements PolicyRepositoryInterface
{
    public function find(): Policy
    {
        $rules = DB::table('public_resources')
            ->where('is_public', true)
            ->get()
            ->flatMap(function ($resource) {
                return collect($resource->allowed_actions)->map(
                    fn($action) => new PolicyRule(
                        subject: '*',
                        resource: "{$resource->resource_type}:{$resource->resource_pattern}",
                        action: $action,
                        effect: Effect::Allow
                    )
                );
            })
            ->all();

        return new Policy($rules);
    }
}
```

## Rate Limiting for Anonymous Access

```php
use Illuminate\Support\Facades\RateLimiter;

class RateLimitedPatrolMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Rate limit by IP for anonymous users
        $key = 'patrol:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 100)) {
            abort(429, 'Too many requests');
        }

        RateLimiter::hit($key, 60); // 60 seconds

        // Check patrol authorization
        if (!Patrol::check($resource, $action)) {
            abort(403);
        }

        return $next($request);
    }
}
```

## Testing

```php
use Pest\Tests;

it('allows anonymous users to read public resources', function () {
    $policy = new Policy([
        new PolicyRule('*', 'article:public-*', 'read', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('public-article-1', 'article'),
        new Action('read')
    );

    expect($result)->toBe(Effect::Allow);
});

it('denies anonymous users from modifying resources', function () {
    $policy = new Policy([
        new PolicyRule('*', 'article:*', 'read', Effect::Allow),
        new PolicyRule('*', 'article:*', 'write', Effect::Deny),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('article-1', 'article'),
        new Action('write')
    );

    expect($result)->toBe(Effect::Deny);
});

it('supports action-based anonymous access', function () {
    $policy = new Policy([
        new PolicyRule('*', 'form:*', 'submit', Effect::Allow),
    ]);

    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());

    $result = $evaluator->evaluate(
        $policy,
        new Subject('*'),
        new Resource('form-1', 'form'),
        new Action('submit')
    );

    expect($result)->toBe(Effect::Allow);
});
```

## Security Considerations

### 1. CSRF Protection

```php
// Always verify CSRF tokens for mutations
Route::middleware(['patrol:form:submit', 'csrf'])->post('/feedback', ...);
```

### 2. Rate Limiting

```php
// Aggressive rate limits for anonymous users
Route::middleware(['patrol:api:read', 'throttle:60,1'])->get('/api/data', ...);
```

### 3. Input Validation

```php
public function submit(Request $request)
{
    // Strict validation for anonymous submissions
    $validated = $request->validate([
        'email' => 'required|email|max:255',
        'message' => 'required|string|max:1000',
    ]);

    // Additional checks
    if ($this->isSpam($validated['message'])) {
        abort(422, 'Spam detected');
    }

    Feedback::create($validated);
}
```

### 4. Resource Visibility

```php
// Model scope for public resources only
class Article extends Model
{
    public function scopePublic($query)
    {
        return $query->where('is_public', true)
            ->where('published_at', '<=', now());
    }
}
```

## Common Mistakes

### ❌ Mistake 1: Overly Permissive Wildcard Rules

**Problem**: Using `* → * → * → ALLOW` grants public access to everything.

```php
// DON'T: Allow anonymous access to ALL resources and actions
$policy = new Policy([
    new PolicyRule(
        subject: '*',
        resource: '*',  // ❌ Matches EVERYTHING
        action: '*',    // ❌ All actions allowed
        effect: Effect::Allow
    ),
]);

// Anonymous user can now:
// - Delete articles
// - Modify system settings
// - Access private data
// - Perform admin actions
```

**Why it's wrong**: No security boundary exists. Anyone can do anything without authentication.

**✅ DO THIS:**
```php
// Correct: Explicitly allow only safe, read-only actions
$policy = new Policy([
    new PolicyRule('*', 'article:public-*', 'read', Effect::Allow),  // ✓ Specific
    new PolicyRule('*', 'file:public-*', 'download', Effect::Allow),  // ✓ Specific

    // Default deny everything else
    new PolicyRule('*', '*', '*', Effect::Deny),
]);
```

---

### ❌ Mistake 2: No Rate Limiting on Public Endpoints

**Problem**: Anonymous endpoints without rate limiting are vulnerable to abuse.

```php
// DON'T: No rate limiting
Route::middleware(['patrol:form:submit'])->post('/feedback', function (Request $request) {
    Feedback::create($request->all());  // ❌ Can be spammed!
    return response()->json(['success' => true]);
});

// Attacker can:
// - Submit thousands of requests per second
// - Fill database with spam
// - DOS the application
```

**Why it's wrong**: Without rate limits, malicious actors can abuse public endpoints to overwhelm your system.

**✅ DO THIS:**
```php
// Correct: Aggressive rate limiting for anonymous users
Route::middleware([
    'patrol:form:submit',
    'throttle:10,1'  // ✓ 10 requests per minute max
])->post('/feedback', function (Request $request) {
    $validated = $request->validate([
        'message' => 'required|string|max:500',
    ]);

    Feedback::create($validated);
    return response()->json(['success' => true]);
});
```

---

### ❌ Mistake 3: No CSRF Protection on Public Mutations

**Problem**: Public POST/PUT/DELETE without CSRF tokens enable CSRF attacks.

```php
// DON'T: No CSRF protection on state changes
Route::middleware(['patrol:form:submit'])->post('/vote', function (Request $request) {
    Vote::create([
        'option' => $request->input('option'),  // ❌ CSRF vulnerable!
        'ip' => $request->ip(),
    ]);
});

// Attacker can embed this in malicious site:
// <form action="https://yoursite.com/vote" method="POST">
//   <input name="option" value="evil-choice">
//   <script>document.forms[0].submit()</script>
// </form>
```

**Why it's wrong**: Without CSRF protection, attackers can trick users into submitting unwanted requests from other sites.

**✅ DO THIS:**
```php
// Correct: Enable CSRF protection for mutations
Route::middleware([
    'patrol:form:submit',
    'csrf',  // ✓ Verify CSRF token
    'throttle:10,1'
])->post('/vote', function (Request $request) {
    $validated = $request->validate([
        'option' => 'required|string|in:option-a,option-b',
    ]);

    Vote::create([
        'option' => $validated['option'],
        'ip' => $request->ip(),
    ]);
});
```

---

### ❌ Mistake 4: Exposing Private Resources via Prefix Mismatch

**Problem**: Resource naming doesn't match policy patterns, exposing private data.

```php
// DON'T: Inconsistent resource naming
$policy = new Policy([
    new PolicyRule('*', 'article:public-*', 'read', Effect::Allow),
]);

// But in code:
$article = Article::find(1);
$resource = new Resource("article-{$article->id}", 'article');  // ❌ Missing "public-" prefix!

// Anonymous check passes even for private articles!
Patrol::check($resource, 'read');  // DENY (correct, but confusing)

// Different article:
$publicArticle = Article::find(2);
$resource = new Resource("public-article-{$publicArticle->id}", 'article');  // ✓ Has prefix

Patrol::check($resource, 'read');  // ALLOW
```

**Why it's wrong**: Inconsistent resource naming makes it easy to accidentally expose private resources or deny access to public ones.

**✅ DO THIS:**
```php
// Correct: Consistent resource resolver
Patrol::resolveResource(function ($resource) {
    if ($resource instanceof Article) {
        $prefix = $resource->is_public ? 'public' : 'private';

        return new Resource(
            "{$prefix}-article-{$resource->id}",  // ✓ Automatic prefix
            'article'
        );
    }
});

// Policy matches prefix
$policy = new Policy([
    new PolicyRule('*', 'article:public-*', 'read', Effect::Allow),
    new PolicyRule('*', 'article:private-*', '*', Effect::Deny),  // Explicit deny
]);
```

## Best Practices

1. **Default Deny**: Only explicitly allow necessary public access
2. **Rate Limiting**: Always rate limit anonymous endpoints (10-60 req/min)
3. **Minimal Exposure**: Limit public actions to read-only when possible
4. **CSRF Protection**: Enable for all state-changing operations (POST/PUT/DELETE)
5. **Audit Logs**: Track anonymous access patterns and abuse
6. **Resource Scoping**: Use prefixes like `public-*` to clearly mark public resources
7. **Input Validation**: Strict validation and sanitization for anonymous input
8. **Spam Detection**: Implement content filtering for user-submitted data
9. **Consistent Naming**: Use resource resolvers to enforce naming conventions
10. **Monitor Abuse**: Alert on unusual anonymous activity patterns

## Debugging Anonymous Authorization

### Problem 1: "Public resource still denied to anonymous users"

**Symptom**: Resources marked as public still return 403 for anonymous users.

**Diagnosis Steps**:

```php
// Step 1: Check subject resolver returns wildcard
Patrol::resolveSubject(function () {
    $subject = new Subject('*');

    Log::debug('Anonymous subject', ['id' => $subject->id()]);
    // Should output: '*'

    return $subject;
});

// Step 2: Check resource naming matches policy pattern
$article = Article::find(1);
$resource = Patrol::resolveResource($article);

dd([
    'resource_id' => $resource->id(),  // Should be: 'public-article-1'
    'expected_pattern' => 'article:public-*',
]);

// Step 3: Check policy has wildcard rule
$policy = app(PolicyRepositoryInterface::class)->find();
$anonymousRules = collect($policy->rules())->filter(fn($rule) =>
    $rule->subject === '*'
);

dd($anonymousRules->count());  // Should be > 0
```

**Common Causes**:
- Subject ID is empty string instead of `*`
- Resource doesn't have `public-` prefix
- No wildcard rules in policy

**Fix**: Ensure resolver returns `new Subject('*')` and resources use correct prefixes.

---

### Problem 2: "How to identify spam/abuse patterns?"

**Symptom**: Need to detect anonymous users abusing public endpoints.

**Diagnosis Steps**:

```php
// Track anonymous submissions by IP
$recentSubmissions = Feedback::where('ip', $request->ip())
    ->where('created_at', '>=', now()->subMinutes(5))
    ->count();

if ($recentSubmissions > 3) {
    Log::warning('Potential spam from anonymous user', [
        'ip' => $request->ip(),
        'count' => $recentSubmissions,
        'user_agent' => $request->userAgent(),
    ]);

    abort(429, 'Too many submissions');
}
```

**Solution**: Implement IP-based rate limiting and spam detection before creating records.

## When to Use ACL Without Users

✅ **Good for:**
- Public documentation sites
- Read-only APIs
- Anonymous surveys/polls
- Public file downloads
- Static content sites

❌ **Avoid for:**
- Applications requiring user tracking
- Systems needing personalization
- Multi-user collaboration
- Audit-heavy applications

## Combining with Authentication

```php
// Mix authenticated and anonymous access
$policy = new Policy([
    // Anonymous users: read-only
    new PolicyRule('*', 'article:*', 'read', Effect::Allow),

    // Authenticated users: can comment
    new PolicyRule('user:*', 'article:*', 'comment', Effect::Allow),

    // Authors: can edit
    new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow),
]);
```

## Related Models

- [ACL](./acl.md) - User-based ACL
- [ABAC](./abac.md) - Attribute-based for more control
- [RESTful](./restful.md) - HTTP-based authorization
