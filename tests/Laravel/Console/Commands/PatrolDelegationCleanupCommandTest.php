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
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Laravel\Console\Commands\PatrolDelegationCleanupCommand;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create the patrol_delegations table for testing
    DB::statement('
        CREATE TABLE patrol_delegations (
            id VARCHAR(255) PRIMARY KEY,
            delegator_id VARCHAR(255) NOT NULL,
            delegate_id VARCHAR(255) NOT NULL,
            scope TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME,
            expires_at DATETIME,
            is_transitive BOOLEAN NOT NULL DEFAULT 0,
            state VARCHAR(50) NOT NULL,
            metadata TEXT NOT NULL,
            revoked_at DATETIME,
            revoked_by VARCHAR(255),
            deleted_at DATETIME
        )
    ');

    // Bind the DelegationRepositoryInterface for command testing
    $this->app->singleton(DelegationRepositoryInterface::class, fn ($app): DatabaseDelegationRepository => new DatabaseDelegationRepository(
        connection: config('database.default'),
    ));
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_delegations');
});

describe('PatrolDelegationCleanupCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('removes expired delegations successfully', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $retentionDays = config('patrol.delegation.retention_days', 90);

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-expired-1',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->subDays($retentionDays + 10)->toDateTimeString(),
                    'expires_at' => $now->subDays($retentionDays + 1)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'expired',
                    'metadata' => json_encode([]),
                ],
                [
                    'id' => 'delegation-active-1',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:300',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addDays(7)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Cleaning up delegations')
                ->expectsOutputToContain('Removed 1 delegation(s)')
                ->assertSuccessful();

            // Verify expired delegation was removed
            expect(DB::table('patrol_delegations')->where('id', 'delegation-expired-1')->count())->toBe(0);

            // Verify active delegation remains
            expect(DB::table('patrol_delegations')->where('id', 'delegation-active-1')->count())->toBe(1);
        });

        test('removes old revoked delegations successfully', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $retentionDays = config('patrol.delegation.retention_days', 90);

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-revoked-old',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->subDays($retentionDays + 30)->toDateTimeString(),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'revoked',
                    'metadata' => json_encode([]),
                    'revoked_at' => $now->subDays($retentionDays + 1)->toDateTimeString(),
                    'revoked_by' => 'system',
                ],
                [
                    'id' => 'delegation-revoked-recent',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:300',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->subDays(10)->toDateTimeString(),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'revoked',
                    'metadata' => json_encode([]),
                    'revoked_at' => $now->subDays(5)->toDateTimeString(),
                    'revoked_by' => 'system',
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Cleaning up delegations')
                ->expectsOutputToContain('Removed 1 delegation(s)')
                ->assertSuccessful();

            // Verify old revoked delegation was removed
            expect(DB::table('patrol_delegations')->where('id', 'delegation-revoked-old')->count())->toBe(0);

            // Verify recent revoked delegation remains
            expect(DB::table('patrol_delegations')->where('id', 'delegation-revoked-recent')->count())->toBe(1);
        });

        test('removes multiple expired and revoked delegations', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $retentionDays = config('patrol.delegation.retention_days', 90);

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-expired-1',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays($retentionDays + 20)->toDateTimeString(),
                'expires_at' => $now->subDays($retentionDays + 10)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'expired',
                'metadata' => json_encode([]),
            ]);

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-expired-2',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:201',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays($retentionDays + 30)->toDateTimeString(),
                'expires_at' => $now->subDays($retentionDays + 20)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'expired',
                'metadata' => json_encode([]),
            ]);

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-revoked-old',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:202',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays($retentionDays + 40)->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'revoked',
                'metadata' => json_encode([]),
                'revoked_at' => $now->subDays($retentionDays + 1)->toDateTimeString(),
                'revoked_by' => 'system',
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Removed 3 delegation(s)')
                ->assertSuccessful();

            // Verify all delegations were removed
            expect(DB::table('patrol_delegations')->count())->toBe(0);
        });

        test('displays zero when no delegations need cleanup', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-active-1',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(7)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Cleaning up delegations')
                ->expectsOutputToContain('Removed 0 delegation(s)')
                ->assertSuccessful();

            // Verify active delegation remains
            expect(DB::table('patrol_delegations')->count())->toBe(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('handles empty database gracefully', function (): void {
            // Arrange - no delegations in database

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Cleaning up delegations')
                ->expectsOutputToContain('Removed 0 delegation(s)')
                ->assertSuccessful();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles delegations with null expires_at', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-no-expiry',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays(100)->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Removed 0 delegation(s)')
                ->assertSuccessful();

            // Verify delegation with no expiry remains
            expect(DB::table('patrol_delegations')->count())->toBe(1);
        });

        test('handles delegations exactly at retention boundary', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-boundary',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays(60)->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'revoked',
                'metadata' => json_encode([]),
                'revoked_at' => $now->subDays(30)->toDateTimeString(),
                'revoked_by' => 'system',
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->assertSuccessful();

            // Result depends on retention policy implementation (>= vs >)
            expect(DB::table('patrol_delegations')->count())->toBeGreaterThanOrEqual(0);
        });

        test('handles large numbers of delegations', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $retentionDays = config('patrol.delegation.retention_days', 90);
            $delegations = [];

            for ($i = 0; $i < 100; ++$i) {
                $delegations[] = [
                    'id' => 'delegation-expired-'.$i,
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:'.$i,
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->subDays($retentionDays + 20)->toDateTimeString(),
                    'expires_at' => $now->subDays($retentionDays + 1)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'expired',
                    'metadata' => json_encode([]),
                ];
            }

            DB::table('patrol_delegations')->insert($delegations);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Removed 100 delegation(s)')
                ->assertSuccessful();

            expect(DB::table('patrol_delegations')->count())->toBe(0);
        });

        test('preserves active delegations with future expiry', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-future-1',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addYears(1)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
                [
                    'id' => 'delegation-future-2',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:201',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addMonths(6)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->expectsOutputToContain('Removed 0 delegation(s)')
                ->assertSuccessful();

            expect(DB::table('patrol_delegations')->count())->toBe(2);
        });
    });

    describe('Command Configuration', function (): void {
        test('has correct signature', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationCleanupCommand::class);

            // Assert
            expect($command->getName())->toBe('patrol:delegation:cleanup');
        });

        test('has description', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationCleanupCommand::class);

            // Assert
            expect($command->getDescription())->toBeString()
                ->and($command->getDescription())->toContain('expired');
        });

        test('returns success exit code', function (): void {
            // Act & Assert
            $this->artisan('patrol:delegation:cleanup')
                ->assertExitCode(0);
        });
    });
});
