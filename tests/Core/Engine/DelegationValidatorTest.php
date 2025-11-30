<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;

beforeEach(function (): void {
    $this->policyRepository = Mockery::mock(PolicyRepositoryInterface::class);
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);

    // Use real PolicyEvaluator (it's final)
    $this->policyEvaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new EffectResolver(),
    );

    $this->validator = new DelegationValidator(
        $this->policyRepository,
        $this->policyEvaluator,
        $this->delegationRepository,
    );
});

afterEach(function (): void {
    Mockery::close();
});

describe('DelegationValidator', function (): void {
    describe('Happy Paths', function (): void {
        test('validates delegation when delegator has required permissions', function (): void {
            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
            );

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            expect($this->validator->validate($delegation, $delegator))->toBeTrue();
        });

        test('validates delegation with null expiration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            expect($validator->validate($delegation, $delegator))->toBeTrue();
        });

        test('validateDelegatorPermissions returns true when delegator has permissions', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            expect($validator->validateDelegatorPermissions($delegator, $scope))->toBeTrue();
        });

        test('detectCycle returns false when no cycle exists', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            expect($validator->detectCycle('user:alice', 'user:bob'))->toBeFalse();
        });

        test('validateExpiration returns true for future expiration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $expiresAt = CarbonImmutable::now()->modify('+7 days');

            expect($validator->validateExpiration($expiresAt))->toBeTrue();
        });

        test('validateExpiration returns true for null expiration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            expect($validator->validateExpiration(null))->toBeTrue();
        });

        test('validates expiration within max duration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
                maxDurationDays: 30,
            );

            $expiresAt = CarbonImmutable::now()->modify('+14 days');

            expect($validator->validateExpiration($expiresAt))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects delegation when delegator lacks required permissions', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
            );

            $policy = new Policy([]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            expect($validator->validate($delegation, $delegator))->toBeFalse();
        });

        test('rejects delegation when cycle is detected', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
            );

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            // Bob already delegated to Alice (creates cycle)
            $existingDelegation = new Delegation(
                id: 'del-existing',
                delegatorId: 'user:alice',
                delegateId: 'user:charlie',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([$existingDelegation]);

            expect($validator->validate($delegation, $delegator))->toBeFalse();
        });

        test('rejects delegation with past expiration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('-1 day'),
            );

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            expect($validator->validate($delegation, $delegator))->toBeFalse();
        });

        test('validateDelegatorPermissions returns false when delegator lacks permissions', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            $policy = new Policy([]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            expect($validator->validateDelegatorPermissions($delegator, $scope))->toBeFalse();
        });

        test('detectCycle returns true when direct cycle exists', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $scope = new DelegationScope(['document:*'], ['read']);

            // Bob has delegation from Alice (creates cycle if Alice delegates to Bob)
            $existingDelegation = new Delegation(
                id: 'del-existing',
                delegatorId: 'user:alice',
                delegateId: 'user:charlie',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([$existingDelegation]);

            expect($validator->detectCycle('user:alice', 'user:bob'))->toBeTrue();
        });

        test('validateExpiration returns false for past timestamp', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $expiresAt = CarbonImmutable::now()->modify('-1 day');

            expect($validator->validateExpiration($expiresAt))->toBeFalse();
        });

        test('rejects expiration exceeding max duration', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
                maxDurationDays: 30,
            );

            $expiresAt = CarbonImmutable::now()->modify('+60 days');

            expect($validator->validateExpiration($expiresAt))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('skips wildcard validation in resource patterns', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['*'], ['read']);

            // Wildcards are skipped, so validation should pass without checking permissions
            $this->policyRepository->shouldNotReceive('getPoliciesFor');

            expect($validator->validateDelegatorPermissions($delegator, $scope))->toBeTrue();
        });

        test('skips wildcard validation in action patterns', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['*']);

            // Wildcards are skipped
            $this->policyRepository->shouldNotReceive('getPoliciesFor');

            expect($validator->validateDelegatorPermissions($delegator, $scope))->toBeTrue();
        });

        test('validates multiple resource-action combinations', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123', 'document:456'], ['read', 'edit']);

            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
                new PolicyRule('user:alice', 'document:123', 'edit', Effect::Allow),
                new PolicyRule('user:alice', 'document:456', 'read', Effect::Allow),
                new PolicyRule('user:alice', 'document:456', 'edit', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->times(4)
                ->andReturn($policy);

            expect($validator->validateDelegatorPermissions($delegator, $scope))->toBeTrue();
        });

        test('detectCycle handles complex delegation chains', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $scope = new DelegationScope(['document:*'], ['read']);

            // Chain: Alice -> Bob -> Charlie -> Alice (cycle)
            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user:charlie',
                delegateId: 'user:alice',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user:alice',
                delegateId: 'user:charlie',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([$delegation2]);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:charlie')
                ->andReturn([$delegation1]);

            expect($validator->detectCycle('user:alice', 'user:bob'))->toBeTrue();
        });

        test('detectCycle ignores non-transitive delegations', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $scope = new DelegationScope(['document:*'], ['read']);

            // Non-transitive delegation shouldn't contribute to cycle
            $delegation = new Delegation(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:charlie',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: false,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([$delegation]);

            expect($validator->detectCycle('user:alice', 'user:bob'))->toBeFalse();
        });

        test('handles expiration at exact max duration boundary', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
                maxDurationDays: 30,
            );

            $expiresAt = CarbonImmutable::now()->modify('+30 days');

            expect($validator->validateExpiration($expiresAt))->toBeTrue();
        });

        test('handles expiration just over max duration boundary', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
                maxDurationDays: 30,
            );

            $expiresAt = CarbonImmutable::now()->modify('+30 days 1 second');

            expect($validator->validateExpiration($expiresAt))->toBeFalse();
        });

        test('handles visited nodes in cycle detection to prevent duplicate processing', function (): void {
            $validator = new DelegationValidator(
                $this->policyRepository,
                $this->policyEvaluator,
                $this->delegationRepository,
            );

            $scope = new DelegationScope(['document:*'], ['read']);

            // Create a diamond pattern where multiple paths converge on the same node:
            // Starting from Bob, we have two delegation chains:
            //   Bob -> Charlie (via delegation from Charlie)
            //   Bob -> Dave (via delegation from Dave)
            // Both Charlie and Dave delegate from Eve, so:
            //   Charlie -> Eve
            //   Dave -> Eve
            // This means when we process Bob's delegates (Charlie and Dave), both will
            // add Eve to the queue. Eve will be processed once and marked visited,
            // then when dequeued the second time, line 180 (continue) will trigger.

            $bobDelegations = [
                new Delegation(
                    id: 'del-1',
                    delegatorId: 'user:charlie',
                    delegateId: 'user:dave',
                    scope: $scope,
                    createdAt: CarbonImmutable::now(),
                    isTransitive: true,
                ),
                new Delegation(
                    id: 'del-2',
                    delegatorId: 'user:dave',
                    delegateId: 'user:charlie',
                    scope: $scope,
                    createdAt: CarbonImmutable::now(),
                    isTransitive: true,
                ),
            ];

            $charlieDelegations = [
                new Delegation(
                    id: 'del-3',
                    delegatorId: 'user:eve',
                    delegateId: 'user:frank',
                    scope: $scope,
                    createdAt: CarbonImmutable::now(),
                    isTransitive: true,
                ),
            ];

            $daveDelegations = [
                new Delegation(
                    id: 'del-4',
                    delegatorId: 'user:eve',
                    delegateId: 'user:frank',
                    scope: $scope,
                    createdAt: CarbonImmutable::now(),
                    isTransitive: true,
                ),
            ];

            $eveDelegations = [];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn($bobDelegations);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:charlie')
                ->andReturn($charlieDelegations);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:dave')
                ->andReturn($daveDelegations);

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:eve')
                ->once() // Eve should only be processed once due to visited check on line 180
                ->andReturn($eveDelegations);

            expect($validator->detectCycle('user:alice', 'user:bob'))->toBeFalse();
        });
    });
});
