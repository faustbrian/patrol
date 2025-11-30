<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Override;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function sprintf;

/**
 * Cache-decorated policy repository for improved authorization performance.
 *
 * Wraps any PolicyRepositoryInterface implementation with a caching layer to reduce
 * database queries and improve response times for authorization checks. Policies are
 * cached per subject-resource pair with configurable TTL.
 *
 * Provides granular cache invalidation methods for keeping policies fresh after
 * changes while maintaining high performance for read-heavy authorization workloads.
 *
 * ```php
 * $cachedRepo = new CachedPolicyRepository(
 *     repository: new DatabasePolicyRepository(),
 *     cache: app('cache')->store('redis'),
 *     ttl: 3600 // 1 hour
 * );
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CachedPolicyRepository implements PolicyRepositoryInterface
{
    /**
     * Create a new cached policy repository instance.
     *
     * @param PolicyRepositoryInterface $repository The underlying policy repository to wrap with caching.
     *                                              All cache misses are delegated to this repository, and
     *                                              the results are cached for subsequent requests.
     * @param CacheRepository           $cache      The Laravel cache store to use for caching policies.
     *                                              Consider using Redis or Memcached for production to
     *                                              share cache across application instances.
     * @param int                       $ttl        Time-to-live in seconds for cached policies. Balance between
     *                                              performance and policy freshness. Default is 3600 (1 hour).
     *                                              Lower values ensure fresher policies but increase cache misses.
     */
    public function __construct(
        private PolicyRepositoryInterface $repository,
        private CacheRepository $cache,
        private int $ttl = 3_600,
    ) {}

    /**
     * Retrieve policies for a subject-resource pair with caching.
     *
     * Checks the cache first for a previously loaded policy. On cache miss,
     * delegates to the underlying repository and caches the result for the
     * configured TTL duration.
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   The cached or freshly loaded policy containing applicable rules
     */
    #[Override()]
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $key = $this->getCacheKey($subject, $resource);

        return $this->cache->remember(
            $key,
            $this->ttl,
            fn (): Policy => $this->repository->getPoliciesFor($subject, $resource),
        );
    }

    /**
     * Invalidate cached policies for a specific subject-resource pair.
     *
     * Removes the cached policy entry, forcing the next getPoliciesFor() call
     * to reload from the underlying repository. Use after policy updates that
     * affect specific subject-resource combinations.
     *
     * @param Subject  $subject  The subject whose cached policy should be invalidated
     * @param resource $resource The resource whose cached policy should be invalidated
     */
    public function invalidate(Subject $subject, Resource $resource): void
    {
        $this->cache->forget($this->getCacheKey($subject, $resource));
    }

    /**
     * Invalidate all cached policies across all subjects and resources.
     *
     * Flushes the entire cache store. Use after broad policy changes that affect
     * multiple subject-resource pairs. Note this may affect other cached data if
     * using a shared cache store.
     */
    public function invalidateAll(): void
    {
        Cache::getStore()->flush();
    }

    /**
     * Save policy and invalidate all cached policies.
     *
     * Delegates to underlying repository then flushes cache to ensure fresh policies.
     *
     * @param Policy $policy The policy to save
     */
    #[Override()]
    public function save(Policy $policy): void
    {
        $this->repository->save($policy);
        $this->invalidateAll();
    }

    /**
     * Persist multiple policies atomically and invalidate cache.
     *
     * Delegates to underlying repository for bulk insert then flushes cache.
     *
     * @param array<Policy> $policies The policies to persist
     */
    #[Override()]
    public function saveMany(array $policies): void
    {
        $this->repository->saveMany($policies);
        $this->invalidateAll();
    }

    /**
     * Delete multiple policies by identifiers and invalidate cache.
     *
     * Delegates to underlying repository for bulk delete then flushes cache.
     *
     * @param array<string> $ruleIds The rule identifiers to delete
     */
    #[Override()]
    public function deleteMany(array $ruleIds): void
    {
        $this->repository->deleteMany($ruleIds);
        $this->invalidateAll();
    }

    /**
     * Soft delete a policy rule and invalidate cache.
     *
     * Delegates to underlying repository then flushes cache.
     *
     * @param string $ruleId The rule identifier to soft delete
     */
    #[Override()]
    public function softDelete(string $ruleId): void
    {
        $this->repository->softDelete($ruleId);
        $this->invalidateAll();
    }

    /**
     * Restore a soft-deleted policy rule and invalidate cache.
     *
     * Delegates to underlying repository then flushes cache.
     *
     * @param string $ruleId The rule identifier to restore
     */
    #[Override()]
    public function restore(string $ruleId): void
    {
        $this->repository->restore($ruleId);
        $this->invalidateAll();
    }

    /**
     * Permanently delete a policy rule and invalidate cache.
     *
     * Delegates to underlying repository then flushes cache.
     *
     * @param string $ruleId The rule identifier to permanently delete
     */
    #[Override()]
    public function forceDelete(string $ruleId): void
    {
        $this->repository->forceDelete($ruleId);
        $this->invalidateAll();
    }

    /**
     * Retrieve all soft-deleted policy rules.
     *
     * Delegates to underlying repository without caching (administrative operation).
     *
     * @return Policy Policy containing all soft-deleted rules
     */
    #[Override()]
    public function getTrashed(): Policy
    {
        return $this->repository->getTrashed();
    }

    /**
     * Retrieve all policies including soft-deleted ones with caching.
     *
     * Caches the result under a different key to avoid conflicting with normal queries.
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   Policy containing all rules regardless of deletion status
     */
    #[Override()]
    public function getWithTrashed(Subject $subject, Resource $resource): Policy
    {
        $key = sprintf('%s:with-trashed', $this->getCacheKey($subject, $resource));

        return $this->cache->remember(
            $key,
            $this->ttl,
            fn (): Policy => $this->repository->getWithTrashed($subject, $resource),
        );
    }

    /**
     * Retrieve policies for multiple resources with caching.
     *
     * @param  Subject               $subject   The subject requesting access
     * @param  array<resource>       $resources Resources to check
     * @return array<string, Policy> Map of resource ID to policy
     */
    #[Override()]
    public function getPoliciesForBatch(Subject $subject, array $resources): array
    {
        // Delegate to underlying repository
        // TODO: Implement batch cache lookup optimization
        return $this->repository->getPoliciesForBatch($subject, $resources);
    }

    /**
     * Generate a cache key for a subject-resource pair.
     *
     * Creates a namespaced cache key that uniquely identifies the policy for
     * a specific subject accessing a specific resource. Uses a consistent format
     * to enable targeted cache invalidation.
     *
     * @param  Subject  $subject  The subject to include in the cache key
     * @param  resource $resource The resource to include in the cache key
     * @return string   The formatted cache key (e.g., 'patrol:policies:user:123:document:456')
     */
    private function getCacheKey(Subject $subject, Resource $resource): string
    {
        return sprintf('patrol:policies:%s:%s', $subject->id, $resource->id);
    }
}
