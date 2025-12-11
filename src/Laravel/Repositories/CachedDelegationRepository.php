<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;

/**
 * Cache-aware delegation repository decorator.
 *
 * Wraps an underlying delegation repository with caching to improve performance
 * on the authorization hot path. Caches active delegations per delegate with
 * automatic invalidation on create/revoke operations to maintain consistency.
 *
 * Caching strategy:
 * - Cache active delegations by delegate ID (most common query)
 * - Cache individual delegations by ID for revocation operations
 * - Invalidate delegate cache on create/revoke
 * - Use configured TTL to balance performance and staleness
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CachedDelegationRepository implements DelegationRepositoryInterface
{
    /**
     * Create a new cached delegation repository.
     *
     * @param DelegationRepositoryInterface $repository The underlying repository for actual storage operations.
     *                                                  All cache misses and mutations are delegated to this repository.
     * @param CacheRepository               $cache      Laravel cache instance for delegation caching. Typically Redis
     *                                                  or Memcached for production to share cache across instances.
     * @param int                           $ttl        Cache time-to-live in seconds. Balances performance against
     *                                                  staleness risk. Default is 3600 (1 hour).
     */
    public function __construct(
        private DelegationRepositoryInterface $repository,
        private CacheRepository $cache,
        private int $ttl = 3_600,
    ) {}

    /**
     * Create a delegation and invalidate relevant caches.
     *
     * Delegates to the underlying repository then invalidates the delegate's
     * active delegation cache to ensure the new delegation is included in
     * subsequent authorization checks.
     *
     * @param Delegation $delegation The delegation to create
     */
    #[Override()]
    public function create(Delegation $delegation): void
    {
        $this->repository->create($delegation);

        // Invalidate delegate's active delegations cache
        $this->cache->forget($this->activeCacheKey($delegation->delegateId));
    }

    /**
     * Find delegation by ID with caching.
     *
     * Caches individual delegations for efficient lookup during revocation
     * operations. Uses the configured TTL for individual delegation caching.
     *
     * @param  string          $id The delegation identifier
     * @return null|Delegation The delegation if found, null otherwise
     */
    #[Override()]
    public function findById(string $id): ?Delegation
    {
        return $this->cache->remember(
            key: $this->idCacheKey($id),
            ttl: $this->ttl,
            callback: fn (): ?Delegation => $this->repository->findById($id),
        );
    }

    /**
     * Find active delegations for a delegate with caching.
     *
     * This is the hot path for authorization checks. Caches results per delegate
     * to minimize database queries during repeated authorization evaluations.
     *
     * Cache invalidation occurs on:
     * - New delegation creation for this delegate
     * - Delegation revocation for this delegate
     * - Expiration (via TTL)
     *
     * @param  string            $delegateId The delegate subject identifier
     * @return array<Delegation> Active delegations for the delegate
     */
    #[Override()]
    public function findActiveForDelegate(string $delegateId): array
    {
        return $this->cache->remember(
            key: $this->activeCacheKey($delegateId),
            ttl: $this->ttl,
            callback: fn (): array => $this->repository->findActiveForDelegate($delegateId),
        );
    }

    /**
     * Revoke delegation and invalidate caches.
     *
     * Revokes the delegation in underlying storage then invalidates caches
     * for both the specific delegation and the delegate's active delegations.
     *
     * @param string $id The delegation to revoke
     */
    #[Override()]
    public function revoke(string $id): void
    {
        // Fetch before revocation to get delegate ID for cache invalidation
        $delegation = $this->repository->findById($id);

        $this->repository->revoke($id);

        // Invalidate caches
        $this->cache->forget($this->idCacheKey($id));

        if (!$delegation instanceof Delegation) {
            return;
        }

        $this->cache->forget($this->activeCacheKey($delegation->delegateId));
    }

    /**
     * Cleanup delegations (no caching needed).
     *
     * Delegates directly to underlying repository since cleanup is an infrequent
     * batch operation. No cache invalidation needed as expired/revoked delegations
     * are already excluded from active queries.
     *
     * @return int Number of delegations removed
     */
    #[Override()]
    public function cleanup(): int
    {
        return $this->repository->cleanup();
    }

    /**
     * Generate cache key for active delegations by delegate.
     *
     * @param  string $delegateId The delegate subject identifier
     * @return string The formatted cache key
     */
    private function activeCacheKey(string $delegateId): string
    {
        return 'patrol:delegations:active:'.$delegateId;
    }

    /**
     * Generate cache key for delegation by ID.
     *
     * @param  string $id The delegation identifier
     * @return string The formatted cache key
     */
    private function idCacheKey(string $id): string
    {
        return 'patrol:delegations:id:'.$id;
    }
}
