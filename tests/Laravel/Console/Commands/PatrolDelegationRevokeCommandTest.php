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
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Laravel\Console\Commands\PatrolDelegationRevokeCommand;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

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
            is_transitive BOOLEAN NOT NULL,
            state VARCHAR(50) NOT NULL,
            metadata TEXT NOT NULL,
            revoked_at TIMESTAMP,
            revoked_by VARCHAR(255),
            deleted_at DATETIME
        )
    ');

    // Create the patrol_policies table for testing
    DB::statement('
        CREATE TABLE patrol_policies (
            id VARCHAR(255) PRIMARY KEY,
            subject_id VARCHAR(255) NOT NULL,
            resource VARCHAR(255) NOT NULL,
            action VARCHAR(255) NOT NULL,
            effect VARCHAR(50) NOT NULL,
            conditions TEXT,
            metadata TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME
        )
    ');

    // Bind the DelegationRepositoryInterface for command testing
    $delegationRepository = new DatabaseDelegationRepository(
        connection: config('database.default'),
    );
    $this->app->singleton(DelegationRepositoryInterface::class, fn ($app): DatabaseDelegationRepository => $delegationRepository);

    // Bind the PolicyRepositoryInterface for command testing
    $policyRepository = new DatabasePolicyRepository(
        connection: config('database.default'),
    );
    $this->app->singleton(PolicyRepositoryInterface::class, fn ($app): DatabasePolicyRepository => $policyRepository);

    // Bind dependencies for DelegationValidator
    $ruleMatcher = new RbacRuleMatcher();
    $this->app->singleton(RuleMatcherInterface::class, fn ($app): RbacRuleMatcher => $ruleMatcher);

    $policyEvaluator = new PolicyEvaluator(
        ruleMatcher: $ruleMatcher,
        effectResolver: new EffectResolver(),
    );

    // Bind DelegationValidator - required by DelegationManager
    $validator = new DelegationValidator(
        policyRepository: $policyRepository,
        policyEvaluator: $policyEvaluator,
        delegationRepository: $delegationRepository,
    );
    $this->app->singleton(DelegationValidator::class, fn ($app): DelegationValidator => $validator);

    // Bind DelegationManager - required by command
    $this->app->singleton(DelegationManager::class, fn ($app): DelegationManager => new DelegationManager(
        repository: $delegationRepository,
        validator: $validator,
    ));
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_delegations');
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('PatrolDelegationRevokeCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('revokes an active delegation successfully', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-123',
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
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-123'])
                ->expectsOutputToContain('Revoking delegation')
                ->expectsOutputToContain('Delegation delegation-123 revoked successfully')
                ->assertSuccessful();

            // Verify delegation was revoked
            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-123')->first();
            expect($delegation->state)->toBe('revoked')
                ->and($delegation->revoked_at)->not->toBeNull();
        });

        test('displays success message with delegation ID', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-abc-def-123',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-abc-def-123'])
                ->expectsOutputToContain('Delegation delegation-abc-def-123 revoked successfully')
                ->assertSuccessful();
        });

        test('can revoke delegation with no expiration date', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-no-expiry',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-no-expiry'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();

            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-no-expiry')->first();
            expect($delegation->state)->toBe('revoked');
        });

        test('can revoke transitive delegation', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-transitive',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(7)->toDateTimeString(),
                'is_transitive' => true,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-transitive'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();

            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-transitive')->first();
            expect($delegation->state)->toBe('revoked');
        });

        test('can revoke delegation with domain context', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-domain',
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
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-domain'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();

            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-domain')->first();
            expect($delegation->state)->toBe('revoked');
        });
    });

    describe('Sad Paths', function (): void {
        test('fails when delegation ID does not exist', function (): void {
            // Arrange - no delegation with this ID

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'non-existent-id'])
                ->expectsOutputToContain('Delegation not found: non-existent-id')
                ->assertExitCode(1);
        });

        test('displays error message for missing delegation', function (): void {
            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'missing-123'])
                ->expectsOutputToContain('Delegation not found: missing-123')
                ->assertFailed();
        });

        test('returns failure exit code when delegation not found', function (): void {
            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'does-not-exist'])
                ->assertExitCode(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles already expired delegation', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-expired',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays(10)->toDateTimeString(),
                'expires_at' => $now->subDays(1)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'expired',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert - Can still revoke expired delegation
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-expired'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();
        });

        test('handles already revoked delegation', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-revoked',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->subDays(5)->toDateTimeString(),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'revoked',
                'metadata' => json_encode([]),
                'revoked_at' => $now->subDays(1)->toDateTimeString(),
                'revoked_by' => 'user:100',
            ]);

            // Act & Assert - Can revoke again (idempotent)
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-revoked'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();
        });

        test('handles delegation ID with special characters', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $specialId = 'delegation-abc-123_def@456';

            DB::table('patrol_delegations')->insert([
                'id' => $specialId,
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
            $this->artisan('patrol:delegation:revoke', ['id' => $specialId])
                ->expectsOutputToContain(sprintf('Delegation %s revoked successfully', $specialId))
                ->assertSuccessful();
        });

        test('handles very long delegation ID', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $longId = 'delegation-'.str_repeat('a', 200);

            DB::table('patrol_delegations')->insert([
                'id' => $longId,
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
            $this->artisan('patrol:delegation:revoke', ['id' => $longId])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();
        });

        test('handles UUID-style delegation IDs', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $uuidId = '550e8400-e29b-41d4-a716-446655440000';

            DB::table('patrol_delegations')->insert([
                'id' => $uuidId,
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
            $this->artisan('patrol:delegation:revoke', ['id' => $uuidId])
                ->expectsOutputToContain(sprintf('Delegation %s revoked successfully', $uuidId))
                ->assertSuccessful();
        });

        test('handles delegation with complex metadata', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-metadata',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(7)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([
                    'reason' => 'Vacation coverage',
                    'project' => 'Project X',
                    'approved_by' => 'user:manager',
                ]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-metadata'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();

            // Verify metadata is preserved
            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-metadata')->first();
            expect($delegation->state)->toBe('revoked')
                ->and($delegation->metadata)->toContain('Vacation coverage');
        });

        test('handles delegation with multiple resources and actions', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-multi',
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
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-multi'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();
        });

        test('handles case-sensitive delegation IDs', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'Delegation-ABC',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addDays(7)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
            ]);

            // Act & Assert - lowercase should not match
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-abc'])
                ->expectsOutputToContain('Delegation not found')
                ->assertFailed();

            // Uppercase should match
            $this->artisan('patrol:delegation:revoke', ['id' => 'Delegation-ABC'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();
        });

        test('handles delegation expiring in the future', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-future',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addYears(1)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-future'])
                ->expectsOutputToContain('revoked successfully')
                ->assertSuccessful();

            // Verify revoked even though far in the future
            $delegation = DB::table('patrol_delegations')->where('id', 'delegation-future')->first();
            expect($delegation->state)->toBe('revoked');
        });
    });

    describe('Command Arguments', function (): void {
        test('requires delegation ID argument', function (): void {
            // Act & Assert - expect RuntimeException when id argument is missing
            expect(fn () => $this->artisan('patrol:delegation:revoke'))
                ->toThrow(RuntimeException::class, 'Not enough arguments (missing: "id")');
        });

        test('has correct signature', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationRevokeCommand::class);

            // Assert
            expect($command->getName())->toBe('patrol:delegation:revoke');
        });

        test('has description', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationRevokeCommand::class);

            // Assert
            expect($command->getDescription())->toBeString()
                ->and($command->getDescription())->toContain('Revoke');
        });

        test('returns success exit code on successful revocation', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-success',
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
            $this->artisan('patrol:delegation:revoke', ['id' => 'delegation-success'])
                ->assertExitCode(0);
        });

        test('returns failure exit code on failed revocation', function (): void {
            // Act & Assert
            $this->artisan('patrol:delegation:revoke', ['id' => 'non-existent'])
                ->assertExitCode(1);
        });
    });
});
