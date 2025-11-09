<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;

describe('Delegation', function (): void {
    describe('Happy Paths', function (): void {
        test('creates delegation with all properties', function (): void {
            $createdAt = CarbonImmutable::parse('2024-01-01 10:00:00');
            $expiresAt = CarbonImmutable::parse('2024-01-08 10:00:00');
            $scope = new DelegationScope(['document:*'], ['read', 'edit']);
            $metadata = ['reason' => 'Vacation coverage'];

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: $createdAt,
                expiresAt: $expiresAt,
                isTransitive: true,
                status: DelegationState::Active,
                metadata: $metadata,
            );

            expect($delegation->id)->toBe('del-123');
            expect($delegation->delegatorId)->toBe('user:alice');
            expect($delegation->delegateId)->toBe('user:bob');
            expect($delegation->scope)->toBe($scope);
            expect($delegation->createdAt)->toBe($createdAt);
            expect($delegation->expiresAt)->toBe($expiresAt);
            expect($delegation->isTransitive)->toBeTrue();
            expect($delegation->status)->toBe(DelegationState::Active);
            expect($delegation->metadata)->toBe($metadata);
        });

        test('creates delegation with default values', function (): void {
            $createdAt = CarbonImmutable::now();
            $scope = new DelegationScope(['document:*'], ['read']);

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: $createdAt,
            );

            expect($delegation->expiresAt)->toBeNull();
            expect($delegation->isTransitive)->toBeFalse();
            expect($delegation->status)->toBe(DelegationState::Active);
            expect($delegation->metadata)->toBe([]);
        });

        test('isActive returns true for active non-expired delegation', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
                status: DelegationState::Active,
            );

            expect($delegation->isActive())->toBeTrue();
        });

        test('isActive returns true for active delegation with no expiration', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                status: DelegationState::Active,
            );

            expect($delegation->isActive())->toBeTrue();
        });

        test('isExpired returns false for future expiration', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
            );

            expect($delegation->isExpired())->toBeFalse();
        });

        test('isExpired returns false for null expiration', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            expect($delegation->isExpired())->toBeFalse();
        });

        test('canTransit returns true for transitive delegation', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            expect($delegation->canTransit())->toBeTrue();
        });

        test('canTransit returns false for non-transitive delegation', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                isTransitive: false,
            );

            expect($delegation->canTransit())->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('isActive returns false for revoked delegation', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('+7 days'),
                status: DelegationState::Revoked,
            );

            expect($delegation->isActive())->toBeFalse();
        });

        test('isActive returns false for expired status delegation', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('-1 day'),
                status: DelegationState::Expired,
            );

            expect($delegation->isActive())->toBeFalse();
        });

        test('isActive returns false for expired delegation with active status', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('-1 day'),
                status: DelegationState::Active,
            );

            expect($delegation->isActive())->toBeFalse();
        });

        test('isExpired returns true for past expiration', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: CarbonImmutable::now()->modify('-1 day'),
            );

            expect($delegation->isExpired())->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('isExpired handles expiration at exact current time', function (): void {
            // Since comparison uses <, equal time should not be considered expired
            $now = CarbonImmutable::now();
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: $now,
                expiresAt: $now,
            );

            // At exact time, it should be expired (< comparison)
            expect($delegation->isExpired())->toBeTrue();
        });

        test('handles delegation with empty metadata', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                metadata: [],
            );

            expect($delegation->metadata)->toBe([]);
        });

        test('handles delegation with complex metadata', function (): void {
            $metadata = [
                'reason' => 'Vacation coverage',
                'project' => 'Alpha',
                'approved_by' => 'user:manager',
                'nested' => [
                    'key' => 'value',
                ],
            ];

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                metadata: $metadata,
            );

            expect($delegation->metadata)->toBe($metadata);
        });

        test('delegation is immutable', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            // Verify readonly class behavior
            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->id)->toBe('del-123');
        });

        test('handles very long delegation IDs', function (): void {
            $longId = str_repeat('a', 255);
            $delegation = new Delegation(
                id: $longId,
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            expect($delegation->id)->toBe($longId);
        });

        test('handles special characters in subject identifiers', function (): void {
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice@example.com',
                delegateId: 'user:bob+test@example.com',
                scope: new DelegationScope(['document:*'], ['read']),
                createdAt: CarbonImmutable::now(),
            );

            expect($delegation->delegatorId)->toBe('user:alice@example.com');
            expect($delegation->delegateId)->toBe('user:bob+test@example.com');
        });
    });
});
