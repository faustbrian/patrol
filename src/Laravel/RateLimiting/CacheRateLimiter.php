<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\RateLimiting;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Date;
use Override;
use Patrol\Core\Contracts\RateLimiterInterface;

use function is_int;
use function max;

/**
 * Cache-based rate limiter implementation using Laravel's cache system.
 *
 * Implements rate limiting for authorization attempts using Laravel's unified cache
 * API, supporting any configured cache driver (Redis, Memcached, DynamoDB, database,
 * or file). The implementation uses a sliding window algorithm with two cache keys
 * per rate limit: an attempt counter and a timer tracking window expiration.
 *
 * Cache key structure:
 * - Main key: Stores attempt count (integer)
 * - Timer key: "{key}:timer" stores expiration timestamp (Unix timestamp)
 *
 * The implementation is thread-safe when using atomic cache drivers like Redis or
 * Memcached, but may experience race conditions with file or database cache due to
 * non-atomic read-modify-write operations. For high-concurrency environments, use
 * Redis or Memcached as the cache driver.
 *
 * Supported cache drivers:
 * - Redis (recommended for production, atomic operations)
 * - Memcached (atomic operations, no persistence)
 * - DynamoDB (atomic operations, managed service)
 * - Database (persistent but not atomic)
 * - File (not recommended for concurrent access)
 * - Array (in-memory, testing only)
 *
 * ```php
 * $rateLimiter = new CacheRateLimiter(Cache::store('redis'));
 *
 * if (!$rateLimiter->attempt('patrol:auth:user-123', 60, 60)) {
 *     $retryAfter = $rateLimiter->availableIn('patrol:auth:user-123');
 *     throw RateLimitExceededException::create($retryAfter);
 * }
 * ```
 *
 * @see RateLimiterInterface For the rate limiter contract
 * @see CacheRepository For Laravel's cache interface
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class CacheRateLimiter implements RateLimiterInterface
{
    /**
     * Create a new cache-based rate limiter.
     *
     * @param CacheRepository $cache Laravel cache repository instance for storing rate limit data.
     *                               Can use any configured cache driver (redis, memcached, database, file).
     *                               For production deployments with high concurrency, use Redis or Memcached
     *                               to ensure atomic operations and prevent race conditions in attempt counting.
     */
    public function __construct(
        private CacheRepository $cache,
    ) {}

    /**
     * Attempt to execute a rate-limited authorization check.
     *
     * Checks the current attempt count and increments it if under the limit. Returns
     * false if the rate limit has been exceeded, indicating the caller should reject
     * the authorization request. The check and increment are not atomic, so race
     * conditions may occur with non-atomic cache drivers.
     *
     * @param  string $key          Unique identifier for this rate limit scope
     * @param  int    $maxAttempts  Maximum number of attempts allowed within the window
     * @param  int    $decaySeconds Time window duration in seconds
     * @return bool   True if attempt is allowed (under limit), false if rate limit exceeded
     */
    #[Override()]
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        // Check if rate limit already exceeded
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        // Increment attempt counter
        $this->hit($key, $decaySeconds);

        return true;
    }

    /**
     * Check if rate limit has been exceeded without incrementing counter.
     *
     * Performs a read-only check against the attempt counter stored in cache.
     * Returns true when the counter has reached or exceeded the maximum allowed
     * attempts. The counter defaults to 0 if not found (no attempts recorded yet).
     *
     * @param  string $key         Unique identifier for this rate limit scope
     * @param  int    $maxAttempts Maximum number of attempts allowed
     * @return bool   True if too many attempts have been made, false if under limit
     */
    #[Override()]
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->cache->get($key, 0) >= $maxAttempts;
    }

    /**
     * Record a new authorization attempt and increment the counter.
     *
     * Increments the attempt counter in cache and sets the expiration timer if this
     * is the first attempt in the current window. The counter and timer both expire
     * after the decay duration, automatically resetting the rate limit.
     *
     * The timer is stored as a Unix timestamp representing when the window expires,
     * enabling accurate retry-after calculation in availableIn(). The timer is only
     * set on the first attempt to ensure consistent window boundaries.
     *
     * @param  string $key          Unique identifier for this rate limit scope
     * @param  int    $decaySeconds Time window duration in seconds (default: 60)
     * @return int    Total number of attempts recorded within the current window
     */
    #[Override()]
    public function hit(string $key, int $decaySeconds = 60): int
    {
        // Get current attempt count, defaulting to 0 if not found
        $current = $this->cache->get($key, 0);
        // Ensure $current is an int before adding
        $currentInt = is_int($current) ? $current : 0;
        $attempts = $currentInt + 1;

        // Store incremented counter with TTL
        $this->cache->put($key, $attempts, $decaySeconds);

        // Set timer on first attempt to track window expiration
        if ($currentInt === 0) {
            $this->cache->put($key.':timer', Date::now()->getTimestamp() + $decaySeconds, $decaySeconds);
        }

        return $attempts;
    }

    /**
     * Clear all rate limit data for a key.
     *
     * Removes both the attempt counter and timer from cache, effectively resetting
     * the rate limit to allow immediate new attempts. Useful for manual rate limit
     * resets, administrative overrides, or implementing forgiveness policies.
     *
     * @param string $key Unique identifier whose rate limit data should be cleared
     */
    #[Override()]
    public function clear(string $key): void
    {
        $this->cache->forget($key);
        $this->cache->forget($key.':timer');
    }

    /**
     * Calculate seconds until the rate limit window resets.
     *
     * Retrieves the expiration timer from cache and calculates the remaining duration
     * until the window expires and new attempts are allowed. Returns 0 if the timer
     * is not found or has already expired, indicating immediate retry is possible.
     *
     * The calculation uses the current timestamp to determine the remaining duration,
     * ensuring accurate retry-after values even as time progresses. The result is
     * suitable for HTTP Retry-After headers and user interface countdown displays.
     *
     * @param  string $key Unique identifier for this rate limit scope
     * @return int    Seconds remaining until rate limit resets, or 0 if expired/not set
     */
    #[Override()]
    public function availableIn(string $key): int
    {
        // Get timer timestamp, defaulting to current time if not found
        $timer = $this->cache->get($key.':timer', Date::now()->getTimestamp());
        // Ensure $timer is an int before subtraction
        $timerInt = is_int($timer) ? $timer : Date::now()->getTimestamp();

        // Calculate remaining seconds, ensuring non-negative result
        return max(0, $timerInt - Date::now()->getTimestamp());
    }
}
