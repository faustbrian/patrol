<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Exceptions;

use RuntimeException;

use function sprintf;

/**
 * Thrown when authorization rate limit is exceeded to prevent abuse.
 *
 * This exception indicates that too many authorization attempts have been made
 * within the configured time window, triggering rate limiting protection. The
 * exception provides retry-after information to inform clients when they can
 * resume making authorization requests.
 *
 * Rate limiting prevents:
 * - Denial-of-service attacks through authorization request floods
 * - Resource exhaustion from excessive policy evaluation
 * - Policy enumeration attacks probing for valid permissions
 * - Brute-force attempts to discover accessible resources
 *
 * When this exception is thrown, applications should:
 * - Return HTTP 429 Too Many Requests status code
 * - Include Retry-After header with seconds from exception
 * - Log the rate limit violation for security monitoring
 * - Consider implementing exponential backoff for repeated violations
 *
 * The retry-after duration indicates when the rate limit window expires and
 * new authorization attempts will be accepted. Clients should wait at least
 * this duration before retrying to avoid triggering additional violations.
 *
 * ```php
 * try {
 *     $patrol->authorize($subject, $resource, $action);
 * } catch (RateLimitExceededException $e) {
 *     return response('Too many requests', 429)
 *         ->header('Retry-After', $e->getRetryAfter());
 * }
 * ```
 *
 * @see RateLimiterInterface For rate limiting implementation
 * @see CacheRateLimiter For cache-based rate limiting
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RateLimitExceededException extends RuntimeException
{
    /**
     * Seconds until the rate limit resets and new attempts are allowed.
     */
    private int $retryAfter = 0;

    /**
     * Create a new rate limit exceeded exception with retry-after information.
     *
     * Provides a static factory method that generates a user-friendly error message
     * including the retry-after duration. The exception message is suitable for
     * display in error responses and logs.
     *
     * @param  int  $availableIn Seconds until the rate limit window expires and new authorization
     *                           attempts will be accepted. This value should be obtained from
     *                           RateLimiterInterface::availableIn() when the rate limit is exceeded.
     *                           Used to populate HTTP Retry-After headers and user feedback.
     * @return self New exception instance with retry-after information stored for later retrieval
     *              via getRetryAfter() method. The exception message includes the retry duration
     *              in a human-readable format.
     */
    public static function create(int $availableIn): self
    {
        $message = sprintf(
            'Too many authorization attempts. Please try again in %d seconds.',
            $availableIn,
        );

        $exception = new self($message);
        $exception->retryAfter = $availableIn;

        return $exception;
    }

    /**
     * Get seconds until retry is allowed.
     *
     * Returns the duration in seconds until the rate limit window expires, allowing
     * clients to implement proper retry logic and populate HTTP Retry-After headers.
     *
     * @return int Seconds to wait before retrying authorization requests
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter ?? 0; // @codeCoverageIgnore
    }
}
