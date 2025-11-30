<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Patrol\Core\Storage\CsvDelegationRepository;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\FileMode;
use Tests\Helpers\FilesystemHelper;

describe('CsvDelegationRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_csv_delegation_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        test('creates delegation in single file mode', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-123',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read', 'write'],
                ),
                createdAt: CarbonImmutable::parse('2024-01-01 10:00:00'),
                expiresAt: CarbonImmutable::parse('2024-12-31 23:59:59'),
                isTransitive: false,
                status: DelegationState::Active,
                metadata: ['reason' => 'vacation coverage'],
            );

            // Act
            $repository->create($delegation);

            // Assert
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('del-123');
            expect($content)->toContain('user:alice');
            expect($content)->toContain('user:bob');
        });

        test('finds delegation by ID', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-456',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['project:100'],
                    actions: ['manage'],
                    domain: 'engineering',
                ),
                createdAt: CarbonImmutable::parse('2024-01-01 10:00:00'),
                expiresAt: null,
                isTransitive: true,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act
            $result = $repository->findById('del-456');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->id)->toBe('del-456');
            expect($result->delegatorId)->toBe('user:alice');
            expect($result->delegateId)->toBe('user:bob');
            expect($result->scope->resources)->toBe(['project:100']);
            expect($result->scope->actions)->toBe(['manage']);
            expect($result->scope->domain)->toBe('engineering');
            expect($result->isTransitive)->toBeTrue();
        });

        test('finds active delegations for delegate', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Active delegation
            $repository->create(
                new Delegation(
                    id: 'del-1',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: CarbonImmutable::now()->addDays(30),
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                ),
            );

            // Expired delegation
            $repository->create(
                new Delegation(
                    id: 'del-2',
                    delegatorId: 'user:charlie',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['file:*'],
                        actions: ['delete'],
                    ),
                    createdAt: CarbonImmutable::now()->subDays(60),
                    expiresAt: CarbonImmutable::now()->subDays(1),
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                ),
            );

            // Act
            $active = $repository->findActiveForDelegate('user:bob');

            // Assert
            expect($active)->toHaveCount(1);
            expect($active[0]->id)->toBe('del-1');
        });

        test('revokes delegation', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-789',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
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
            $repository->revoke('del-789');

            // Assert
            $result = $repository->findById('del-789');
            expect($result)->not->toBeNull();
            expect($result->status)->toBe(DelegationState::Revoked);
        });

        test('cleans up old delegations', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Old expired delegation - should be removed
            $repository->create(
                new Delegation(
                    id: 'del-old',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now()->subDays(200),
                    expiresAt: CarbonImmutable::now()->subDays(100),
                    isTransitive: false,
                    status: DelegationState::Expired,
                    metadata: [],
                ),
            );

            // Recent active delegation - should be kept
            $repository->create(
                new Delegation(
                    id: 'del-active',
                    delegatorId: 'user:charlie',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['file:*'],
                        actions: ['write'],
                    ),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: CarbonImmutable::now()->addDays(30),
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                ),
            );

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(1);
            expect($repository->findById('del-old'))->toBeNull();
            expect($repository->findById('del-active'))->not->toBeNull();
        });

        test('handles multiple files mode', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $delegation1 = new Delegation(
                id: 'del-multi-1',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $delegation2 = new Delegation(
                id: 'del-multi-2',
                delegatorId: 'user:charlie',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['project:*'],
                    actions: ['manage'],
                ),
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
            expect(file_exists($this->tempDir.'/delegations/del-multi-1.csv'))->toBeTrue();
            expect(file_exists($this->tempDir.'/delegations/del-multi-2.csv'))->toBeTrue();

            $result1 = $repository->findById('del-multi-1');
            $result2 = $repository->findById('del-multi-2');

            expect($result1)->not->toBeNull();
            expect($result2)->not->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns null when delegation not found', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $result = $repository->findById('nonexistent');

            // Assert
            expect($result)->toBeNull();
        });

        test('returns empty array when no active delegations exist', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $active = $repository->findActiveForDelegate('user:nobody');

            // Assert
            expect($active)->toHaveCount(0);
        });

        test('returns zero when no delegations to cleanup', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(0);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles delegation with complex metadata', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $metadata = [
                'reason' => 'vacation coverage',
                'approved_by' => 'manager:123',
                'notes' => 'Temporary access for project completion',
            ];

            $delegation = new Delegation(
                id: 'del-meta',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: $metadata,
            );

            // Act
            $repository->create($delegation);
            $result = $repository->findById('del-meta');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->metadata)->toBe($metadata);
        });

        test('handles delegation with multiple resources and actions', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-multi',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*', 'project:*', 'file:*'],
                    actions: ['read', 'write', 'delete'],
                    domain: 'engineering',
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);
            $result = $repository->findById('del-multi');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->scope->resources)->toBe(['document:*', 'project:*', 'file:*']);
            expect($result->scope->actions)->toBe(['read', 'write', 'delete']);
        });

        test('filters delegations by non-matching delegate ID', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $repository->create(
                new Delegation(
                    id: 'del-1',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: CarbonImmutable::now()->addDays(30),
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                ),
            );

            // Act
            $active = $repository->findActiveForDelegate('user:charlie');

            // Assert
            expect($active)->toHaveCount(0);
        });

        test('filters out non-active delegations for delegate', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $repository->create(
                new Delegation(
                    id: 'del-revoked',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: CarbonImmutable::now()->addDays(30),
                    isTransitive: false,
                    status: DelegationState::Revoked,
                    metadata: [],
                ),
            );

            // Act
            $active = $repository->findActiveForDelegate('user:bob');

            // Assert
            expect($active)->toHaveCount(0);
        });

        test('cleanup preserves recent expired delegations', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Recent expired delegation - should be kept (within 90 days)
            $repository->create(
                new Delegation(
                    id: 'del-recent-expired',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now()->subDays(10),
                    expiresAt: CarbonImmutable::now()->subDays(5),
                    isTransitive: false,
                    status: DelegationState::Expired,
                    metadata: [],
                ),
            );

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(0);
            expect($repository->findById('del-recent-expired'))->not->toBeNull();
        });

        test('cleanup removes old revoked delegations', function (): void {
            // Arrange
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            mkdir(dirname($filePath), 0o755, true);

            // Manually create CSV with old revoked delegation
            $oldRevokedAt = CarbonImmutable::now()->subDays(100)->format('Y-m-d H:i:s');
            $createdAt = CarbonImmutable::now()->subDays(200)->format('Y-m-d H:i:s');

            $csvContent = "id,delegator_id,delegate_id,resources,actions,domain,created_at,expires_at,is_transitive,state,metadata,revoked_at\n";
            $csvContent .= sprintf('del-old-revoked,user:alice,user:bob,document:*,read,,%s,,0,revoked,{},%s%s', $createdAt, $oldRevokedAt, \PHP_EOL);

            file_put_contents($filePath, $csvContent);

            // Create repository
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(1);

            // Create new repository to verify deletion
            $repository2 = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );
            expect($repository2->findById('del-old-revoked'))->toBeNull();
        });

        test('cleanup preserves recent revoked delegations', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-recent-revoked',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);
            $repository->revoke('del-recent-revoked');

            // Act
            $removed = $repository->cleanup();

            // Assert
            expect($removed)->toBe(0);
            expect($repository->findById('del-recent-revoked'))->not->toBeNull();
        });

        test('handles delegation with empty resources', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-empty-res',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: [],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);
            $result = $repository->findById('del-empty-res');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->scope->resources)->toBe([]);
        });

        test('handles delegation with empty actions', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-empty-act',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: [],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);
            $result = $repository->findById('del-empty-act');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->scope->actions)->toBe([]);
        });

        test('handles delegation with invalid JSON metadata', function (): void {
            // Arrange - Manually create CSV with invalid JSON metadata
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            mkdir(dirname($filePath), 0o755, true);

            $createdAt = CarbonImmutable::now()->format('Y-m-d H:i:s');

            $csvContent = "id,delegator_id,delegate_id,resources,actions,domain,created_at,expires_at,is_transitive,state,metadata,revoked_at\n";
            $csvContent .= "del-invalid-meta,user:alice,user:bob,document:*,read,,{$createdAt},,0,active,invalid-json,\n";

            file_put_contents($filePath, $csvContent);

            // Create repository
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $result = $repository->findById('del-invalid-meta');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->metadata)->toBe([]);
        });

        test('uses cache on second load', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-cache',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);

            // First findById call - populates cache
            $result1 = $repository->findById('del-cache');

            // Second findById call on same repository - should use cache (line 301)
            $result2 = $repository->findById('del-cache');

            // Assert - both should return same result showing cache works
            expect($result1)->not->toBeNull();
            expect($result2)->not->toBeNull();
            expect($result1->id)->toBe('del-cache');
            expect($result2->id)->toBe('del-cache');
        });

        test('uses pre-populated cache on first load with findById', function (): void {
            // Arrange - Create pre-populated cache with delegation data
            $createdAt = CarbonImmutable::now()->format('Y-m-d H:i:s');
            $expiresAt = CarbonImmutable::now()->addDays(30)->format('Y-m-d H:i:s');

            $cachedDelegation = [
                'id' => 'del-cached',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'resources' => 'document:*',
                'actions' => 'read',
                'domain' => '',
                'created_at' => $createdAt,
                'expires_at' => $expiresAt,
                'is_transitive' => '0',
                'state' => 'active',
                'metadata' => '{}',
                'revoked_at' => '',
            ];

            // Cache key format: delegations:{version}:{fileMode}
            $cacheKey = 'delegations:latest:single';
            $cache = [
                $cacheKey => [$cachedDelegation],
            ];

            // Create repository with pre-populated cache
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                cache: $cache,
            );

            // Act - This should hit cache on line 326 instead of loading from disk
            $result = $repository->findById('del-cached');

            // Assert - delegation should be found from cache
            expect($result)->not->toBeNull();
            expect($result->id)->toBe('del-cached');
            expect($result->delegatorId)->toBe('user:alice');
            expect($result->delegateId)->toBe('user:bob');
            expect($result->scope->resources)->toBe(['document:*']);
            expect($result->scope->actions)->toBe(['read']);
            expect($result->status)->toBe(DelegationState::Active);

            // Verify no files were created (proves cache was used)
            expect(file_exists($this->tempDir.'/delegations/delegations.csv'))->toBeFalse();
        });

        test('uses pre-populated cache on first load with findActiveForDelegate', function (): void {
            // Arrange - Create pre-populated cache with active delegation
            $createdAt = CarbonImmutable::now()->format('Y-m-d H:i:s');
            $expiresAt = CarbonImmutable::now()->addDays(30)->format('Y-m-d H:i:s');

            $cachedDelegation = [
                'id' => 'del-cached-active',
                'delegator_id' => 'user:alice',
                'delegate_id' => 'user:bob',
                'resources' => 'document:*|project:*',
                'actions' => 'read|write',
                'domain' => 'engineering',
                'created_at' => $createdAt,
                'expires_at' => $expiresAt,
                'is_transitive' => '1',
                'state' => 'active',
                'metadata' => '{"reason":"vacation"}',
                'revoked_at' => '',
            ];

            // Cache key format: delegations:{version}:{fileMode}
            $cacheKey = 'delegations:latest:multiple';
            $cache = [
                $cacheKey => [$cachedDelegation],
            ];

            // Create repository with pre-populated cache in multiple file mode
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
                cache: $cache,
            );

            // Act - This should hit cache on line 326 instead of loading from disk
            $active = $repository->findActiveForDelegate('user:bob');

            // Assert - active delegations should be found from cache
            expect($active)->toHaveCount(1);
            expect($active[0]->id)->toBe('del-cached-active');
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

        test('resolves version when loading delegations with versioning enabled in multiple file mode', function (): void {
            // Arrange - Create versioned directory structure
            $versionDir = $this->tempDir.'/delegations/1.0.0';
            mkdir($versionDir, 0o755, true);

            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '1.0.0',
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-versioned-load',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Act - This will trigger loadDelegations() -> loadMultipleFiles() -> buildDirectoryPath() with versioning
            $result = $repository->findById('del-versioned-load');

            // Assert - Delegation should be found and loaded from versioned directory
            expect($result)->not->toBeNull();
            expect($result->id)->toBe('del-versioned-load');
            expect($result->delegatorId)->toBe('user:alice');
            expect($result->delegateId)->toBe('user:bob');
        });

        test('handles corrupted CSV file gracefully', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create corrupted CSV file
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, "invalid\ncsv\ncontent");

            // Act
            $result = $repository->findById('del-anything');

            // Assert
            expect($result)->toBeNull();
        });

        test('handles multiple files mode with non-existent directory', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir.'/nonexistent',
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Act
            $result = $repository->findById('del-anything');

            // Assert
            expect($result)->toBeNull();
        });

        test('handles multiple files mode with corrupted file', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Create valid delegation
            $delegation = new Delegation(
                id: 'del-valid',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Create corrupted file
            $corruptedPath = $this->tempDir.'/delegations/del-corrupted.csv';
            file_put_contents($corruptedPath, "invalid\ncsv");

            // Act - should skip corrupted file
            $result = $repository->findById('del-valid');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->id)->toBe('del-valid');
        });

        test('handles versioning enabled with version resolution', function (): void {
            // Arrange - create version directory structure
            $versionDir = $this->tempDir.'/delegations/1.0.0';
            mkdir($versionDir, 0o755, true);

            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '1.0.0',
                versioningEnabled: true,
            );

            $delegation = new Delegation(
                id: 'del-versioned',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            // Act
            $repository->create($delegation);

            // Assert
            expect(file_exists($this->tempDir.'/delegations/1.0.0/del-versioned.csv'))->toBeTrue();
        });

        test('revokes delegation in multiple files mode', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $delegation = new Delegation(
                id: 'del-multi-revoke',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
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
            $repository->revoke('del-multi-revoke');

            // Assert
            $result = $repository->findById('del-multi-revoke');
            expect($result)->not->toBeNull();
            expect($result->status)->toBe(DelegationState::Revoked);
        });

        test('handles multiple files mode with unreadable file', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            // Create valid delegation
            $delegation = new Delegation(
                id: 'del-readable',
                delegatorId: 'user:alice',
                delegateId: 'user:bob',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::now(),
                expiresAt: null,
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository->create($delegation);

            // Create a directory where a file should be (simulates unreadable file scenario)
            $badPath = $this->tempDir.'/delegations/del-bad.csv';
            mkdir($badPath, 0o755, true);

            // Act - should skip the bad "file" that's actually a directory
            $result = $repository->findById('del-readable');

            // Assert
            expect($result)->not->toBeNull();
            expect($result->id)->toBe('del-readable');
        });

        test('cleanup returns correct count when multiple delegations removed', function (): void {
            // Arrange
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            mkdir(dirname($filePath), 0o755, true);

            $oldRevokedAt = CarbonImmutable::now()->subDays(100)->format('Y-m-d H:i:s');
            $oldExpiredAt = CarbonImmutable::now()->subDays(100)->format('Y-m-d H:i:s');
            $createdAt = CarbonImmutable::now()->subDays(200)->format('Y-m-d H:i:s');

            $csvContent = "id,delegator_id,delegate_id,resources,actions,domain,created_at,expires_at,is_transitive,state,metadata,revoked_at\n";
            $csvContent .= sprintf('del-old-revoked-1,user:alice,user:bob,document:*,read,,%s,,0,revoked,{},%s%s', $createdAt, $oldRevokedAt, \PHP_EOL);
            $csvContent .= "del-old-expired-1,user:alice,user:bob,document:*,read,,{$createdAt},{$oldExpiredAt},0,expired,,\n";
            $csvContent .= sprintf('del-old-revoked-2,user:alice,user:bob,document:*,read,,%s,,0,revoked,{},%s%s', $createdAt, $oldRevokedAt, \PHP_EOL);

            file_put_contents($filePath, $csvContent);

            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $removed = $repository->cleanup();

            // Assert - should remove all 3 old delegations
            expect($removed)->toBe(3);
        });

        test('handles delegation without expires_at in findActiveForDelegate', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create delegation without expiration
            $repository->create(
                new Delegation(
                    id: 'del-no-expiry',
                    delegatorId: 'user:alice',
                    delegateId: 'user:bob',
                    scope: new DelegationScope(
                        resources: ['document:*'],
                        actions: ['read'],
                    ),
                    createdAt: CarbonImmutable::now(),
                    expiresAt: null,
                    isTransitive: false,
                    status: DelegationState::Active,
                    metadata: [],
                ),
            );

            // Act
            $active = $repository->findActiveForDelegate('user:bob');

            // Assert
            expect($active)->toHaveCount(1);
            expect($active[0]->id)->toBe('del-no-expiry');
        });

        test('handles CSV parsing exception gracefully in single file mode', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Create a CSV file that triggers an exception during parsing
            // The exception will be caught by the catch block on line 353
            $filePath = $this->tempDir.'/delegations/delegations.csv';
            mkdir(dirname($filePath), 0o755, true);

            // Create invalid CSV content that will trigger exception during getRecords() iteration
            // Using content that passes Reader::createFromString but fails on getRecords
            $csvContent = "id,delegator_id,delegate_id,resources,actions,domain,created_at,expires_at,is_transitive,state,metadata,revoked_at\n";
            // Add a row with mismatched column count or invalid structure
            $csvContent .= '"unclosed field'; // Unclosed quote will trigger exception

            file_put_contents($filePath, $csvContent);

            // Act - should handle exception and return empty array
            $result = $repository->findById('del-anything');

            // Assert - should gracefully return null (empty results)
            expect($result)->toBeNull();
        });

        test('respects configured retention days', function (): void {
            // Arrange
            $repository = new CsvDelegationRepository(
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
