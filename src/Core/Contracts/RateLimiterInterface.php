<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

/**
 * Tracks and limits authorization attempts to prevent abuse and resource exhaustion.
 *
 * Implementations provide rate limiting functionality for authorization checks using
 * a sliding window algorithm. This prevents denial-of-service attacks, resource exhaustion,
 * and policy enumeration attacks where attackers probe for valid resource identifiers or
 * permission boundaries through repeated authorization attempts.
 *
 * Rate limiting is typically applied per subject-resource-action combination, using a
 * composite key to track attempts. The rate limiter uses two time-based buckets:
 * - Attempt counter with TTL tracking request volume
 * - Timer tracking when the window expires for retry-after calculations
 *
 * Common rate limit keys:
 * - "patrol:auth:{subject_id}:{resource_id}:{action}" for fine-grained control
 * - "patrol:auth:{subject_id}" for global per-user limits
 * - "patrol:auth:{ip_address}" for IP-based throttling
 *
 * @see CacheRateLimiter For cache-based implementation using Laravel's cache system
 * @see RateLimitExceededException Thrown when attempt() returns false
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RateLimiterInterface
{
    /**
     * Attempt to execute a rate-limited authorization check.
     *
     * Checks if the rate limit has been exceeded and increments the attempt counter
     * if allowed. Returns false if too many attempts have been made, indicating that
     * the caller should reject the authorization request and potentially throw a
     * RateLimitExceededException with the retry-after duration.
     *
     * This method combines tooManyAttempts() and hit() in an atomic operation to
     * prevent race conditions in concurrent environments. Implementations should
     * ensure thread-safety when using distributed caching systems.
     *
     * ```php
     * if (!$rateLimiter->attempt('patrol:auth:user-123', 60, 60)) {
     *     $retryAfter = $rateLimiter->availableIn('patrol:auth:user-123');
     *     throw RateLimitExceededException::create($retryAfter);
     * }
     * ```
     *
     * @param  string $key          Unique identifier for tracking this rate limit scope (e.g.,
     *                              "patrol:auth:{subject}:{resource}:{action}"). Should be consistent
     *                              across all related authorization checks to properly limit attempts.
     * @param  int    $maxAttempts  Maximum number of authorization attempts allowed within the decay
     *                              window. Common values: 60 for strict limits, 300 for lenient limits.
     * @param  int    $decaySeconds Time window duration in seconds for the sliding window. The attempt
     *                              counter resets after this duration. Common values: 60 (1 minute),
     *                              3600 (1 hour), 86400 (1 day).
     * @return bool   True if the authorization attempt is allowed (under rate limit), false if too many
     *                attempts have been made and the request should be rejected. When false, use
     *                availableIn() to determine retry-after duration.
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Check if the rate limit has been exceeded without incrementing the counter.
     *
     * Performs a read-only check to determine if the attempt limit has been reached
     * for the specified key. Does not modify the attempt counter, allowing callers to
     * check the rate limit status without consuming an attempt. Useful for pre-flight
     * checks or UI feedback before attempting authorization.
     *
     * @param  string $key         Unique identifier for tracking this rate limit scope
     * @param  int    $maxAttempts Maximum number of attempts allowed within the current window
     * @return bool   True if the attempt count has reached or exceeded maxAttempts and further
     *                attempts should be rejected, false if attempts are still available
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Record a new authorization attempt and increment the counter.
     *
     * Increments the attempt counter for the specified key and sets the decay timer
     * if this is the first attempt in the current window. The counter persists for
     * the decay duration, after which it resets to zero allowing new attempts.
     *
     * Implementations should handle the initial counter creation atomically to prevent
     * race conditions. The timer key is typically stored as "{key}:timer" with a
     * Unix timestamp representing when the window expires.
     *
     * @param  string $key          Unique identifier for tracking this rate limit scope
     * @param  int    $decaySeconds Time window duration in seconds. Defaults to 60 seconds if not
     *                              specified. The attempt counter and timer expire after this duration.
     * @return int    Total number of attempts recorded for this key within the current window,
     *                including the newly recorded attempt. Use this to determine how many attempts
     *                remain before hitting maxAttempts limit.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Clear all attempt data for a rate limit key.
     *
     * Removes both the attempt counter and timer for the specified key, effectively
     * resetting the rate limit to allow immediate new attempts. Useful for manually
     * clearing rate limits after successful authentication, administrative overrides,
     * or when implementing "forgive and forget" policies.
     *
     * Should remove all associated cache keys including:
     * - Main counter key: "{key}"
     * - Timer key: "{key}:timer"
     *
     * @param string $key Unique identifier whose rate limit data should be cleared
     */
    public function clear(string $key): void;

    /**
     * Calculate seconds until the rate limit window resets.
     *
     * Returns the number of seconds remaining until the rate limit counter resets
     * and new attempts are allowed. Used to populate retry-after response headers
     * and provide user feedback about when they can retry authorization requests.
     *
     * If no timer is set (no attempts recorded or window already expired), returns 0
     * to indicate immediate retry is possible. Implementations use the stored timer
     * timestamp to calculate the remaining duration.
     *
     * @param  string $key Unique identifier for the rate limit scope
     * @return int    Number of seconds remaining until the rate limit resets, or 0 if the
     *                window has expired or no attempts have been recorded. Use this value
     *                for HTTP Retry-After headers or user interface countdown displays.
     */
    public function availableIn(string $key): int;
}
