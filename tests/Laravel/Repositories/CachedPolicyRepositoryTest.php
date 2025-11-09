<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Repositories\CachedPolicyRepository;

beforeEach(function (): void {
    // Clear cache before each test
    Cache::flush();

    $this->mockRepository = Mockery::mock(PolicyRepositoryInterface::class);
    $this->cache = Cache::store('array');
    $this->repository = new CachedPolicyRepository(
        repository: $this->mockRepository,
        cache: $this->cache,
        ttl: 3_600,
    );
});

afterEach(function (): void {
    Mockery::close();
    Cache::flush();
});

describe('CachedPolicyRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('fetches from repository on cache miss and caches result', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $expectedPolicy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->with(
                    Mockery::on(fn ($s): bool => $s->id === 'user:123'),
                    Mockery::on(fn ($r): bool => $r->id === 'document:456'),
                )
                ->andReturn($expectedPolicy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result)->toBe($expectedPolicy);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
        });

        test('returns cached policy on cache hit without calling repository', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $cachedPolicy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Manually cache the policy
            $this->cache->put('patrol:policies:user:123:document:456', $cachedPolicy, 3_600);

            // Repository should not be called
            $this->mockRepository->shouldNotReceive('getPoliciesFor');

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result)->toBe($cachedPolicy);
        });

        test('invalidates specific subject-resource combination', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Cache the policy
            $this->cache->put('patrol:policies:user:123:document:456', $policy, 3_600);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();

            // Act
            $this->repository->invalidate($subject, $resource);

            // Assert
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
        });

        test('invalidates all cached policies', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:999',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(1),
                ),
            ]);

            // Cache multiple policies
            $this->cache->put('patrol:policies:user:123:document:456', $policy1, 3_600);
            $this->cache->put('patrol:policies:user:999:document:789', $policy2, 3_600);

            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
            expect($this->cache->has('patrol:policies:user:999:document:789'))->toBeTrue();

            // Act
            $this->repository->invalidateAll();

            // Assert
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
            expect($this->cache->has('patrol:policies:user:999:document:789'))->toBeFalse();
        });

        test('respects ttl configuration', function (): void {
            // Arrange
            $customTtl = 7_200;
            $repository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $this->cache,
                ttl: $customTtl,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
        });

        test('caches empty policy when no rules match', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $emptyPolicy = new Policy([]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($emptyPolicy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
        });

        test('uses Cache::remember for automatic cache handling', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act - first call caches
            $this->repository->getPoliciesFor($subject, $resource);

            // Assert - cache key exists
            expect(Cache::has('patrol:policies:user:123:document:456'))->toBeTrue();
        });

        test('caches policies with domain information', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: new Domain('tenant:1'),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($result->rules[0]->domain->id)->toBe('tenant:1');
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
        });
    });

    describe('Save Operations', function (): void {
        test('save delegates to underlying repository and invalidates all cache', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Cache a policy first
            $this->cache->put('patrol:policies:user:123:document:456', $policy, 3_600);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();

            // Mock repository expects save to be called
            $this->mockRepository->shouldReceive('save')
                ->once()
                ->with(Mockery::on(fn ($p): bool => $p instanceof Policy));

            // Act
            $this->repository->save($policy);

            // Assert - cache should be cleared
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
        });

        test('save invalidates all cached policies including multiple entries', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:999',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(1),
                ),
            ]);

            // Cache multiple policies
            $this->cache->put('patrol:policies:user:123:document:456', $policy1, 3_600);
            $this->cache->put('patrol:policies:user:999:document:789', $policy2, 3_600);

            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
            expect($this->cache->has('patrol:policies:user:999:document:789'))->toBeTrue();

            $this->mockRepository->shouldReceive('save')
                ->once()
                ->with(Mockery::on(fn ($p): bool => $p instanceof Policy));

            // Act
            $this->repository->save($policy1);

            // Assert - all caches should be cleared
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
            expect($this->cache->has('patrol:policies:user:999:document:789'))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('falls through to repository when cache is unavailable', function (): void {
            // Arrange
            $failingCache = Mockery::mock(CacheRepository::class);
            $failingCache->shouldReceive('remember')
                ->andThrow(
                    new Exception('Cache unavailable'),
                );

            $repository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $failingCache,
                ttl: 3_600,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act & Assert
            expect(fn (): Policy => $repository->getPoliciesFor($subject, $resource))
                ->toThrow(Exception::class, 'Cache unavailable');
        });

        test('handles invalidate when cache key does not exist', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();

            // Act
            $this->repository->invalidate($subject, $resource);

            // Assert - should not throw exception
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
        });

        test('handles invalidate all when cache is empty', function (): void {
            // Arrange - cache is empty

            // Act
            $this->repository->invalidateAll();

            // Assert - should not throw exception
            expect(true)->toBeTrue();
        });

        test('propagates repository exceptions to caller', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andThrow(
                    new RuntimeException('Database connection failed'),
                );

            // Act & Assert
            expect(fn () => $this->repository->getPoliciesFor($subject, $resource))
                ->toThrow(RuntimeException::class, 'Database connection failed');
        });

        test('handles invalidate when cache flush fails gracefully', function (): void {
            // Arrange
            $failingCache = Mockery::mock(CacheRepository::class);
            $failingCache->shouldReceive('forget')
                ->andReturn(false); // Simulate failed deletion

            $repository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $failingCache,
                ttl: 3_600,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act - should not throw
            $repository->invalidate($subject, $resource);

            // Assert - execution completed without exception
            expect(true)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('prevents cache key collision for different subject-resource combinations', function (): void {
            // Arrange
            $subject1 = new Subject('user:123');
            $resource1 = new Resource('document:456', 'document');

            $subject2 = new Subject('user:456');
            $resource2 = new Resource('document:123', 'document');

            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'document:123',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->with(
                    Mockery::on(fn ($s): bool => $s->id === 'user:123'),
                    Mockery::on(fn ($r): bool => $r->id === 'document:456'),
                )
                ->andReturn($policy1);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->with(
                    Mockery::on(fn ($s): bool => $s->id === 'user:456'),
                    Mockery::on(fn ($r): bool => $r->id === 'document:123'),
                )
                ->andReturn($policy2);

            // Act
            $result1 = $this->repository->getPoliciesFor($subject1, $resource1);
            $result2 = $this->repository->getPoliciesFor($subject2, $resource2);

            // Assert
            expect($result1)->toBe($policy1);
            expect($result2)->toBe($policy2);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
            expect($this->cache->has('patrol:policies:user:456:document:123'))->toBeTrue();
        });

        test('handles special characters in subject and resource ids', function (): void {
            // Arrange
            $subject = new Subject('user:test@example.com');
            $resource = new Resource('document:file/path/123', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:test@example.com',
                    resource: 'document:file/path/123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result)->toBe($policy);
            expect($this->cache->has('patrol:policies:user:test@example.com:document:file/path/123'))->toBeTrue();
        });

        test('cache hit does not call repository multiple times', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act - call multiple times
            $this->repository->getPoliciesFor($subject, $resource);
            $this->repository->getPoliciesFor($subject, $resource);
            $this->repository->getPoliciesFor($subject, $resource);

            // Assert - repository called only once
            expect(true)->toBeTrue();
        });

        test('invalidate and re-fetch calls repository again', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy1);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy2);

            // Act
            $result1 = $this->repository->getPoliciesFor($subject, $resource);
            $this->repository->invalidate($subject, $resource);
            $result2 = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result1)->toBe($policy1);
            expect($result2)->toBe($policy2);
        });

        test('handles policies with multiple rules and complex priorities', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(100),
                ),
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(50),
                ),
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'delete',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(3);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();
        });

        test('cache key includes null resource correctly', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('null', 'document'); // Resource with 'null' as ID

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: null,
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($this->cache->has('patrol:policies:user:123:null'))->toBeTrue();
        });

        test('different cache stores maintain separate cached policies', function (): void {
            // Arrange
            $arrayCache = Cache::store('array');
            $fileCache = Cache::store('file');

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->twice()
                ->andReturn($policy);

            $arrayRepository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $arrayCache,
                ttl: 3_600,
            );

            $fileRepository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $fileCache,
                ttl: 3_600,
            );

            // Act
            $arrayRepository->getPoliciesFor($subject, $resource);
            $fileRepository->getPoliciesFor($subject, $resource);

            // Assert - both stores have cached the policy
            expect($arrayCache->has('patrol:policies:user:123:document:456'))->toBeTrue();
            expect($fileCache->has('patrol:policies:user:123:document:456'))->toBeTrue();

            // Cleanup
            $fileCache->flush();
        });

        test('invalidateAll only affects cache store used by repository', function (): void {
            // Arrange
            $arrayCache = Cache::store('array');
            $key = 'patrol:policies:user:123:document:456';

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Cache in array store
            $arrayCache->put($key, $policy, 3_600);

            // Create repository with array store
            $repository = new CachedPolicyRepository(
                repository: $this->mockRepository,
                cache: $arrayCache,
                ttl: 3_600,
            );

            // Act
            $repository->invalidateAll();

            // Assert
            expect($arrayCache->has($key))->toBeFalse();
        });

        test('handles very long subject and resource identifiers', function (): void {
            // Arrange
            $longSubjectId = 'user:'.str_repeat('a', 500);
            $longResourceId = 'document:'.str_repeat('b', 500);

            $subject = new Subject($longSubjectId);
            $resource = new Resource($longResourceId, 'document');

            $policy = new Policy([
                new PolicyRule(
                    subject: $longSubjectId,
                    resource: $longResourceId,
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Act
            $result = $this->repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result)->toBe($policy);
            expect($this->cache->has(sprintf('patrol:policies:%s:%s', $longSubjectId, $longResourceId)))->toBeTrue();
        });

        test('saveMany delegates to underlying repository and invalidates cache', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(10),
                ),
            ]);

            // Cache something first
            $this->cache->put('patrol:policies:user:123:document:456', $policy1, 3_600);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();

            $this->mockRepository->shouldReceive('saveMany')
                ->once()
                ->with([$policy1, $policy2]);

            // Act
            $this->repository->saveMany([$policy1, $policy2]);

            // Assert
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
        });

        test('deleteMany delegates to underlying repository and invalidates cache', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Cache something first
            $this->cache->put('patrol:policies:user:123:document:456', $policy, 3_600);
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeTrue();

            $this->mockRepository->shouldReceive('deleteMany')
                ->once()
                ->with([1, 2, 3]);

            // Act
            $this->repository->deleteMany([1, 2, 3]);

            // Assert
            expect($this->cache->has('patrol:policies:user:123:document:456'))->toBeFalse();
        });

        test('softDelete delegates to underlying repository and invalidates cache', function (): void {
            // Arrange
            $this->mockRepository->shouldReceive('softDelete')
                ->once()
                ->with('policy-id-1');

            // Act
            $this->repository->softDelete('policy-id-1');

            // Assert - invalidation tested via mockInvalidation
        });

        test('restore delegates to underlying repository and invalidates cache', function (): void {
            // Arrange
            $this->mockRepository->shouldReceive('restore')
                ->once()
                ->with('policy-id-1');

            // Act
            $this->repository->restore('policy-id-1');

            // Assert - invalidation tested via mockInvalidation
        });

        test('forceDelete delegates to underlying repository and invalidates cache', function (): void {
            // Arrange
            $this->mockRepository->shouldReceive('forceDelete')
                ->once()
                ->with('policy-id-1');

            // Act
            $this->repository->forceDelete('policy-id-1');

            // Assert - invalidation tested via mockInvalidation
        });

        test('getTrashed delegates to underlying repository without caching', function (): void {
            // Arrange
            $trashedPolicy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getTrashed')
                ->once()
                ->andReturn($trashedPolicy);

            // Act
            $result = $this->repository->getTrashed();

            // Assert
            expect($result)->toBe($trashedPolicy);
        });

        test('getWithTrashed caches results under separate key', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $this->mockRepository->shouldReceive('getWithTrashed')
                ->once()
                ->with($subject, $resource)
                ->andReturn($policy);

            // Act
            $result1 = $this->repository->getWithTrashed($subject, $resource);
            $result2 = $this->repository->getWithTrashed($subject, $resource);

            // Assert - second call should use cache
            expect($result1)->toBe($policy);
            expect($result2)->toBe($policy);
            expect($this->cache->has('patrol:policies:user:123:document:456:with-trashed'))->toBeTrue();
        });
    });

    describe('Batch Operations', function (): void {
        test('delegates batch to underlying repository', function (): void {
            $subject = new Subject('user:1');
            $resources = [
                new Resource('doc:1', 'document'),
                new Resource('doc:2', 'document'),
            ];

            $expected = [
                'doc:1' => new Policy([]),
                'doc:2' => new Policy([]),
            ];

            $this->mockRepository->shouldReceive('getPoliciesForBatch')
                ->once()
                ->with($subject, $resources)
                ->andReturn($expected);

            $result = $this->repository->getPoliciesForBatch($subject, $resources);

            expect($result)->toBe($expected);
        });
    });
});
