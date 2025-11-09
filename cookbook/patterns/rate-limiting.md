# Rate Limiting - Prevent Authorization Abuse

Protect your authorization system from denial-of-service attacks, resource exhaustion, and policy enumeration by rate limiting authorization checks.

## Problem: Authorization Abuse

Without rate limiting, attackers can abuse authorization systems:

```php
// ❌ Attacker probes for valid resource IDs
for ($i = 1; $i <= 100000; $i++) {
    $canAccess = Patrol::check(new Resource("document:$i"), 'read');
    if ($canAccess === Effect::Allow) {
        // Found valid resource, enumerate further
    }
}
// No rate limit = 100,000 authorization checks in seconds
```

**Attack vectors:**
- **Policy enumeration**: Probe for valid resource IDs or permission boundaries
- **Resource exhaustion**: Overwhelm database with authorization queries
- **DoS attacks**: Saturate authorization system to deny service
- **Brute force**: Try combinations to discover access patterns

---

## Solution: Rate Limiter

Implement sliding window rate limiting for authorization checks:

```php
use Patrol\Core\Contracts\RateLimiterInterface;
use Patrol\Core\Exceptions\RateLimitExceededException;

class AuthorizationService
{
    public function __construct(
        private RateLimiterInterface $rateLimiter,
        private PolicyEvaluator $evaluator,
    ) {}

    public function authorize(Subject $subject, Resource $resource, Action $action): Effect
    {
        // Rate limit key combining subject, resource, and action
        $key = "patrol:auth:{$subject->id}:{$resource->id}:{$action->value}";

        // Allow max 60 attempts per minute
        if (!$this->rateLimiter->attempt($key, 60, 60)) {
            $retryAfter = $this->rateLimiter->availableIn($key);
            throw RateLimitExceededException::create($retryAfter);
        }

        // Proceed with authorization
        return $this->evaluator->evaluate(...);
    }
}
```

**Protection provided:**
- ✅ Prevents rapid-fire authorization probing
- ✅ Limits resource exhaustion attacks
- ✅ Provides retry-after feedback to clients
- ✅ Configurable per-scope limits

---

## Core Concepts

### Rate Limiting Strategies

| Strategy | Key Format | Use Case | Example Limit |
|----------|-----------|----------|---------------|
| **Per-user global** | `patrol:auth:{user_id}` | Prevent user abuse | 300/min |
| **Per-resource** | `patrol:auth:{resource_id}` | Protect popular resources | 1000/min |
| **Per-user-resource** | `patrol:auth:{user_id}:{resource_id}` | Fine-grained control | 60/min |
| **Per-user-action** | `patrol:auth:{user_id}:{action}` | Limit expensive actions | 10/min for `delete` |
| **Per-IP** | `patrol:auth:{ip_address}` | Anonymous/unauthenticated | 100/min |

### Sliding Window Algorithm

Patrol uses a sliding window approach:

```
Time windows:
┌─────────────────────────────────────────────────────┐
│ [────60s window────] → Expires                      │
│   10 requests                                        │
│                                                      │
│     [────60s window────] → Active                   │
│       15 requests (reset in 45s)                    │
└─────────────────────────────────────────────────────┘

Behavior:
- Counter increments with each attempt
- Window expires after decay seconds
- New window starts on next attempt after expiry
- availableIn() returns seconds until window resets
```

---

## Implementation Examples

### 1. Laravel Middleware Integration

Apply rate limiting at the middleware layer:

```php
namespace App\Http\Middleware;

use Closure;
use Patrol\Core\Contracts\RateLimiterInterface;
use Patrol\Core\Exceptions\RateLimitExceededException;

class RateLimitAuthorization
{
    public function __construct(
        private RateLimiterInterface $rateLimiter
    ) {}

    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            // Rate limit by IP for unauthenticated requests
            $key = "patrol:auth:ip:{$request->ip()}";
            $maxAttempts = 100; // Stricter for anonymous
        } else {
            // Rate limit by user ID
            $key = "patrol:auth:user:{$user->id}";
            $maxAttempts = 300;
        }

        $decaySeconds = 60;

        if (!$this->rateLimiter->attempt($key, $maxAttempts, $decaySeconds)) {
            $retryAfter = $this->rateLimiter->availableIn($key);

            throw new RateLimitExceededException(
                "Too many authorization attempts. Retry after {$retryAfter} seconds.",
                $retryAfter
            );
        }

        return $next($request);
    }
}
```

Register in `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\RateLimitAuthorization::class,
        // ...
    ],
];
```

### 2. Per-Action Rate Limiting

Different limits for different actions:

```php
use Patrol\Laravel\Facades\Patrol as PatrolFacade;

class DocumentController
{
    public function destroy(Document $document)
    {
        $user = auth()->user();
        $rateLimiter = app(RateLimiterInterface::class);

        // Stricter limit for destructive actions
        $deleteKey = "patrol:auth:user:{$user->id}:action:delete";
        if (!$rateLimiter->attempt($deleteKey, 10, 60)) {
            $retryAfter = $rateLimiter->availableIn($deleteKey);
            return response()->json([
                'error' => 'Too many delete attempts',
                'retry_after' => $retryAfter,
            ], 429);
        }

        // Proceed with authorization
        PatrolFacade::authorize($document, 'delete');

        $document->delete();

        return response()->noContent();
    }

    public function show(Document $document)
    {
        $user = auth()->user();
        $rateLimiter = app(RateLimiterInterface::class);

        // Lenient limit for read operations
        $readKey = "patrol:auth:user:{$user->id}:action:read";
        if (!$rateLimiter->attempt($readKey, 300, 60)) {
            $retryAfter = $rateLimiter->availableIn($readKey);
            return response()->json([
                'error' => 'Too many read attempts',
                'retry_after' => $retryAfter,
            ], 429);
        }

        PatrolFacade::authorize($document, 'read');

        return new DocumentResource($document);
    }
}
```

### 3. Composite Rate Limiting

Combine multiple rate limit scopes:

```php
class RateLimitedAuthorizationService
{
    public function authorize(Subject $subject, Resource $resource, Action $action): Effect
    {
        // Global per-user limit (broad protection)
        $globalKey = "patrol:auth:user:{$subject->id}";
        if (!$this->rateLimiter->attempt($globalKey, 300, 60)) {
            throw RateLimitExceededException::create(
                $this->rateLimiter->availableIn($globalKey)
            );
        }

        // Per-resource limit (protect popular resources)
        $resourceKey = "patrol:auth:resource:{$resource->id}";
        if (!$this->rateLimiter->attempt($resourceKey, 1000, 60)) {
            throw RateLimitExceededException::create(
                $this->rateLimiter->availableIn($resourceKey)
            );
        }

        // Fine-grained per-user-resource-action limit
        $specificKey = "patrol:auth:{$subject->id}:{$resource->id}:{$action->value}";
        if (!$this->rateLimiter->attempt($specificKey, 60, 60)) {
            throw RateLimitExceededException::create(
                $this->rateLimiter->availableIn($specificKey)
            );
        }

        return $this->evaluator->evaluate(...);
    }
}
```

### 4. Custom Rate Limit Implementation

Create a custom rate limiter using Laravel's cache:

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Patrol\Core\Contracts\RateLimiterInterface;

class CacheRateLimiter implements RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        $this->hit($key, $decaySeconds);

        return true;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $attempts = Cache::get($key, 0);

        return $attempts >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $attempts = Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, $decaySeconds);

        // Set timer on first attempt
        if ($attempts === 1) {
            Cache::put("{$key}:timer", time() + $decaySeconds, $decaySeconds);
        }

        return $attempts;
    }

    public function clear(string $key): void
    {
        Cache::forget($key);
        Cache::forget("{$key}:timer");
    }

    public function availableIn(string $key): int
    {
        $timer = Cache::get("{$key}:timer");

        if (!$timer) {
            return 0;
        }

        return max(0, $timer - time());
    }
}
```

Bind in `AppServiceProvider`:

```php
use Patrol\Core\Contracts\RateLimiterInterface;

public function register()
{
    $this->app->singleton(RateLimiterInterface::class, CacheRateLimiter::class);
}
```

---

## Configuration

### Environment-Based Limits

Configure limits per environment:

```php
// config/patrol.php
return [
    'rate_limiting' => [
        'enabled' => env('PATROL_RATE_LIMITING_ENABLED', true),

        'limits' => [
            // Global per-user limit
            'user' => [
                'max_attempts' => env('PATROL_USER_MAX_ATTEMPTS', 300),
                'decay_seconds' => env('PATROL_USER_DECAY_SECONDS', 60),
            ],

            // Per-resource limit
            'resource' => [
                'max_attempts' => env('PATROL_RESOURCE_MAX_ATTEMPTS', 1000),
                'decay_seconds' => env('PATROL_RESOURCE_DECAY_SECONDS', 60),
            ],

            // Per-IP for unauthenticated
            'ip' => [
                'max_attempts' => env('PATROL_IP_MAX_ATTEMPTS', 100),
                'decay_seconds' => env('PATROL_IP_DECAY_SECONDS', 60),
            ],

            // Per-action overrides
            'actions' => [
                'delete' => [
                    'max_attempts' => env('PATROL_DELETE_MAX_ATTEMPTS', 10),
                    'decay_seconds' => 60,
                ],
                'export' => [
                    'max_attempts' => env('PATROL_EXPORT_MAX_ATTEMPTS', 5),
                    'decay_seconds' => 300, // 5 minutes
                ],
            ],
        ],
    ],
];
```

`.env` file:

```bash
# Production: Strict limits
PATROL_RATE_LIMITING_ENABLED=true
PATROL_USER_MAX_ATTEMPTS=300
PATROL_IP_MAX_ATTEMPTS=100

# Development: Lenient limits
PATROL_RATE_LIMITING_ENABLED=false

# Testing: Disabled
PATROL_RATE_LIMITING_ENABLED=false
```

---

## Exception Handling

### HTTP Response for Rate Limit Exceeded

```php
namespace App\Exceptions;

use Patrol\Core\Exceptions\RateLimitExceededException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof RateLimitExceededException) {
            return response()->json([
                'error' => 'Too many authorization attempts',
                'message' => $exception->getMessage(),
                'retry_after' => $exception->getRetryAfter(),
            ], 429)
            ->header('Retry-After', $exception->getRetryAfter())
            ->header('X-RateLimit-Limit', $exception->getMaxAttempts())
            ->header('X-RateLimit-Remaining', 0);
        }

        return parent::render($request, $exception);
    }
}
```

### Client-Side Retry Logic

```javascript
// Frontend retry logic
async function checkAuthorization(resourceId, action) {
    try {
        const response = await fetch(`/api/resources/${resourceId}/authorize`, {
            method: 'POST',
            body: JSON.stringify({ action }),
        });

        if (response.status === 429) {
            const data = await response.json();
            const retryAfter = data.retry_after;

            console.warn(`Rate limited. Retrying in ${retryAfter}s`);

            // Wait and retry
            await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
            return checkAuthorization(resourceId, action);
        }

        return response.json();
    } catch (error) {
        console.error('Authorization check failed:', error);
        throw error;
    }
}
```

---

## Best Practices

### 1. Whitelist Trusted Users

Exempt trusted users from rate limiting:

```php
public function authorize(Subject $subject, Resource $resource, Action $action): Effect
{
    // Skip rate limiting for admins
    if (in_array('admin', $subject->attributes['roles'] ?? [])) {
        return $this->evaluator->evaluate(...);
    }

    // Apply rate limiting for regular users
    $key = "patrol:auth:{$subject->id}";
    if (!$this->rateLimiter->attempt($key, 300, 60)) {
        throw RateLimitExceededException::create(...);
    }

    return $this->evaluator->evaluate(...);
}
```

### 2. Monitor Rate Limit Hits

Log when users hit rate limits:

```php
if (!$this->rateLimiter->attempt($key, $maxAttempts, $decaySeconds)) {
    \Log::warning('Rate limit exceeded', [
        'user_id' => $subject->id,
        'resource_id' => $resource->id,
        'action' => $action->value,
        'retry_after' => $this->rateLimiter->availableIn($key),
    ]);

    throw RateLimitExceededException::create(...);
}
```

### 3. Clear Rate Limits on Success

Reward successful operations by clearing counters:

```php
public function successfulLogin(User $user)
{
    $rateLimiter = app(RateLimiterInterface::class);

    // Clear failed login attempts
    $rateLimiter->clear("patrol:auth:user:{$user->id}:action:login");
}
```

### 4. Progressive Rate Limiting

Increase strictness based on behavior:

```php
public function authorize(Subject $subject, Resource $resource, Action $action): Effect
{
    $key = "patrol:auth:user:{$subject->id}";
    $attempts = Cache::get($key, 0);

    // Progressive limits
    if ($attempts < 100) {
        $maxAttempts = 300; // Normal
    } elseif ($attempts < 200) {
        $maxAttempts = 150; // Warning
    } else {
        $maxAttempts = 60;  // Strict
    }

    if (!$this->rateLimiter->attempt($key, $maxAttempts, 60)) {
        throw RateLimitExceededException::create(...);
    }

    return $this->evaluator->evaluate(...);
}
```

---

## Testing

```php
use Patrol\Core\Contracts\RateLimiterInterface;
use Patrol\Laravel\RateLimiting\CacheRateLimiter;

test('rate limiter blocks after max attempts', function () {
    $rateLimiter = new CacheRateLimiter();
    $key = 'test:key';
    $maxAttempts = 5;
    $decaySeconds = 60;

    // First 5 attempts succeed
    for ($i = 0; $i < 5; $i++) {
        expect($rateLimiter->attempt($key, $maxAttempts, $decaySeconds))->toBeTrue();
    }

    // 6th attempt fails
    expect($rateLimiter->attempt($key, $maxAttempts, $decaySeconds))->toBeFalse();

    // Retry-after is set
    expect($rateLimiter->availableIn($key))->toBeGreaterThan(0);
});

test('rate limiter resets after decay period', function () {
    $rateLimiter = new CacheRateLimiter();
    $key = 'test:key';

    // Hit limit
    for ($i = 0; $i < 5; $i++) {
        $rateLimiter->attempt($key, 5, 1); // 1 second decay
    }

    expect($rateLimiter->attempt($key, 5, 1))->toBeFalse();

    // Wait for decay
    sleep(2);

    // New attempt succeeds
    expect($rateLimiter->attempt($key, 5, 1))->toBeTrue();
});

test('rate limiter can be manually cleared', function () {
    $rateLimiter = new CacheRateLimiter();
    $key = 'test:key';

    // Hit limit
    for ($i = 0; $i < 5; $i++) {
        $rateLimiter->attempt($key, 5, 60);
    }

    expect($rateLimiter->attempt($key, 5, 60))->toBeFalse();

    // Clear manually
    $rateLimiter->clear($key);

    // New attempt succeeds
    expect($rateLimiter->attempt($key, 5, 60))->toBeTrue();
});
```

---

## Security Considerations

### 1. Distributed Rate Limiting

For multi-server deployments, use shared cache:

```php
// Use Redis instead of local cache
Cache::store('redis')->put($key, $attempts, $decaySeconds);
```

### 2. Rate Limit Key Security

Never expose rate limit keys to clients:

```php
// ❌ BAD: Attacker can bypass by changing user_id
$key = $request->input('user_id'); // Controlled by client

// ✅ GOOD: Server-side determination
$key = "patrol:auth:user:{$request->user()->id}";
```

### 3. Combine with Other Defenses

Rate limiting is one layer. Combine with:
- **IP blocking**: Ban repeat offenders
- **CAPTCHA**: Challenge suspicious behavior
- **Audit logging**: Track authorization patterns
- **Anomaly detection**: Alert on unusual activity

---

## Related Documentation

- **[Batch Authorization](./batch-authorization.md)** - Optimize bulk authorization
- **[Security](../../SECURITY.md)** - Authorization security best practices
- **[Configuration](../guides/configuration.md)** - System configuration
- **Exception Handling** (coming soon) - Error handling patterns

---

**Security tip:** Monitor rate limit hits in production. A sudden spike may indicate an attack or misconfigured client. Set up alerts for sustained rate limiting.
