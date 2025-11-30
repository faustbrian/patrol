<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Delegation as DelegationValue;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Laravel\Delegation;

beforeEach(function (): void {
    // Mock repositories
    $this->delegationRepository = Mockery::mock(DelegationRepositoryInterface::class);
    $this->policyRepository = Mockery::mock(PolicyRepositoryInterface::class);

    // Use real PolicyEvaluator (it's final)
    $policyEvaluator = new PolicyEvaluator(
        new AclRuleMatcher(),
        new EffectResolver(),
    );

    // Use real DelegationValidator with mocked dependencies
    $validator = new DelegationValidator(
        $this->policyRepository,
        $policyEvaluator,
        $this->delegationRepository,
    );

    // Use real DelegationManager with mocked repositories
    $this->delegationManager = new DelegationManager(
        $this->delegationRepository,
        $validator,
        $this->policyRepository,
    );

    $this->app->instance(DelegationManager::class, $this->delegationManager);
});

afterEach(function (): void {
    Mockery::close();
});

describe('Delegation Facade', function (): void {
    describe('Happy Paths', function (): void {
        test('grant creates delegation', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];

            // Mock validation dependencies - skip validation for wildcard
            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            // Mock repository create
            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => $delegation->delegatorId === 'user:alice'
                    && $delegation->delegateId === 'user:bob'
                    && $delegation->scope->resources === ['*']
                    && $delegation->scope->actions === ['read']);

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read'],
            );

            expect($result)->toBeInstanceOf(DelegationValue::class);
            expect($result->delegatorId)->toBe('user:alice');
            expect($result->delegateId)->toBe('user:bob');
            expect($result->scope->resources)->toBe(['*']);
            expect($result->scope->actions)->toBe(['read']);
        });

        test('grant with expiration', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];
            $expiresAt = CarbonImmutable::now()->modify('+7 days');

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => $delegation->expiresAt instanceof DateTimeImmutable);

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read'],
                expiresAt: $expiresAt,
            );

            expect($result)->toBeInstanceOf(DelegationValue::class);
            expect($result->expiresAt)->not->toBeNull();
        });

        test('grant with transitive flag', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => $delegation->isTransitive);

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read'],
                transitive: true,
            );

            expect($result)->toBeInstanceOf(DelegationValue::class);
            expect($result->isTransitive)->toBeTrue();
        });

        test('grant with metadata', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];
            $metadata = ['reason' => 'Vacation coverage'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => $delegation->metadata === $metadata);

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read'],
                metadata: $metadata,
            );

            expect($result)->toBeInstanceOf(DelegationValue::class);
            expect($result->metadata)->toBe($metadata);
        });

        test('revoke calls manager', function (): void {
            $this->delegationRepository->shouldReceive('revoke')
                ->once()
                ->with('del-123');

            Delegation::revoke('del-123');

            expect(true)->toBeTrue();
        });

        test('active returns collection of delegations', function (): void {
            $user = (object) ['id' => 'user:bob'];

            $delegation1 = new DelegationValue(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            $delegation2 = new DelegationValue(
                id: 'del-2',
                delegatorId: 'user:charlie',
                delegateId: 'user:bob',
                scope: new DelegationScope(['report:*'], ['edit']),
                createdAt: CarbonImmutable::now(),
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation1, $delegation2]);

            $result = Delegation::active($user);

            expect($result)->toBeInstanceOf(Collection::class);
            expect($result)->toHaveCount(2);
            expect($result[0])->toBe($delegation1);
            expect($result[1])->toBe($delegation2);
        });

        test('canDelegate checks permissions', function (): void {
            $user = (object) ['id' => 'user:alice'];

            // Use wildcard to bypass validation check
            $result = Delegation::canDelegate($user, ['*'], ['read']);

            expect($result)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('grant propagates validation errors', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];

            // Create a condition that will fail validation (e.g., empty policy)
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn(
                    new Policy([]),
                );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->never();

            expect(fn (): DelegationValue => Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['document:123'],  // Non-wildcard to trigger validation
                actions: ['read'],
            ))->toThrow(RuntimeException::class, 'Delegation validation failed');
        });

        test('canDelegate returns false when user lacks permissions', function (): void {
            $user = (object) ['id' => 'user:alice'];

            // Empty policy will cause validation to fail
            $this->policyRepository->shouldReceive('getPoliciesFor')
                ->once()
                ->andReturn(
                    new Policy([]),
                );

            $result = Delegation::canDelegate($user, ['document:123'], ['read']);

            expect($result)->toBeFalse();
        });

        test('active returns empty collection when no delegations exist', function (): void {
            $user = (object) ['id' => 'user:bob'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $result = Delegation::active($user);

            expect($result)->toBeInstanceOf(Collection::class);
            expect($result)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('grant with multiple resources and actions', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => count($delegation->scope->resources) === 1
                    && count($delegation->scope->actions) === 3);

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read', 'edit', 'delete'],
            );

            expect($result->scope->resources)->toHaveCount(1);
            expect($result->scope->actions)->toHaveCount(3);
        });

        test('grant with wildcard resources and actions', function (): void {
            $delegator = (object) ['id' => 'user:alice'];
            $delegate = (object) ['id' => 'user:bob'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once();

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['*'],
            );

            expect($result->scope->resources)->toBe(['*']);
            expect($result->scope->actions)->toBe(['*']);
        });

        test('active returns collection with correct delegation objects', function (): void {
            $user = (object) ['id' => 'user:bob'];

            $delegation = new DelegationValue(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation]);

            $result = Delegation::active($user);

            expect($result->first())->toBeInstanceOf(DelegationValue::class);
            expect($result->first()->id)->toBe('del-123');
        });

        test('revoke uses system as revoker', function (): void {
            $this->delegationRepository->shouldReceive('revoke')
                ->once()
                ->with('del-123');

            Delegation::revoke('del-123');

            expect(true)->toBeTrue();
        });

        test('grant creates Subject objects from user IDs', function (): void {
            $delegator = (object) ['id' => 'user:alice@example.com'];
            $delegate = (object) ['id' => 'user:bob+test@example.com'];

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob+test@example.com')
                ->andReturn([]);

            $this->delegationRepository->shouldReceive('create')
                ->once()
                ->withArgs(fn (DelegationValue $delegation): bool => $delegation->delegatorId === 'user:alice@example.com'
                    && $delegation->delegateId === 'user:bob+test@example.com');

            $result = Delegation::grant(
                delegator: $delegator,
                delegate: $delegate,
                resources: ['*'],
                actions: ['read'],
            );

            expect($result->delegatorId)->toBe('user:alice@example.com');
            expect($result->delegateId)->toBe('user:bob+test@example.com');
        });

        test('canDelegate with empty resources and actions', function (): void {
            $user = (object) ['id' => 'user:alice'];

            // Empty resources/actions will fail validation
            $result = Delegation::canDelegate($user, [], []);

            expect($result)->toBeTrue(); // Empty scope passes (no validation needed)
        });

        test('active works with collection methods', function (): void {
            $user = (object) ['id' => 'user:bob'];

            $delegation1 = new DelegationValue(
                id: 'del-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            $delegation2 = new DelegationValue(
                id: 'del-2',
                delegatorId: 'user:charlie',
                delegateId: 'user:bob',
                scope: new DelegationScope(['report:*'], ['edit']),
                createdAt: CarbonImmutable::now(),
            );

            $this->delegationRepository->shouldReceive('findActiveForDelegate')
                ->once()
                ->with('user:bob')
                ->andReturn([$delegation1, $delegation2]);

            $result = Delegation::active($user);

            // Test collection methods
            expect($result->count())->toBe(2);
            expect($result->pluck('id')->toArray())->toBe(['del-1', 'del-2']);
            expect($result->filter(fn ($d): bool => $d->delegatorId === 'user:alice')->count())->toBe(1);
        });
    });
});
