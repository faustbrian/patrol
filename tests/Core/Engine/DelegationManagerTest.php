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
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

beforeEach(function (): void {
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->policyRepository = Mockery::mock(PolicyRepositoryInterface::class);

    // Use real PolicyEvaluator and DelegationValidator (they're final)
    $policyEvaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new EffectResolver(),
    );

    $this->validator = new DelegationValidator(
        $this->policyRepository,
        $policyEvaluator,
        $this->delegationRepository,
    );

    $this->manager = new DelegationManager(
        $this->delegationRepository,
        $this->validator,
        $this->policyRepository,
    );
});

afterEach(function (): void {
    Mockery::close();
});

describe('DelegationManager', function (): void {
    describe('Happy Paths', function (): void {
        test('creates valid delegation', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Use full wildcards for simplicity

            $expiresAt = CarbonImmutable::now()->modify('+7 days');

            // Mock policy repository to return policy allowing delegator permissions
            // Validator will skip wildcards, so we don't need specific policies
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            // Mock no existing delegations for cycle detection
            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => $delegation->delegatorId === $delegator->id
                    && $delegation->delegateId === $delegate->id
                    && $delegation->status === DelegationState::Active);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
                expiresAt: $expiresAt,
            );

            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->delegatorId)->toBe('user:alice');
            expect($delegation->delegateId)->toBe('user:bob');
            expect($delegation->status)->toBe(DelegationState::Active);
        });

        test('creates transitive delegation', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Use full wildcards

            // Mock for wildcard validation (skipped)
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => $delegation->isTransitive);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
                transitive: true,
            );

            expect($delegation->isTransitive)->toBeTrue();
        });

        test('creates delegation with metadata', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Use full wildcards
            $metadata = ['reason' => 'Vacation coverage'];

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => $delegation->metadata === $metadata);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
                metadata: $metadata,
            );

            expect($delegation->metadata)->toBe($metadata);
        });

        test('revokes delegation', function (): void {
            $revoker = subject('user:alice');

            $this->delegationRepository->shouldReceive('revoke')
                ->once()
                ->with('del-123');

            $this->manager->revoke('del-123', $revoker);

            expect(true)->toBeTrue(); // Verify no exception thrown
        });

        test('finds active delegations for delegate', function (): void {
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['document:*'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation]);

            $delegations = $this->manager->findActiveDelegations($delegate);

            expect($delegations)->toHaveCount(1);
            expect($delegations[0])->toBe($delegation);
        });

        test('canDelegate returns true when delegator has permissions', function (): void {
            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            // Mock policy allowing delegator to have the permissions
            $policy = new Policy([
                new PolicyRule('user:alice', 'document:123', 'read', Effect::Allow),
            ]);

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn($policy);

            expect($this->manager->canDelegate($delegator, $scope))->toBeTrue();
        });

        test('converts delegations to policy rules', function (): void {
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['document:*', 'report:*'], ['read', 'edit']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation]);

            $rules = $this->manager->toPolicyRules($delegate);

            // Should create rules for each resource-action combination
            expect($rules)->toHaveCount(4); // 2 resources × 2 actions

            foreach ($rules as $rule) {
                expect($rule->subject)->toBe('user:bob');
                expect($rule->effect)->toBe(Effect::Allow);
                expect($rule->priority)->toBeInstanceOf(Priority::class);
                expect($rule->priority->value)->toBe(50);
            }
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects delegation when validation fails', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['document:123'], ['read']);

            // Delegator does NOT have the permissions they're trying to delegate
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldNotReceive('create');

            expect(fn () => $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
            ))->toThrow(RuntimeException::class, 'Delegation validation failed');
        });

        test('canDelegate returns false when delegator lacks permissions', function (): void {
            $delegator = subject('user:alice');
            $scope = new DelegationScope(['document:123'], ['read']);

            // Delegator lacks the permissions
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn(
                    new Policy([]),
                );

            expect($this->manager->canDelegate($delegator, $scope))->toBeFalse();
        });

        test('findActiveDelegations returns empty array when no delegations exist', function (): void {
            $delegate = subject('user:bob');

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $delegations = $this->manager->findActiveDelegations($delegate);

            expect($delegations)->toBeEmpty();
        });

        test('toPolicyRules returns empty array when no active delegations', function (): void {
            $delegate = subject('user:bob');

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $rules = $this->manager->toPolicyRules($delegate);

            expect($rules)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('generates unique delegation IDs', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Full wildcard

            // Wildcard scopes skip validation
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $ids = [];
            $this->delegationRepository->shouldReceive('create')
                ->twice()
                ->withArgs(function (Delegation $delegation) use (&$ids): bool {
                    $ids[] = $delegation->id;

                    return true;
                });

            $this->manager->delegate($delegator, $delegate, $scope);
            $this->manager->delegate($delegator, $delegate, $scope);

            expect($ids[0])->not->toBe($ids[1]);
        });

        test('converts multiple delegations to policy rules', function (): void {
            $delegate = subject('user:bob');
            $scope1 = new DelegationScope(['document:*'], ['read']);
            $scope2 = new DelegationScope(['report:*'], ['edit']);

            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope1,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user:charlie',
                delegateId: 'user:bob',
                scope: $scope2,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation1, $delegation2]);

            $rules = $this->manager->toPolicyRules($delegate);

            expect($rules)->toHaveCount(2); // 1 from each delegation
        });

        test('handles delegation with wildcard actions', function (): void {
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['document:123'], ['*']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation]);

            $rules = $this->manager->toPolicyRules($delegate);

            expect($rules)->toHaveCount(1);
            expect($rules[0]->action)->toBe('*');
        });

        test('handles delegation with wildcard resources', function (): void {
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation]);

            $rules = $this->manager->toPolicyRules($delegate);

            expect($rules)->toHaveCount(1);
            expect($rules[0]->resource)->toBe('*');
        });

        test('creates delegation without expiration', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Full wildcard

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => !$delegation->expiresAt instanceof DateTimeImmutable);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
            );

            expect($delegation->expiresAt)->toBeNull();
        });

        test('creates non-transitive delegation by default', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Full wildcard

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => $delegation->isTransitive === false);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
            );

            expect($delegation->isTransitive)->toBeFalse();
        });

        test('policy rules use consistent priority', function (): void {
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['document:*'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                status: DelegationState::Active,
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->andReturn([$delegation]);

            $rules = $this->manager->toPolicyRules($delegate);

            expect($rules)->toHaveCount(1);
            expect($rules[0]->priority->value)->toBe(50);
        });

        test('handles delegation with empty metadata by default', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Full wildcard

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (Delegation $delegation): bool => $delegation->metadata === []);

            $delegation = $this->manager->delegate(
                delegator: $delegator,
                delegate: $delegate,
                scope: $scope,
            );

            expect($delegation->metadata)->toBe([]);
        });

        test('delegation ID follows UUID format', function (): void {
            $delegator = subject('user:alice');
            $delegate = subject('user:bob');
            $scope = new DelegationScope(['*'], ['*']); // Full wildcard

            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->with('user:bob')
                ->andReturn([]);

            $generatedId = null;
            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(function (Delegation $delegation) use (&$generatedId): bool {
                    $generatedId = $delegation->id;

                    return true;
                });

            $this->manager->delegate($delegator, $delegate, $scope);

            // Check UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
            expect($generatedId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
        });
    });
});
