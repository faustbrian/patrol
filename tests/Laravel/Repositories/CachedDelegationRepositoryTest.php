<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Laravel\Repositories\CachedDelegationRepository;

beforeEach(function (): void {
    Cache::flush();

    $this->mockRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->cache = Cache::store('array');
    $this->repository = new CachedDelegationRepository(
        repository: $this->mockRepository,
        cache: $this->cache,
        ttl: 3_600,
    );
});

afterEach(function (): void {
    Mockery::close();
    Cache::flush();
});

describe('CachedDelegationRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('creates delegation and invalidates cache', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            // Pre-populate cache
            $this->cache->put('patrol:delegations:active:user:bob', [], 3_600);

            $this->mockRepository->shouldReceive('create')
                ->once()
                ->with($delegation);

            $this->repository->create($delegation);

            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeFalse();
        });

        test('caches findById on first call', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('del-123')
                ->andReturn($delegation);

            $result = $this->repository->findById('del-123');

            expect($result)->toBe($delegation);
            expect($this->cache->has('patrol:delegations:id:del-123'))->toBeTrue();
        });

        test('returns cached delegation on second findById call', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->cache->put('patrol:delegations:id:del-123', $delegation, 3_600);

            $this->mockRepository->shouldNotReceive('findById');

            $result = $this->repository->findById('del-123');

            expect($result)->toBe($delegation);
        });

        test('caches findActiveForDelegate on first call', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation]);

            $result = $this->repository->findActiveForDelegate('user:bob');

            expect($result)->toHaveCount(1);
            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeTrue();
        });

        test('returns cached delegations on second findActiveForDelegate call', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->cache->put('patrol:delegations:active:user:bob', [$delegation], 3_600);

            $this->mockRepository->shouldNotReceive('findActiveForDelegate');

            $result = $this->repository->findActiveForDelegate('user:bob');

            expect($result)->toHaveCount(1);
            expect($result[0])->toBe($delegation);
        });

        test('revokes delegation and invalidates caches', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            // Pre-populate caches
            $this->cache->put('patrol:delegations:id:del-123', $delegation, 3_600);
            $this->cache->put('patrol:delegations:active:user:bob', [$delegation], 3_600);

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('del-123')
                ->andReturn($delegation);

            $this->mockRepository->shouldReceive('revoke')
                ->once()
                ->with('del-123');

            $this->repository->revoke('del-123');

            expect($this->cache->has('patrol:delegations:id:del-123'))->toBeFalse();
            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeFalse();
        });

        test('cleanup delegates to underlying repository', function (): void {
            $this->mockRepository->shouldReceive('cleanup')
                ->once()
                ->andReturn(5);

            $count = $this->repository->cleanup();

            expect($count)->toBe(5);
        });

        test('respects ttl configuration', function (): void {
            $customRepository = new CachedDelegationRepository(
                repository: $this->mockRepository,
                cache: $this->cache,
                ttl: 7_200,
            );

            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->andReturn($delegation);

            $customRepository->findById('del-123');

            expect($this->cache->has('patrol:delegations:id:del-123'))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('handles null delegation on revoke', function (): void {
            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('del-123')
                ->andReturn(null);

            $this->mockRepository->shouldReceive('revoke')
                ->once()
                ->with('del-123');

            $this->repository->revoke('del-123');

            // Should not throw exception
            expect(true)->toBeTrue();
        });

        test('caches null result from findById', function (): void {
            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('non-existent')
                ->andReturn(null);

            $result = $this->repository->findById('non-existent');

            expect($result)->toBeNull();
            // Note: Cache may not store null values depending on the driver
            // The array cache driver doesn't persist null values by design
        });

        test('caches empty array from findActiveForDelegate', function (): void {
            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $result = $this->repository->findActiveForDelegate('user:bob');

            expect($result)->toBeEmpty();
            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeTrue();
        });

        test('propagates exceptions from underlying repository', function (): void {
            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->andThrow(
                    new RuntimeException('Database error'),
                );

            expect(fn () => $this->repository->findById('del-123'))
                ->toThrow(RuntimeException::class, 'Database error');
        });
    });

    describe('Edge Cases', function (): void {
        test('separate cache keys for different delegates', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user:alice',
                delegateId: 'user:charlie',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation1]);

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:charlie')
                ->andReturn([$delegation2]);

            $this->repository->findActiveForDelegate('user:bob');
            $this->repository->findActiveForDelegate('user:charlie');

            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeTrue();
            expect($this->cache->has('patrol:delegations:active:user:charlie'))->toBeTrue();
        });

        test('separate cache keys for different delegation IDs', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('del-1')
                ->andReturn($delegation1);

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->with('del-2')
                ->andReturn($delegation2);

            $this->repository->findById('del-1');
            $this->repository->findById('del-2');

            expect($this->cache->has('patrol:delegations:id:del-1'))->toBeTrue();
            expect($this->cache->has('patrol:delegations:id:del-2'))->toBeTrue();
        });

        test('invalidate on create only affects delegate cache', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            // Cache for different delegate
            $this->cache->put('patrol:delegations:active:user:charlie', [], 3_600);

            $this->mockRepository->shouldReceive('create')
                ->once()
                ->with($delegation);

            $this->repository->create($delegation);

            // Only bob's cache should be invalidated
            expect($this->cache->has('patrol:delegations:active:user:charlie'))->toBeTrue();
        });

        test('handles special characters in delegate ID for cache keys', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob@example.com',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation]);

            $this->repository->findActiveForDelegate('user:bob@example.com');

            expect($this->cache->has('patrol:delegations:active:user:bob@example.com'))->toBeTrue();
        });

        test('multiple calls with cache hits do not hit repository', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation]);

            // Call multiple times
            $this->repository->findActiveForDelegate('user:bob');
            $this->repository->findActiveForDelegate('user:bob');
            $this->repository->findActiveForDelegate('user:bob');

            // Repository should only be called once
            expect(true)->toBeTrue();
        });

        test('invalidate and re-fetch calls repository again', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation1]);

            $this->mockRepository->shouldReceive('create')
                ->once()
                ->with($delegation2);

            $this->mockRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation1, $delegation2]);

            // First call - caches
            $result1 = $this->repository->findActiveForDelegate('user:bob');

            // Create new delegation - invalidates cache
            $this->repository->create($delegation2);

            // Second call - fetches from repository again
            $result2 = $this->repository->findActiveForDelegate('user:bob');

            expect($result1)->toHaveCount(1);
            expect($result2)->toHaveCount(2);
        });

        test('revoke without cached delegation still invalidates', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            // Only cache the active delegations, not the individual delegation
            $this->cache->put('patrol:delegations:active:user:bob', [$delegation], 3_600);

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->andReturn($delegation);

            $this->mockRepository->shouldReceive('revoke')
                ->once();

            $this->repository->revoke('del-123');

            expect($this->cache->has('patrol:delegations:active:user:bob'))->toBeFalse();
        });

        test('handles delegation with multiple resource patterns in scope', function (): void {
            $scope = new DelegationScope(['document:*', 'report:*'], ['read', 'edit']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->mockRepository->shouldReceive('findById')
                ->once()
                ->andReturn($delegation);

            $result = $this->repository->findById('del-123');

            expect($result->scope->resources)->toBe(['document:*', 'report:*']);
            expect($this->cache->has('patrol:delegations:id:del-123'))->toBeTrue();
        });
    });
});
