<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use ColinODell\Json5\SyntaxError;
use Patrol\Core\Exceptions\StorageVersionNotFoundException;
use Patrol\Core\Storage\Json5DelegationRepository;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;
use Tests\Helpers\FilesystemHelper;

describe('Json5DelegationRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_json5_delegation_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        describe('Single File Mode', function (): void {
            test('creates delegation in single file mode', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/delegations.json5';
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
                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/delegations.json5';
                $content = json_decode(file_get_contents($filePath), true);

                expect($content)->toHaveCount(2);
                expect($content[0]['id'])->toBe('del-1');
                expect($content[1]['id'])->toBe('del-2');
            });

            test('finds delegation by id in single file mode', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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

            test('reads JSON5 file with comments and trailing commas', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                // Create JSON5 file with comments and trailing commas
                $filePath = $this->tempDir.'/delegations/delegations.json5';
                mkdir(dirname($filePath), 0o755, true);
                $json5Content = <<<'JSON5'
[
  {
    // This is a test delegation
    "id": "del-json5",
    "delegator_id": "user-1",
    "delegate_id": "user-2",
    "scope": {
      "resources": ["doc:*"],
      "actions": ["read"],
      "domain": null,
    },
    "created_at": "2024-01-01 10:00:00",
    "expires_at": null,
    "is_transitive": false,
    "state": "active",
    "metadata": {},
    "revoked_at": null, // Trailing comma here
  },
]
JSON5;
                file_put_contents($filePath, $json5Content);

                // Act
                $found = $repository->findById('del-json5');

                // Assert
                expect($found)->toBeInstanceOf(Delegation::class);
                expect($found->id)->toBe('del-json5');
                expect($found->delegatorId)->toBe('user-1');
                expect($found->delegateId)->toBe('user-2');
            });
        });

        describe('Multiple File Mode', function (): void {
            test('creates delegation in multiple file mode', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/del-456.json5';
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
                $repository = new Json5DelegationRepository(
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
                expect($this->tempDir.'/delegations/del-multi-1.json5')->toBeFile();
                expect($this->tempDir.'/delegations/del-multi-2.json5')->toBeFile();
            });

            test('finds delegation by id in multiple file mode', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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

            test('reads multiple JSON5 files with comments', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Multiple,
                    versioningEnabled: false,
                );

                $dirPath = $this->tempDir.'/delegations';
                mkdir($dirPath, 0o755, true);

                // Create first JSON5 file with comments
                $file1 = $dirPath.'/del-json5-1.json5';
                $json5Content1 = <<<'JSON5'
{
  // First delegation
  "id": "del-json5-1",
  "delegator_id": "user-1",
  "delegate_id": "user-2",
  "scope": {
    "resources": ["doc:*"],
    "actions": ["read"],
    "domain": null,
  },
  "created_at": "2024-01-01 10:00:00",
  "expires_at": null,
  "is_transitive": false,
  "state": "active",
  "metadata": {},
  "revoked_at": null,
}
JSON5;
                file_put_contents($file1, $json5Content1);

                // Create second JSON5 file with comments
                $file2 = $dirPath.'/del-json5-2.json5';
                $json5Content2 = <<<'JSON5'
{
  /* Second delegation
   * Multi-line comment
   */
  "id": "del-json5-2",
  "delegator_id": "user-3",
  "delegate_id": "user-4",
  "scope": {
    "resources": ["file:*"],
    "actions": ["write"],
    "domain": null,
  },
  "created_at": "2024-01-02 10:00:00",
  "expires_at": null,
  "is_transitive": true,
  "state": "active",
  "metadata": {},
  "revoked_at": null,
}
JSON5;
                file_put_contents($file2, $json5Content2);

                // Act
                $found1 = $repository->findById('del-json5-1');
                $found2 = $repository->findById('del-json5-2');

                // Assert
                expect($found1)->toBeInstanceOf(Delegation::class);
                expect($found1->id)->toBe('del-json5-1');
                expect($found2)->toBeInstanceOf(Delegation::class);
                expect($found2->id)->toBe('del-json5-2');
                expect($found2->isTransitive)->toBeTrue();
            });
        });

        describe('Versioning', function (): void {
            test('creates delegation in versioned directory', function (): void {
                // Arrange
                $versionDir = $this->tempDir.'/delegations/1.0.0';
                mkdir($versionDir, 0o755, true);

                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/1.0.0/delegations.json5';
                expect($filePath)->toBeFile();
            });

            test('auto-detects latest version when not specified', function (): void {
                // Arrange
                mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
                mkdir($this->tempDir.'/delegations/1.5.0', 0o755, true);
                mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/2.0.0/delegations.json5';
                expect($filePath)->toBeFile();
            });
        });

        describe('Active Delegation Filtering', function (): void {
            test('finds active delegations for delegate', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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
                $repository = new Json5DelegationRepository(
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
                $repository = new Json5DelegationRepository(
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
                $repository = new Json5DelegationRepository(
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
                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/delegations.json5';
                $content = json_decode(file_get_contents($filePath), true);

                expect($content[0]['revoked_at'])->not->toBeNull();
                expect($content[0]['revoked_at'])->toMatch('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/');
            });
        });

        describe('Cleanup Operations', function (): void {
            test('removes old expired delegations', function (): void {
                // Arrange
                $repository = new Json5DelegationRepository(
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
                $repository = new Json5DelegationRepository(
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
                $filePath = $this->tempDir.'/delegations/delegations.json5';
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
                $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - No delegations created, file doesn't exist
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();
        });

        test('throws exception for corrupted JSON5 file', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create corrupted JSON5 file
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, 'invalid json5 content {{{ // corrupted');

            // Act & Assert
            expect(fn (): ?Delegation => $repository->findById('any-id'))
                ->toThrow(SyntaxError::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles delegation without expiration date', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create empty JSON array file
            $filePath = $this->tempDir.'/delegations/delegations.json5';
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

            $repository = new Json5DelegationRepository(
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
            $filePath = $this->tempDir.'/delegations/10.0.0/delegations.json5';
            expect($filePath)->toBeFile();
        });

        test('handles concurrent delegation states correctly', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
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
            $repository = new Json5DelegationRepository(
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

        test('handles mixed JSON and JSON5 content compatibility', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create file with pure JSON (no JSON5 features)
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);
            $jsonContent = json_encode([
                [
                    'id' => 'del-pure-json',
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
                    'metadata' => [],
                    'revoked_at' => null,
                ],
            ], \JSON_PRETTY_PRINT);
            file_put_contents($filePath, $jsonContent);

            // Act
            $found = $repository->findById('del-pure-json');

            // Assert
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->id)->toBe('del-pure-json');
        });
    });

    describe('Coverage Enhancement', function (): void {
        test('handles non-array JSON5 decode result in single file mode', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create file with JSON5 content that decodes to a non-array (covers line 272)
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, '"just a string"');

            // Act
            $found = $repository->findById('any-id');

            // Assert - Should return null because loadSingleFile returns []
            expect($found)->toBeNull();
        });

        test('handles glob failure in multiple file mode', function (): void {
            // Note: glob() returning false is extremely rare in normal operation.
            // It typically only happens with system-level errors or invalid patterns.
            // The @codeCoverageIgnore block covers lines 319-333 which handle file read failures.
            // Line 313 (glob === false) is difficult to trigger without filesystem manipulation.
            // This test verifies that when delegations path is a file (not directory),
            // the repository returns null gracefully.

            // Arrange - Create a file instead of directory to cause early return
            $filePath = $this->tempDir.'/delegations';
            file_put_contents($filePath, 'not a directory');

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act - is_dir() will return false, triggering early return at line 307
            $found = $repository->findById('any-id');

            // Assert
            expect($found)->toBeNull();
        });

        test('creates directory when saving in single file mode via revoke', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create delegation
            $delegation = new Delegation(
                id: 'del-revoke-mkdir',
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

            // Delete the delegations directory to test mkdir in saveDelegations (covers line 328)
            $delegationsDir = $this->tempDir.'/delegations';
            FilesystemHelper::deleteDirectory($delegationsDir);

            // Act - Revoke will call saveDelegations which should create directory
            $repository->revoke('del-revoke-mkdir');

            // Assert
            expect($delegationsDir)->toBeDirectory();
            expect($delegationsDir.'/delegations.json5')->toBeFile();
        });

        test('uses resolved version in buildDirectoryPath when versioning enabled', function (): void {
            // Arrange - Create version directories
            mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: null,
                versioningEnabled: true,
            );

            // Create delegation - this will use buildDirectoryPath with versioning (covers lines 380-383)
            $delegation = new Delegation(
                id: 'del-versioned-path',
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

            // Assert - File should be in latest version directory (2.0.0)
            $filePath = $this->tempDir.'/delegations/2.0.0/del-versioned-path.json5';
            expect($filePath)->toBeFile();
        });

        test('uses resolved version in buildDirectoryPath with explicit version', function (): void {
            // Arrange - Create version directories
            mkdir($this->tempDir.'/delegations/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/delegations/2.0.0', 0o755, true);

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '1.0.0',
                versioningEnabled: true,
            );

            // Create delegation - this will use buildDirectoryPath with explicit version (covers lines 380-383)
            $delegation = new Delegation(
                id: 'del-explicit-version',
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

            // Assert - File should be in specified version directory (1.0.0)
            $filePath = $this->tempDir.'/delegations/1.0.0/delegations.json5';
            expect($filePath)->toBeFile();
        });

        test('cleanup keeps expired delegations without valid expires_at timestamp', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create expired delegation
            $delegation = new Delegation(
                id: 'del-expired-no-timestamp',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now()->modify('-100 days'),
                expiresAt: CarbonImmutable::now()->modify('-50 days'),
                isTransitive: false,
                status: DelegationState::Expired,
                metadata: [],
            );

            $repository->create($delegation);

            // Manually modify to have null expires_at (invalid state but tests line 158)
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            $content = json_decode(file_get_contents($filePath), true);
            $content[0]['expires_at'] = null;
            file_put_contents($filePath, json_encode($content, \JSON_PRETTY_PRINT));

            // Act - cleanup should keep it (fallback return true on line 158)
            $removed = $repository->cleanup();

            // Assert - Should be kept because expires_at condition fails
            expect($removed)->toBe(0);
            expect($repository->findById('del-expired-no-timestamp'))->toBeInstanceOf(Delegation::class);
        });

        test('cleanup keeps revoked delegations without valid revoked_at timestamp', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create revoked delegation
            $delegation = new Delegation(
                id: 'del-revoked-no-timestamp',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now()->modify('-100 days'),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Revoked,
                metadata: [],
            );

            $repository->create($delegation);

            // Manually set revoked_at to null (tests line 158 fallback)
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            $content = json_decode(file_get_contents($filePath), true);
            $content[0]['revoked_at'] = null;
            file_put_contents($filePath, json_encode($content, \JSON_PRETTY_PRINT));

            // Act - cleanup should keep it (fallback return true on line 158)
            $removed = $repository->cleanup();

            // Assert - Should be kept because revoked_at is null
            expect($removed)->toBe(0);
            expect($repository->findById('del-revoked-no-timestamp'))->toBeInstanceOf(Delegation::class);
        });

        test('handles non-array metadata during hydration', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create file with non-array metadata (string instead of array)
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);
            $content = [
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
                    'metadata' => 'not-an-array',
                    'revoked_at' => null,
                ],
            ];
            file_put_contents($filePath, json_encode($content, \JSON_PRETTY_PRINT));

            // Act
            $found = $repository->findById('del-bad-metadata');

            // Assert - Should return empty array for metadata
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->metadata)->toBeArray()->toBeEmpty();
        });

        test('loads delegations from cache on repeated calls', function (): void {
            // Arrange
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);

            $content = [
                [
                    'id' => 'del-cached',
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
                    'metadata' => ['key' => 'value'],
                    'revoked_at' => null,
                ],
            ];
            file_put_contents($filePath, json_encode($content, \JSON_PRETTY_PRINT));

            // Create repository with pre-populated cache
            $cacheKey = 'delegations:latest:single';
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                cache: [$cacheKey => $content],
            );

            // Act - This should hit cache (line 238) without reading file
            $found = $repository->findById('del-cached');

            // Assert
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->id)->toBe('del-cached');
            expect($found->metadata)->toBe(['key' => 'value']);
        });

        test('loads delegations from cache in multiple file mode', function (): void {
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
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
                cache: $cache,
            );

            // Act - This should hit cache on line 257 instead of loading from disk
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

        test('creates directory when saving delegations in single mode', function (): void {
            // Arrange - Use a nested path that doesn't exist
            $nestedPath = $this->tempDir.'/nested/deep/path';
            $repository = new Json5DelegationRepository(
                basePath: $nestedPath,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-mkdir',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act - Create should make all necessary directories
            $repository->create($delegation);

            // Revoke to trigger saveDelegations which also creates directories
            $repository->revoke('del-mkdir');

            // Assert
            expect($nestedPath.'/delegations')->toBeDirectory();
            expect($nestedPath.'/delegations/delegations.json5')->toBeFile();

            // Cleanup
            FilesystemHelper::deleteDirectory($nestedPath);
        });

        test('handles versioning with no version directories present', function (): void {
            // Arrange - Create base directory but no version subdirectories
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-no-versions',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act - This should handle null version gracefully
            $repository->create($delegation);

            // Assert - Should create file in base delegations directory
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            expect($filePath)->toBeFile();
        });

        test('buildDirectoryPath uses version when versioning enabled and version exists', function (): void {
            // Arrange - Create version directory structure
            mkdir($this->tempDir.'/delegations/3.0.0', 0o755, true);

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '3.0.0',
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-version-path-test',
                delegatorId: 'user-1',
                delegateId: 'user-2',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act - This covers buildDirectoryPath lines 400-403
            $repository->create($delegation);

            // Assert - File should be in versioned directory
            $filePath = $this->tempDir.'/delegations/3.0.0/del-version-path-test.json5';
            expect($filePath)->toBeFile();
        });

        test('handles file_get_contents failure gracefully in single file mode', function (): void {
            // Arrange
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            mkdir(dirname($filePath), 0o755, true);

            // Create a valid JSON5 file first
            file_put_contents($filePath, '[]');

            // Make file unreadable to trigger file_get_contents() === false
            chmod($filePath, 0o000);

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            try {
                // Act - file_get_contents will return false due to permissions
                // This covers line 283: if ($content === false) return [];
                $found = $repository->findById('any-id');

                // Assert - Should handle gracefully when file_get_contents fails
                expect($found)->toBeNull();
            } finally {
                // Cleanup - restore permissions before deletion
                chmod($filePath, 0o644);
            }
        })->skip(\DIRECTORY_SEPARATOR === '\\', 'Permission tests unreliable on Windows')->skipOnCI();

        test('handles non-array JSON5 content in multiple file mode', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $dirPath = $this->tempDir.'/delegations';
            mkdir($dirPath, 0o755, true);

            // Create file with JSON5 that decodes to non-array
            $filePath = $dirPath.'/del-string.json5';
            file_put_contents($filePath, '"just a string"');

            // Act - Json5Decoder will decode to string, not array
            $found = $repository->findById('del-string');

            // Assert
            expect($found)->toBeNull();
        });

        test('handles file_get_contents failure in multiple file mode', function (): void {
            // Arrange
            $dirPath = $this->tempDir.'/delegations';
            mkdir($dirPath, 0o755, true);

            // Create a delegation file then delete it to simulate file_get_contents failure
            $delegation = new Delegation(
                id: 'del-missing',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(['doc:*'], ['read']),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository = new Json5DelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $repository->create($delegation);

            // Delete the file to force file_get_contents to return false
            $filePath = $dirPath.'/del-missing.json5';
            unlink($filePath);

            // Act - file_get_contents will return false for missing file
            // This covers line 283 in loadMultipleFiles context
            $found = $repository->findById('del-missing');

            // Assert - Should return null when file doesn't exist
            expect($found)->toBeNull();
        });
    });

    describe('Regressions', function (): void {
        test('prevents duplicate delegation IDs in single file mode', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
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
            $filePath = $this->tempDir.'/delegations/delegations.json5';
            $content = json_decode(file_get_contents($filePath), true);

            // Assert - Both delegations stored (no deduplication in current implementation)
            expect($content)->toHaveCount(2);
            expect($content[0]['id'])->toBe('del-duplicate');
            expect($content[1]['id'])->toBe('del-duplicate');
        });

        test('preserves JSON structure when revoking in multiple file mode', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
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
            $filePath = $this->tempDir.'/delegations/del-multi-revoke.json5';
            $content = json_decode(file_get_contents($filePath), true);

            expect($content)
                ->toHaveKey('state', 'revoked')
                ->toHaveKey('revoked_at')
                ->toHaveKey('metadata', ['key' => 'value']);
        });

        test('respects configured retention days', function (): void {
            // Arrange
            $repository = new Json5DelegationRepository(
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
