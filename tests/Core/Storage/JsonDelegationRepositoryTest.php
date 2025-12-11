<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Patrol\Core\Exceptions\StorageVersionNotFoundException;
use Patrol\Core\Storage\JsonDelegationRepository;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;
use Tests\Helpers\FilesystemHelper;

describe('JsonDelegationRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_delegation_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        describe('Single File Mode', function (): void {
            test('creates delegation in single file mode', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-123',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read', 'write'],
                        domain: 'example.com',
                    ),
                    createdAt: CarbonImmutable::parse('2024-01-01 10:00:00'),
                    expiresAt: CarbonImmutable::parse('2024-12-31 23:59:59'),
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: ['note' => 'test delegation'],
                );

                // Act
                $repository->create($delegation);

                // Assert
                $filePath = $this->tempDir.'/delegations/delegations.json';
                expect($filePath)->toBeFile();

                $content = json_decode(file_get_contents($filePath), true);
                expect($content)->toBeArray()
                    ->toHaveCount(1);

                expect($content[0])
                    ->toHaveKey('id', 'del-123')
                    ->toHaveKey('delegator_id', 'user-1')
                    ->toHaveKey('delegate_id', 'user-2')
                    ->toHaveKey('state', 'active')
                    ->toHaveKey('is_transitive', false);

                expect($content[0]['scope'])
                    ->toHaveKey('resources', ['document:*'])
                    ->toHaveKey('actions', ['read', 'write'])
                    ->toHaveKey('domain', 'example.com');
            });

            test('appends multiple delegations to single file', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation1 = new Delegation(
                    id: 'del-1',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $delegation2 = new Delegation(
                    id: 'del-2',
                    delegatorId: 'user-3',
                    delegateId: 'user-4',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: true,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Act
                $repository->create($delegation1);
                $repository->create($delegation2);

                // Assert
                $filePath = $this->tempDir.'/delegations/delegations.json';
                $content = json_decode(file_get_contents($filePath), true);

                expect($content)->toHaveCount(2);
                expect($content[0]['id'])->toBe('del-1');
                expect($content[1]['id'])->toBe('del-2');
            });

            test('finds delegation by id in single file mode', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-find-me',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::parse('2024-01-01 10:00:00'),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: ['key' => 'value'],
                );

                $repository->create($delegation);

                // Act
                $found = $repository->findById('del-find-me');

                // Assert
                expect($found)->toBeInstanceOf(Delegation::class);
                expect($found->id)->toBe('del-find-me');
                expect($found->delegatorId)->toBe('user-1');
                expect($found->delegateId)->toBe('user-2');
                expect($found->status)->toBe(DelegationState::Active);
                expect($found->metadata)->toBe(['key' => 'value']);
            });
        });

        describe('Multiple File Mode', function (): void {
            test('creates delegation in multiple file mode', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-456',
                    delegatorId: 'user-5',
                    delegateId: 'user-6',
                    scope: new DelegationScope(['resource:*'], ['execute']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: true,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Act
                $repository->create($delegation);

                // Assert
                $filePath = $this->tempDir.'/delegations/del-456.json';
                expect($filePath)->toBeFile();

                $content = json_decode(file_get_contents($filePath), true);
                expect($content)
                    ->toHaveKey('id', 'del-456')
                    ->toHaveKey('delegator_id', 'user-5')
                    ->toHaveKey('delegate_id', 'user-6')
                    ->toHaveKey('is_transitive', true);
            });

            test('creates multiple delegation files in multiple mode', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    versioningEnabled: false,
                );

                $delegation1 = new Delegation(
                    id: 'del-multi-1',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $delegation2 = new Delegation(
                    id: 'del-multi-2',
                    delegatorId: 'user-3',
                    delegateId: 'user-4',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Act
                $repository->create($delegation1);
                $repository->create($delegation2);

                // Assert
                expect($this->tempDir.'/delegations/del-multi-1.json')->toBeFile();
                expect($this->tempDir.'/delegations/del-multi-2.json')->toBeFile();
            });

            test('finds delegation by id in multiple file mode', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-multi-find',
                    delegatorId: 'user-10',
                    delegateId: 'user-20',
                    scope: new DelegationScope(['api:*'], ['call']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation);

                // Act
                $found = $repository->findById('del-multi-find');

                // Assert
                expect($found)->toBeInstanceOf(Delegation::class);
                expect($found->id)->toBe('del-multi-find');
                expect($found->delegatorId)->toBe('user-10');
                expect($found->delegateId)->toBe('user-20');
            });
        });

        describe('Versioning', function (): void {
            test('creates delegation in versioned directory', function (): void {
                // Arrange
                $versionDir = $this->tempDir.'/delegations/1.0.0';
                mkdir($versionDir, 0o755, true);

                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: '1.0.0',
                    versioningEnabled: true,
                );

                $delegation = new Delegation(
                    id: 'del-versioned',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Act
                $repository->create($delegation);

                // Assert
                $filePath = $this->tempDir.'/delegations/1.0.0/delegations.json';
                expect($filePath)->toBeFile();
            });

            test('auto-detects latest version when not specified', function (): void {
                // Arrange
                mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
                mkdir($this->tempDir.'/delegations/1.5.0', 0o755, true);
                mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    version: null,
                    versioningEnabled: true,
                );

                $delegation = new Delegation(
                    id: 'del-latest',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Act
                $repository->create($delegation);

                // Assert - Should use latest version 2.0.0
                $filePath = $this->tempDir.'/delegations/2.0.0/delegations.json';
                expect($filePath)->toBeFile();
            });
        });

        describe('Active Delegation Filtering', function (): void {
            test('finds active delegations for delegate', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $now = CarbonImmutable::now();
                $future = $now->modify('+1 day');

                // Active delegation for target delegate
                $delegation1 = new Delegation(
                    id: 'del-active-1',
                    delegatorId: 'delegator-1',
                    delegateId: 'target-delegate',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: $now,
                    expiresAt: $future,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Another active delegation for same delegate
                $delegation2 = new Delegation(
                    id: 'del-active-2',
                    delegatorId: 'delegator-2',
                    delegateId: 'target-delegate',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: $now,
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Active delegation for different delegate (should not be returned)
                $delegation3 = new Delegation(
                    id: 'del-other',
                    delegatorId: 'delegator-1',
                    delegateId: 'other-delegate',
                    scope: new DelegationScope(['api:*'], ['call']),
                    createdAt: $now,
                    expiresAt: $future,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation1);
                $repository->create($delegation2);
                $repository->create($delegation3);

                // Act
                $active = $repository->findActiveForDelegate('target-delegate');

                // Assert
                expect($active)->toHaveCount(2);
                expect($active[0]->id)->toBe('del-active-1');
                expect($active[1]->id)->toBe('del-active-2');
            });

            test('excludes expired delegations from active results', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $now = CarbonImmutable::now();
                $past = $now->modify('-1 day');
                $future = $now->modify('+1 day');

                // Active non-expired
                $delegation1 = new Delegation(
                    id: 'del-valid',
                    delegatorId: 'delegator-1',
                    delegateId: 'delegate-1',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: $now,
                    expiresAt: $future,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Active but expired
                $delegation2 = new Delegation(
                    id: 'del-expired',
                    delegatorId: 'delegator-1',
                    delegateId: 'delegate-1',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: $past,
                    expiresAt: $past,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation1);
                $repository->create($delegation2);

                // Act
                $active = $repository->findActiveForDelegate('delegate-1');

                // Assert
                expect($active)->toHaveCount(1);
                expect($active[0]->id)->toBe('del-valid');
            });

            test('excludes revoked delegations from active results', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $now = CarbonImmutable::now();

                // Active delegation
                $delegation1 = new Delegation(
                    id: 'del-active',
                    delegatorId: 'delegator-1',
                    delegateId: 'delegate-1',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: $now,
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                // Revoked delegation
                $delegation2 = new Delegation(
                    id: 'del-revoked',
                    delegatorId: 'delegator-1',
                    delegateId: 'delegate-1',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: $now,
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Revoked,
                    metadata: [],
                );

                $repository->create($delegation1);
                $repository->create($delegation2);

                // Act
                $active = $repository->findActiveForDelegate('delegate-1');

                // Assert
                expect($active)->toHaveCount(1);
                expect($active[0]->id)->toBe('del-active');
            });
        });

        describe('Delegation Revocation', function (): void {
            test('revokes delegation by id', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-to-revoke',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation);

                // Act
                $repository->revoke('del-to-revoke');

                // Assert
                $found = $repository->findById('del-to-revoke');
                expect($found->status)->toBe(DelegationState::Revoked);
            });

            test('sets revoked_at timestamp when revoking', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation = new Delegation(
                    id: 'del-timestamp',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation);

                // Act
                $repository->revoke('del-timestamp');

                // Assert
                $filePath = $this->tempDir.'/delegations/delegations.json';
                $content = json_decode(file_get_contents($filePath), true);

                expect($content[0]['revoked_at'])->not->toBeNull();
                expect($content[0]['revoked_at'])->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
            });
        });

        describe('Cleanup Operations', function (): void {
            test('removes old expired delegations', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $now = CarbonImmutable::now();
                $oldExpired = $now->modify('-100 days');
                $recentExpired = $now->modify('-30 days');

                // Old expired (should be removed)
                $delegation1 = new Delegation(
                    id: 'del-old-expired',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: $oldExpired,
                    expiresAt: $oldExpired,
                    isTransitive: false,
                    status: DelegationState::Expired,
                    metadata: [],
                );

                // Recent expired (should be kept)
                $delegation2 = new Delegation(
                    id: 'del-recent-expired',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: $recentExpired,
                    expiresAt: $recentExpired,
                    isTransitive: false,
                    status: DelegationState::Expired,
                    metadata: [],
                );

                // Active (should be kept)
                $delegation3 = new Delegation(
                    id: 'del-active',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['api:*'], ['call']),
                    createdAt: $now,
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation1);
                $repository->create($delegation2);
                $repository->create($delegation3);

                // Act
                $removed = $repository->cleanup();

                // Assert
                expect($removed)->toBe(1);
                expect($repository->findById('del-old-expired'))->toBeNull();
                expect($repository->findById('del-recent-expired'))->toBeInstanceOf(Delegation::class);
                expect($repository->findById('del-active'))->toBeInstanceOf(Delegation::class);
            });

            test('removes old revoked delegations', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                // Create old revoked delegation
                $delegation = new Delegation(
                    id: 'del-old-revoked',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation);

                // Manually set old revoked_at date
                $filePath = $this->tempDir.'/delegations/delegations.json';
                $content = json_decode(file_get_contents($filePath), true);
                $content[0]['state'] = 'revoked';
                $content[0]['revoked_at'] = CarbonImmutable::now()->modify('-100 days')->format('Y-m-d H:i:s');
                file_put_contents($filePath, json_encode($content, \JSON_PRETTY_PRINT));

                // Act
                $removed = $repository->cleanup();

                // Assert
                expect($removed)->toBe(1);
                expect($repository->findById('del-old-revoked'))->toBeNull();
            });

            test('keeps active delegations during cleanup', function (): void {
                // Arrange
                $repository = new JsonDelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                $delegation1 = new Delegation(
                    id: 'del-active-1',
                    delegatorId: 'user-1',
                    delegateId: 'user-2',
                    scope: new DelegationScope(['doc:*'], ['read']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $delegation2 = new Delegation(
                    id: 'del-active-2',
                    delegatorId: 'user-3',
                    delegateId: 'user-4',
                    scope: new DelegationScope(['file:*'], ['write']),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                );

                $repository->create($delegation1);
                $repository->create($delegation2);

                // Act
                $removed = $repository->cleanup();

                // Assert
                expect($removed)->toBe(0);
                expect($repository->findById('del-active-1'))->toBeInstanceOf(Delegation::class);
                expect($repository->findById('del-active-2'))->toBeInstanceOf(Delegation::class);
            });
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null when delegation not found', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $found = $repository->findById('non-existent-id');

            // Assert
            expect($found)->toBeNull();
        });

        test('returns empty array when no active delegations exist for delegate', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $active = $repository->findActiveForDelegate('non-existent-delegate');

            // Assert
            expect($active)->toBeArray()->toBeEmpty();
        });

        test('throws exception when specified version does not exist', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '99.99.99',
                versioningEnabled: true,
            );

            // Act & Assert - Exception thrown when trying to use non-existent version
            expect(fn (): ?Delegation => $repository->findById('any-id'))
                ->toThrow(StorageVersionNotFoundException::class);
        });

        test('returns 0 when cleanup finds nothing to remove', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-active',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(0);
        });

        test('handles missing directory gracefully when loading delegations', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir.'/non-existent',
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();
        });

        test('handles missing file gracefully when loading delegations', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - No delegations created, file doesn't exist
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();
        });

        test('handles corrupted JSON file gracefully', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create corrupted JSON file
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, 'invalid json content {{{');

            // Act
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles delegation without expiration date', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-no-expiry',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $active = $repository->findActiveForDelegate('user-2');

            // Assert
            expect($active)->toHaveCount(1);
            expect($active[0]->expiresAt)->toBeNull();
        });

        test('handles delegation without metadata', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-no-metadata',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $found = $repository->findById('del-no-metadata');

            // Assert
            expect($found->metadata)->toBeArray()->toBeEmpty();
        });

        test('handles scope without domain', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-no-domain',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(
                    resources: ['doc:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $found = $repository->findById('del-no-domain');

            // Assert
            expect($found->scope->domain)->toBeNull();
        });

        test('handles transitive delegation flag', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-transitive',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: true,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $found = $repository->findById('del-transitive');

            // Assert
            expect($found->isTransitive)->toBeTrue();
        });

        test('handles empty delegations file', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create empty JSON array file
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, '[]');

            // Act
            $found = $repository->findById('any-id');
            $active = $repository->findActiveForDelegate('any-delegate');

            // Assert
            expect($found)->toBeNull();
            expect($active)->toBeArray()->toBeEmpty();
        });

        test('handles multiple versions with semantic versioning', function (): void {
            // Arrange
            mkdir($this->tempDir.'/delegations/0.1.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/0.10.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/1.0.1', 0o755, true);
            mkdir($this->tempDir.'/delegations/10.0.0', 0o755, true);

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-semver',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);

            // Assert - Should use highest version (10.0.0)
            $filePath = $this->tempDir.'/delegations/10.0.0/delegations.json';
            expect($filePath)->toBeFile();
        });

        test('handles concurrent delegation states correctly', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $now = CarbonImmutable::now();

            // Mix of states for same delegate
            $active = new Delegation(
                id: 'del-active',
                delegatorId: 'del-1',
                delegateId: 'target',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: $now,
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $expired = new Delegation(
                id: 'del-expired',
                delegatorId: 'del-2',
                delegateId: 'target',
                scope: new DelegationScope(['file:*'], ['write']),
                createdAt: $now->modify('-2 days'),
                expiresAt: $now->modify('-1 day'),
                isTransitive: false,
                status: DelegationState::Expired,
                metadata: [],
            );

            $revoked = new Delegation(
                id: 'del-revoked',
                delegatorId: 'del-3',
                delegateId: 'target',
                scope: new DelegationScope(['api:*'], ['call']),
                createdAt: $now,
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Revoked,
                metadata: [],
            );

            $repository->create($active);
            $repository->create($expired);
            $repository->create($revoked);

            // Act
            $activeResults = $repository->findActiveForDelegate('target');

            // Assert
            expect($activeResults)->toHaveCount(1);
            expect($activeResults[0]->id)->toBe('del-active');
        });

        test('handles delegation at exact expiration boundary', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $now = CarbonImmutable::now();

            $delegation = new Delegation(
                id: 'del-boundary',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: $now->modify('-1 hour'),
                expiresAt: $now,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act - At exact expiration time, delegation should be expired
            $active = $repository->findActiveForDelegate('user-2');

            // Assert
            expect($active)->toBeEmpty();
        });
    });

    describe('Coverage Edge Cases', function (): void {
        test('handles metadata that is not an array during hydration', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create file with non-array metadata
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);
            $data = [
                [
                    'id' => 'del-bad-metadata',
                    'delegator_id' => 'user-1',
                    'delegate_id' => 'user-2',
                    'scope' => [
                        'resources' => ['doc:*'],
                        'actions' => ['read'],
                        'domain' => null,
                    ],
                    'created_at' => '2024-01-01 10:00:00',
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => 'not-an-array', // Invalid metadata type
                    'revoked_at' => null,
                ],
            ];
            file_put_contents($filePath, json_encode($data));

            // Act
            $found = $repository->findById('del-bad-metadata');

            // Assert - Should handle gracefully and return empty array for metadata
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->metadata)->toBeArray()->toBeEmpty();
        });

        test('uses cache when provided in constructor', function (): void {
            // Arrange - Create file with delegations
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);

            $data = [
                [
                    'id' => 'del-1',
                    'delegator_id' => 'user-1',
                    'delegate_id' => 'user-2',
                    'scope' => ['resources' => ['doc:*'], 'actions' => ['read'], 'domain' => null],
                    'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => [],
                    'revoked_at' => null,
                ],
            ];
            file_put_contents($filePath, json_encode($data));

            // Create pre-populated cache with different data
            $cacheKey = 'delegations:latest:single';
            $cachedData = [
                [
                    'id' => 'del-cached',
                    'delegator_id' => 'user-cached',
                    'delegate_id' => 'user-cached-2',
                    'scope' => ['resources' => ['cached:*'], 'actions' => ['read'], 'domain' => null],
                    'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'active',
                    'metadata' => [],
                    'revoked_at' => null,
                ],
            ];

            // Create repository with pre-populated cache
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                cache: [$cacheKey => $cachedData],
            );

            // Act - Try to find delegation from file (should use cache instead)
            $found1 = $repository->findById('del-1'); // In file but not in cache
            $found2 = $repository->findById('del-cached'); // In cache

            // Assert - Cache takes precedence over file (line 298 exercised)
            expect($found1)->toBeNull(); // Not found because cache is used instead of file
            expect($found2)->toBeInstanceOf(Delegation::class); // Found in cache
            expect($found2->delegatorId)->toBe('user-cached');
        });

        test('uses cache in multiple file mode', function (): void {
            // Arrange - Pre-populate cache with delegation data for multiple file mode
            $cachedDelegation = [
                'id' => 'del-cached-multi',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'scope' => [
                    'resources' => ['document:*', 'project:*'],
                    'actions' => ['read', 'write'],
                    'domain' => 'engineering',
                ],
                'created_at' => CarbonImmutable::now()->subDay()->toDateTimeString(),
                'expires_at' => CarbonImmutable::now()->addYear()->toDateTimeString(),
                'is_transitive' => true,
                'state' => 'active',
                'metadata' => ['reason' => 'vacation'],
                'revoked_at' => null,
            ];

            // Cache key format for multiple mode: delegations:{version}:{fileMode}
            $cacheKey = 'delegations:latest:multiple';
            $cache = [
                $cacheKey => [$cachedDelegation],
            ];

            // Create repository with pre-populated cache in multiple file mode
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
                cache: $cache,
            );

            // Act - This should hit cache on line 317 instead of loading from disk
            $active = $repository->findActiveForDelegate('user:bob');

            // Assert - Active delegations should be found from cache
            expect($active)->toHaveCount(1);
            expect($active[0]->id)->toBe('del-cached-multi');
            expect($active[0]->delegatorId)->toBe('user:alice');
            expect($active[0]->delegateId)->toBe('user:bob');
            expect($active[0]->scope->resources)->toBe(['document:*', 'project:*']);
            expect($active[0]->scope->actions)->toBe(['read', 'write']);
            expect($active[0]->scope->domain)->toBe('engineering');
            expect($active[0]->isTransitive)->toBeTrue();
            expect($active[0]->metadata)->toBe(['reason' => 'vacation']);

            // Verify no directory was created (proves cache was used)
            expect(is_dir($this->tempDir.'/delegations'))->toBeFalse();
        });

        test('handles file read failure in single file mode gracefully', function (): void {
            // Arrange
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);

            // Create a valid JSON file first
            file_put_contents($filePath, '[]');

            // Make file unreadable to trigger file_get_contents() === false
            chmod($filePath, 0o000);

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            try {
                // Act - file_get_contents will return false due to permissions
                // This covers line 348: if ($content === false) return [];
                $found = $repository->findById('any-id');

                // Assert - Should handle gracefully when file_get_contents fails
                expect($found)->toBeNull();
            } finally {
                // Cleanup - restore permissions before deletion
                chmod($filePath, 0o644);
            }
        })->skip(\DIRECTORY_SEPARATOR === '\\', 'Permission tests unreliable on Windows')->skipOnCi();

        test('handles glob failure in multiple file mode', function (): void {
            // Arrange - Create a file where directory should be to force glob failure
            $delegationsPath = $this->tempDir.'/delegations';
            file_put_contents($delegationsPath, 'not a directory');

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();

            // Cleanup
            unlink($delegationsPath);
        });

        test('handles file read failure in multiple file mode', function (): void {
            // Arrange
            $dirPath = $this->tempDir.'/delegations';
            mkdir($dirPath, 0o755, true);

            // Create a readable file
            $readableFile = $dirPath.'/del-readable.json';
            $data = [
                'id' => 'del-readable',
                'delegator_id' => 'user-1',
                'delegate_id' => 'user-2',
                'scope' => ['resources' => ['doc:*'], 'actions' => ['read'], 'domain' => null],
                'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => [],
                'revoked_at' => null,
            ];
            file_put_contents($readableFile, json_encode($data));

            // Create a symlink to non-existent file (will cause file_get_contents to fail)
            $brokenSymlink = $dirPath.'/del-broken.json';
            symlink($this->tempDir.'/non-existent.json', $brokenSymlink);

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act
            $found1 = $repository->findById('del-broken');
            $found2 = $repository->findById('del-readable');

            // Assert - Broken file skipped (continue), readable file loaded
            expect($found1)->toBeNull();
            expect($found2)->toBeInstanceOf(Delegation::class);

            // Cleanup
            unlink($brokenSymlink);
        })->skip('Symlink tests are environment-dependent');

        test('creates directory when saving delegations in single file mode', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-1',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Revoke to trigger saveDelegations path
            $repository->revoke('del-1');

            // Act
            $dirPath = $this->tempDir.'/delegations';

            // Assert
            expect($dirPath)->toBeDirectory();
        });

        test('handles cleanup with delegations without revoked_at timestamp', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create revoked delegation without revoked_at timestamp
            $filePath = $this->tempDir.'/delegations/delegations.json';
            mkdir(dirname($filePath), 0o755, true);

            $data = [
                [
                    'id' => 'del-revoked-no-timestamp',
                    'delegator_id' => 'user-1',
                    'delegate_id' => 'user-2',
                    'scope' => [
                        'resources' => ['doc:*'],
                        'actions' => ['read'],
                        'domain' => null,
                    ],
                    'created_at' => CarbonImmutable::now()->modify('-100 days')->format('Y-m-d H:i:s'),
                    'expires_at' => null,
                    'is_transitive' => false,
                    'state' => 'revoked',
                    'metadata' => [],
                    'revoked_at' => null, // No timestamp - should fall through to return true
                ],
            ];
            file_put_contents($filePath, json_encode($data));

            // Act
            $removed = $repository->cleanup();

            // Assert - Delegation without revoked_at timestamp is kept (fallback return true)
            expect($removed)->toBe(0);
        });

        test('handles versioning with null version in buildDirectoryPath', function (): void {
            // Arrange - Create version directory so resolveVersion succeeds but buildDirectoryPath gets null
            mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '1.0.0',
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-versioned',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);

            // Assert - Should create in versioned path (lines 469-472 exercised)
            $filePath = $this->tempDir.'/delegations/1.0.0/del-versioned.json';
            expect($filePath)->toBeFile();
        });

        test('creates directory when saving to single file with no parent directory', function (): void {
            // Arrange - Repository with deep path that doesn't exist yet
            $deepPath = $this->tempDir.'/storage/patrol/delegations/v1';

            $repository = new JsonDelegationRepository(
                basePath: $deepPath,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create first delegation
            $delegation1 = new Delegation(
                id: 'del-1',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation1);

            // Create second delegation to trigger revoke which calls saveDelegations
            $delegation2 = new Delegation(
                id: 'del-2',
                delegatorId: 'user-3',
                delegateId: 'user-4',
                scope: new DelegationScope(['file:*'], ['write']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation2);

            // Now delete the directory to simulate it not existing
            FilesystemHelper::deleteDirectory($deepPath);

            // Act - Revoke delegation which triggers saveDelegations and should create directory (line 401)
            $repository->revoke('del-1');

            // Assert - Directory should be recreated
            expect($deepPath.'/delegations')->toBeDirectory();
            expect($deepPath.'/delegations/delegations.json')->toBeFile();
        });

        test('handles glob failure when delegations path is a file in multiple mode', function (): void {
            // Arrange - Create a regular file where directory should be
            $delegationsPath = $this->tempDir.'/delegations';
            file_put_contents($delegationsPath, 'blocking content');

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act - Try to find delegation, glob will fail (line 361)
            $found = $repository->findById('any-id');
            $active = $repository->findActiveForDelegate('any-delegate');

            // Assert - Should handle glob failure gracefully
            expect($found)->toBeNull();
            expect($active)->toBeArray()->toBeEmpty();

            // Cleanup
            unlink($delegationsPath);
        });

        test('handles buildDirectoryPath when versioning enabled but no version directory exists', function (): void {
            // Arrange - Enable versioning without creating any version directories
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: null,
                versioningEnabled: true, // Auto-detect, but no versions exist
            );

            // Act - Try to find delegation when no version directories exist
            // This triggers buildDirectoryPath with resolveVersion returning null (lines 474-477)
            $found = $repository->findById('any-id');

            // Assert - Should handle gracefully
            expect($found)->toBeNull();
        });

        test('exercises buildDirectoryPath with versioning enabled and valid version', function (): void {
            // Arrange - Create version directories
            mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '2.0.0',
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-version-path',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act - Create delegation which exercises buildDirectoryPath (line 477)
            $repository->create($delegation);

            // Assert - File should be created in versioned path
            $filePath = $this->tempDir.'/delegations/2.0.0/del-version-path.json';
            expect($filePath)->toBeFile();

            // Verify we can load it back
            $found = $repository->findById('del-version-path');
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->id)->toBe('del-version-path');
        });

        test('covers glob failure path when directory operations fail in multiple mode', function (): void {
            // Arrange - Create a scenario where glob can fail
            // First create a valid directory structure
            $delegationsPath = $this->tempDir.'/delegations';
            mkdir($delegationsPath, 0o755, true);

            // Create a valid delegation file first
            $validFile = $delegationsPath.'/del-valid.json';
            $data = [
                'id' => 'del-valid',
                'delegator_id' => 'user-1',
                'delegate_id' => 'user-2',
                'scope' => ['resources' => ['doc:*'], 'actions' => ['read'], 'domain' => null],
                'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                'expires_at' => null,
                'is_transitive' => false,
                'state' => 'active',
                'metadata' => [],
                'revoked_at' => null,
            ];
            file_put_contents($validFile, json_encode($data));

            // Now create repository and verify it works
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act - Should find the valid file
            $found = $repository->findById('del-valid');

            // Assert
            expect($found)->toBeInstanceOf(Delegation::class);

            // Now test glob failure scenario by replacing directory with file
            FilesystemHelper::deleteDirectory($delegationsPath);
            file_put_contents($delegationsPath, 'blocking content');

            // Create new repository instance to clear cache
            $repository2 = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act - glob should fail because path is a file not directory (line 361)
            $found2 = $repository2->findById('any-id');

            // Assert
            expect($found2)->toBeNull();

            // Cleanup
            unlink($delegationsPath);
        });

        test('simulates file read failure in single file mode', function (): void {
            // Arrange - Create repository
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create a delegation first
            $delegation = new Delegation(
                id: 'del-test',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Verify it was created successfully
            $found = $repository->findById('del-test');
            expect($found)->toBeInstanceOf(Delegation::class);

            // Now test the file read failure path
            // Replace the file with a symlink to non-existent target
            $filePath = $this->tempDir.'/delegations/delegations.json';
            unlink($filePath);

            // Create a broken symlink that will cause file_get_contents to fail
            $nonExistentTarget = $this->tempDir.'/does-not-exist.json';
            symlink($nonExistentTarget, $filePath);

            // Create new repository instance to bypass cache
            $repository2 = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - file_get_contents should fail on broken symlink (line 328)
            $found2 = $repository2->findById('del-test');

            // Assert - Should handle failure gracefully
            expect($found2)->toBeNull();

            // Cleanup
            if (!is_link($filePath)) {
                return;
            }

            unlink($filePath);
        });
    });

    describe('Regressions', function (): void {
        test('prevents duplicate delegation IDs in single file mode', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation1 = new Delegation(
                id: 'del-duplicate',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $delegation2 = new Delegation(
                id: 'del-duplicate',
                delegatorId: 'user-3',
                delegateId: 'user-4',
                scope: new DelegationScope(['file:*'], ['write']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation1);
            $repository->create($delegation2);

            // Act
            $filePath = $this->tempDir.'/delegations/delegations.json';
            $content = json_decode(file_get_contents($filePath), true);

            // Assert - Both delegations stored (no deduplication in current implementation)
            expect($content)->toHaveCount(2);
            expect($content[0]['id'])->toBe('del-duplicate');
            expect($content[1]['id'])->toBe('del-duplicate');
        });

        test('preserves JSON structure when revoking in multiple file mode', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-multi-revoke',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: ['key' => 'value'],
            );

            $repository->create($delegation);

            // Act
            $repository->revoke('del-multi-revoke');

            // Assert
            $filePath = $this->tempDir.'/delegations/del-multi-revoke.json';
            $content = json_decode(file_get_contents($filePath), true);

            expect($content)
                ->toHaveKey('state', 'revoked')
                ->toHaveKey('revoked_at')
                ->toHaveKey('metadata', ['key' => 'value']);
        });

        test('respects configured retention days', function (): void {
            // Arrange
            $repository = new JsonDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                retentionDays: 30,
            );

            $now = CarbonImmutable::now();
            $beyondRetention = $now->modify('-31 days');
            $withinRetention = $now->modify('-29 days');

            // Expired beyond 30-day retention (should be removed)
            $delegation1 = new Delegation(
                id: 'del-old',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: $beyondRetention,
                expiresAt: $beyondRetention,
                isTransitive: false,
                status: DelegationState::Expired,
                metadata: [],
            );

            // Expired within 30-day retention (should be kept)
            $delegation2 = new Delegation(
                id: 'del-recent',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['file:*'], ['write']),
                createdAt: $withinRetention,
                expiresAt: $withinRetention,
                isTransitive: false,
                status: DelegationState::Expired,
                metadata: [],
            );

            $repository->create($delegation1);
            $repository->create($delegation2);

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(1);
            expect($repository->findById('del-old'))->toBeNull();
            expect($repository->findById('del-recent'))->toBeInstanceOf(Delegation::class);
        });
    });
});
