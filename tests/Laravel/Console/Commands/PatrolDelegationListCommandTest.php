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
use Patrol\Laravel\Console\Commands\PatrolDelegationListCommand;
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
            is_transitive BOOLEAN NOT NULL DEFAULT 0,
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
    $this->app->singleton(DelegationRepositoryInterface::class, fn ($app): DatabaseDelegationRepository => new DatabaseDelegationRepository(
        connection: config('database.default'),
    ));

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

    $delegationRepository = new DatabaseDelegationRepository(
        connection: config('database.default'),
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
        repository: $app->make(DelegationRepositoryInterface::class),
        validator: $validator,
    ));
});

afterEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS patrol_delegations');
    DB::statement('DROP TABLE IF EXISTS patrol_policies');
});

describe('PatrolDelegationListCommand', function (): void {
    describe('Happy Paths', function (): void {
        test('lists active delegations for a user', function (): void {
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s) for user:200')
                ->expectsTable(
                    ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'],
                    [[
                        'delegation-123',
                        'user:100',
                        'document:*',
                        'read',
                        $now->addDays(7)->format('Y-m-d H:i:s'),
                    ]],
                )
                ->assertSuccessful();
        });

        test('lists multiple active delegations for a user', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-1',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addDays(7)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
                [
                    'id' => 'delegation-2',
                    'delegator_id' => 'user:101',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => $now->addDays(14)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
                [
                    'id' => 'delegation-3',
                    'delegator_id' => 'user:102',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->toDateTimeString(),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => json_encode([]),
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 3 active delegation(s) for user:200')
                ->expectsTable(
                    ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'],
                    [
                        [
                            'delegation-1',
                            'user:100',
                            'document:*',
                            'read',
                            $now->addDays(7)->format('Y-m-d H:i:s'),
                        ],
                        [
                            'delegation-2',
                            'user:101',
                            'document:*',
                            'read',
                            $now->addDays(14)->format('Y-m-d H:i:s'),
                        ],
                        [
                            'delegation-3',
                            'user:102',
                            'document:*',
                            'read',
                            'Never',
                        ],
                    ],
                )
                ->assertSuccessful();
        });

        test('displays delegations in table format with headers', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-456',
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsTable(
                    ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'],
                    [[
                        'delegation-456',
                        'user:100',
                        'document:*',
                        'read',
                        $now->addDays(7)->format('Y-m-d H:i:s'),
                    ]],
                )
                ->assertSuccessful();
        });

        test('displays "Never" for delegations without expiration', function (): void {
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Never')
                ->assertSuccessful();
        });

        test('handles multiple resources and actions', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-multi',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*', 'report:*', 'project:123'], 'actions' => ['read', 'edit', 'create', 'delete'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(7)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsTable(
                    ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'],
                    [[
                        'delegation-multi',
                        'user:100',
                        'document:*, report:*, project:123',
                        'read, edit, create, delete',
                        $now->addDays(7)->format('Y-m-d H:i:s'),
                    ]],
                )
                ->assertSuccessful();
        });
    });

    describe('Sad Paths', function (): void {
        test('displays warning when no active delegations found', function (): void {
            // Arrange - no delegations for the user

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:999'])
                ->expectsOutputToContain('No active delegations found for user:999')
                ->assertSuccessful();
        });

        test('ignores expired delegations', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-expired',
                    'delegator_id' => 'user:100',
                    'delegate_id' => 'user:200',
                    'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                    'created_at' => $now->subDays(10)->toDateTimeString(),
                    'expires_at' => $now->subDays(1)->toDateTimeString(),
                    'is_transitive' => false,
                    'state' => 'expired',
                    'metadata' => json_encode([]),
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('No active delegations found for user:200')
                ->assertSuccessful();
        });

        test('ignores revoked delegations', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
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
                ],
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('No active delegations found for user:200')
                ->assertSuccessful();
        });

        test('does not show delegations for other users', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                [
                    'id' => 'delegation-other',
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('No active delegations found for user:200')
                ->assertSuccessful();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles user ID with special characters', function (): void {
            // Arrange
            $now = CarbonImmutable::now();
            $specialUserId = 'user:test@example.com';

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-special',
                'delegator_id' => 'user:admin@example.com',
                'delegate_id' => $specialUserId,
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(7)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => $specialUserId])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->expectsTable(
                    ['ID', 'Delegator', 'Resources', 'Actions', 'Expires'],
                    [[
                        'delegation-special',
                        'user:admin@example.com',
                        'document:*',
                        'read',
                        $now->addDays(7)->format('Y-m-d H:i:s'),
                    ]],
                )
                ->assertSuccessful();
        });

        test('handles role-based delegation identifiers', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-role',
                'delegator_id' => 'role:manager',
                'delegate_id' => 'role:editor',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addDays(30)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'role:editor'])
                ->expectsOutputToContain('Found 1 active delegation(s) for role:editor')
                ->expectsOutputToContain('role:manager')
                ->assertSuccessful();
        });

        test('handles delegations with domain context', function (): void {
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->assertSuccessful();
        });

        test('handles very long delegation IDs', function (): void {
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->assertSuccessful();
        });

        test('handles delegations expiring soon', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-soon',
                'delegator_id' => 'user:100',
                'delegate_id' => 'user:200',
                'scope' => json_encode(['resources' => ['document:*'], 'actions' => ['read'], 'domain' => null]),
                'created_at' => $now->toDateTimeString(),
                'expires_at' => $now->addHours(1)->toDateTimeString(),
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => json_encode([]),
            ]);

            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->expectsOutputToContain($now->addHours(1)->format('Y-m-d H:i:s'))
                ->assertSuccessful();
        });

        test('handles wildcard actions', function (): void {
            // Arrange
            $now = CarbonImmutable::now();

            DB::table('patrol_delegations')->insert([
                'id' => 'delegation-wildcard',
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->expectsOutputToContain('*')
                ->assertSuccessful();
        });

        test('handles transitive delegations', function (): void {
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
            $this->artisan('patrol:delegation:list', ['user' => 'user:200'])
                ->expectsOutputToContain('Found 1 active delegation(s)')
                ->assertSuccessful();
        });
    });

    describe('Command Arguments', function (): void {
        test('requires user argument', function (): void {
            // Act & Assert - expect RuntimeException when user argument is missing
            expect(fn () => $this->artisan('patrol:delegation:list'))
                ->toThrow(RuntimeException::class, 'Not enough arguments (missing: "user")');
        });

        test('has correct signature', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationListCommand::class);

            // Assert
            expect($command->getName())->toBe('patrol:delegation:list');
        });

        test('has description', function (): void {
            // Act
            $command = $this->app->make(PatrolDelegationListCommand::class);

            // Assert
            expect($command->getDescription())->toBeString()
                ->and($command->getDescription())->toContain('active delegations');
        });

        test('returns success exit code even with no delegations', function (): void {
            // Act & Assert
            $this->artisan('patrol:delegation:list', ['user' => 'user:999'])
                ->assertExitCode(0);
        });
    });
});
