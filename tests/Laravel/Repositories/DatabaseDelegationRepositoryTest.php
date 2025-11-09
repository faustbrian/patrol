<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create the delegations table for testing
    DB::statement('
        CREATE TABLE patrol_delegations (
            id VARCHAR(255) PRIMARY KEY,
            delegator_id VARCHAR(255) NOT NULL,
            delegate_id VARCHAR(255) NOT NULL,
            scope TEXT NOT NULL,
            created_at DATETIME,
            updated_at DATETIME,
            expires_at DATETIME,
            is_transitive BOOLEAN NOT NULL,
            state VARCHAR(50) NOT NULL,
            metadata TEXT,
            revoked_at DATETIME,
            revoked_by VARCHAR(255),
            deleted_at DATETIME
        )
    ');

    $this->repository = new DatabaseDelegationRepository(connection: 'testing');
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_delegations');
});

describe('DatabaseDelegationRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('creates delegation in database', function (): void {
            $scope = new DelegationScope(['document:*'], ['read', 'edit']);
            $createdAt = CarbonImmutable::parse('2024-01-01 10:00:00');
            $expiresAt = CarbonImmutable::parse('2024-01-08 10:00:00');

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: $createdAt,
                expiresAt: $expiresAt,
                isTransitive: true,
                status: DelegationState::Active,
                metadata: ['reason' => 'Vacation coverage'],
            );

            $this->repository->create($delegation);

            $row = DB::table('patrol_delegations')->where('id', 'del-123')->first();

            expect($row)->not->toBeNull();
            expect($row->id)->toBe('del-123');
            expect($row->delegator_id)->toBe('user:alice');
            expect($row->delegate_id)->toBe('user:bob');
            expect($row->is_transitive)->toBe(1); // Database returns integer for boolean
            expect($row->state)->toBe('active');
        });

        test('retrieves delegation by ID', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-123',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode([
                    'resources' => ['document:*'],
                    'actions' => ['read'],
                    'domain' => null,
                ]),
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => '2024-01-08 10:00:00',
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            $delegation = $this->repository->findById('del-123');

            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->id)->toBe('del-123');
            expect($delegation->delegatorId)->toBe('user:alice');
            expect($delegation->delegateId)->toBe('user:bob');
            expect($delegation->scope->resources)->toBe(['document:*']);
            expect($delegation->scope->actions)->toBe(['read']);
            expect($delegation->isTransitive)->toBeFalse();
            expect($delegation->status)->toBe(DelegationState::Active);
        });

        test('finds active delegations for delegate', function (): void {
            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'del-1',
                    'delegator_id' => 'user:alice',
                    'delegate_id' => 'user:bob',
                    'scope' => json_encode([
                        'resources' => ['document:*'],
                        'actions' => ['read'],
                        'domain' => null,
                    ]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
                [
                    'id' => 'del-2',
                    'delegator_id' => 'user:charlie',
                    'delegate_id' => 'user:bob',
                    'scope' => json_encode([
                        'resources' => ['report:*'],
                        'actions' => ['edit'],
                        'domain' => null,
                    ]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toHaveCount(2);
            expect($delegations[0]->id)->toBe('del-1');
            expect($delegations[1]->id)->toBe('del-2');
        });

        test('revokes delegation', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-123',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode([
                    'resources' => ['document:*'],
                    'actions' => ['read'],
                    'domain' => null,
                ]),
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            $this->repository->revoke('del-123');

            $row = DB::table('patrol_delegations')->where('id', 'del-123')->first();

            expect($row->state)->toBe('revoked');
            expect($row->revoked_at)->not->toBeNull();
        });

        test('cleanup removes old expired delegations', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-old',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                'created_at' => now()->subDays(200)->format('Y-m-d H:i:s'),
                'expires_at' => now()->subDays(150)->format('Y-m-d H:i:s'),
                'is_transitive' => false,
                'state' => 'expired',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            config(['patrol.delegation.retention_days' => 90]);

            $count = $this->repository->cleanup();

            expect($count)->toBe(1);
            expect(DB::table('patrol_delegations')->where('id', 'del-old')->exists())->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null when delegation not found', function (): void {
            $delegation = $this->repository->findById('non-existent');

            expect($delegation)->toBeNull();
        });

        test('returns empty array when no active delegations exist', function (): void {
            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toBeEmpty();
        });

        test('excludes revoked delegations from active query', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-revoked',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode([
                    'resources' => ['document:*'],
                    'actions' => ['read'],
                    'domain' => null,
                ]),
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'revoked',
                'metadata' => json_encode([]),
                'revoked_at' => '2024-01-02 10:00:00',
                'revoked_by' => null,
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toBeEmpty();
        });

        test('excludes expired delegations from active query', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-expired',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode([
                    'resources' => ['document:*'],
                    'actions' => ['read'],
                    'domain' => null,
                ]),
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => now()->subDays(1)->format('Y-m-d H:i:s'),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles delegation with null expiration', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->repository->create($delegation);

            $row = DB::table('patrol_delegations')->where('id', 'del-123')->first();

            expect($row->expires_at)->toBeNull();
        });

        test('handles delegation with domain in scope', function (): void {
            $scope = new DelegationScope(['document:*'], ['read'], 'tenant-1');
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
            );

            $this->repository->create($delegation);

            $retrieved = $this->repository->findById('del-123');

            expect($retrieved->scope->domain)->toBe('tenant-1');
        });

        test('handles delegation with complex metadata', function (): void {
            $metadata = [
                'reason' => 'Vacation coverage',
                'project' => 'Alpha',
                'nested' => ['key' => 'value'],
            ];

            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                metadata: $metadata,
            );

            $this->repository->create($delegation);

            $retrieved = $this->repository->findById('del-123');

            expect($retrieved->metadata)->toBe($metadata);
        });

        test('cleanup preserves recent expired delegations', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-recent',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                'created_at' => now()->subDays(10)->format('Y-m-d H:i:s'),
                'expires_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'is_transitive' => false,
                'state' => 'expired',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            config(['patrol.delegation.retention_days' => 90]);

            $count = $this->repository->cleanup();

            expect($count)->toBe(0);
            expect(DB::table('patrol_delegations')->where('id', 'del-recent')->exists())->toBeTrue();
        });

        test('cleanup removes old revoked delegations', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-old-revoked',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                'created_at' => now()->subDays(200)->format('Y-m-d H:i:s'),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'revoked',
                'metadata' => json_encode([]),
                'revoked_at' => now()->subDays(150)->format('Y-m-d H:i:s'),
                'revoked_by' => null,
            ]);

            config(['patrol.delegation.retention_days' => 90]);

            $count = $this->repository->cleanup();

            expect($count)->toBe(1);
        });

        test('finds active delegations with null expiration', function (): void {
            DB::table('patrol_delegations')->insert([
                'id' => 'del-permanent',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => json_encode([
                    'resources' => ['document:*'],
                    'actions' => ['read'],
                    'domain' => null,
                ]),
                'created_at' => '2024-01-01 10:00:00',
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
                'revoked_at' => null,
                'revoked_by' => null,
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toHaveCount(1);
            expect($delegations[0]->expiresAt)->toBeNull();
        });

        test('handles multiple delegations for same delegate from different delegators', function (): void {
            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'del-1',
                    'delegator_id' => 'user:alice',
                    'delegate_id' => 'user:bob',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
                [
                    'id' => 'del-2',
                    'delegator_id' => 'user:charlie',
                    'delegate_id' => 'user:bob',
                    'scope' => json_encode(['resources' => ['report:*'], 'actions' => ['edit'], 'domain' => null]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toHaveCount(2);
        });

        test('handles transitive delegation flag', function (): void {
            $scope = new DelegationScope(['document:*'], ['read']);
            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: $scope,
                createdAt: CarbonImmutable::now(),
                isTransitive: true,
            );

            $this->repository->create($delegation);

            $retrieved = $this->repository->findById('del-123');

            expect($retrieved->isTransitive)->toBeTrue();
        });

        test('filters delegations by delegate ID correctly', function (): void {
            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'del-bob',
                    'delegator_id' => 'user:alice',
                    'delegate_id' => 'user:bob',
                    'scope' => json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
                [
                    'id' => 'del-charlie',
                    'delegator_id' => 'user:alice',
                    'delegate_id' => 'user:charlie',
                    'scope' => json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                    'revoked_at' => null,
                    'revoked_by' => null,
                ],
            ]);

            $delegations = $this->repository->findActiveForDelegate('user:bob');

            expect($delegations)->toHaveCount(1);
            expect($delegations[0]->delegateId)->toBe('user:bob');
        });

        test('handles null created_at timestamp gracefully', function (): void {
            // Insert raw data with null created_at to trigger fallback
            DB::statement('
                INSERT INTO patrol_delegations (
                    id, delegator_id, delegate_id, scope, created_at,
                    expires_at, is_transitive, state, metadata, revoked_at, revoked_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                'del-null-created',
                'user:alice',
                'user:bob',
                json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                null,
                null,
                false,
                'active',
                json_encode([]),
                null,
                null,
            ]);

            $delegation = $this->repository->findById('del-null-created');

            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->createdAt)->toBeInstanceOf(DateTimeImmutable::class);
        });

        test('handles null metadata gracefully', function (): void {
            // Insert raw data with null metadata to trigger fallback
            DB::statement('
                INSERT INTO patrol_delegations (
                    id, delegator_id, delegate_id, scope, created_at,
                    expires_at, is_transitive, state, metadata, revoked_at, revoked_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                'del-null-metadata',
                'user:alice',
                'user:bob',
                json_encode(['resources' => ['*'], 'actions' => ['*'], 'domain' => null]),
                '2024-01-01 10:00:00',
                null,
                false,
                'active',
                null,
                null,
                null,
            ]);

            $delegation = $this->repository->findById('del-null-metadata');

            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->metadata)->toBe([]);
        });
    });
});
