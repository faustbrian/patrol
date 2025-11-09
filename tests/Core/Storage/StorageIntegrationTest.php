<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Patrol\Core\Storage\JsonPolicyRepository;
use Patrol\Core\Storage\SerializedPolicyRepository;
use Patrol\Core\Storage\StorageFactory;
use Patrol\Core\Storage\StorageManager;
use Patrol\Core\Storage\TomlPolicyRepository;
use Patrol\Core\Storage\XmlPolicyRepository;
use Patrol\Core\Storage\YamlPolicyRepository;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\StorageDriver;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;
use Tests\Helpers\FilesystemHelper;

describe('Storage System Integration', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_integration_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);

        $this->factory = new StorageFactory();
        $this->config = [
            'path' => $this->tempDir,
            'file_mode' => FileMode::Single,
            'versioning' => ['enabled' => true],
        ];
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Runtime Driver Switching', function (): void {
        test('switches from eloquent to json at runtime', function (): void {
            // Arrange
            $manager = new StorageManager(
                driver: StorageDriver::Eloquent,
                config: $this->config,
                factory: $this->factory,
            );

            // Act
            $eloquentRepo = $manager->policy();
            $jsonRepo = $manager->driver(StorageDriver::Json)->policy();

            // Assert
            expect($eloquentRepo)->toBeInstanceOf(DatabasePolicyRepository::class);
            expect($jsonRepo)->toBeInstanceOf(JsonPolicyRepository::class);
        });

        test('switches between multiple file-based drivers', function (): void {
            // Arrange
            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: $this->config,
                factory: $this->factory,
            );

            // Act
            $jsonRepo = $manager->policy();
            $yamlRepo = $manager->driver(StorageDriver::Yaml)->policy();
            $backToJson = $manager->driver(StorageDriver::Json)->policy();

            // Assert
            expect($jsonRepo)->toBeInstanceOf(JsonPolicyRepository::class);
            expect($yamlRepo)->toBeInstanceOf(YamlPolicyRepository::class);
            expect($backToJson)->toBeInstanceOf(JsonPolicyRepository::class);
        });
    });

    describe('Version-Specific Loading for Auditing', function (): void {
        test('loads specific version for auditing purposes', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/policies/2.0.0', 0o755, true);

            file_put_contents($this->tempDir.'/policies/1.0.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            file_put_contents($this->tempDir.'/policies/2.0.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Deny',
                    'priority' => 1,
                ],
            ]));

            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: $this->config,
                factory: $this->factory,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $v1Policy = $manager->version('1.0.0')->policy()->getPoliciesFor($subject, $resource);
            $v2Policy = $manager->version('2.0.0')->policy()->getPoliciesFor($subject, $resource);

            // Assert
            expect($v1Policy->rules[0]->effect)->toBe(Effect::Allow);
            expect($v2Policy->rules[0]->effect)->toBe(Effect::Deny);
        });

        test('auto-detects latest version when version not specified', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);
            mkdir($this->tempDir.'/policies/2.5.0', 0o755, true);

            file_put_contents($this->tempDir.'/policies/2.5.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: $this->config,
                factory: $this->factory,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $manager->policy()->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
        });
    });

    describe('Runtime File Mode Switching', function (): void {
        test('switches from single to multiple file mode at runtime', function (): void {
            // Arrange
            $singleDir = $this->tempDir.'/policies';
            mkdir($singleDir, 0o755, true);

            file_put_contents($singleDir.'/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            file_put_contents($singleDir.'/admin-policy.json', json_encode([
                [
                    'subject' => 'user:456',
                    'resource' => 'document:789',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: array_merge($this->config, ['versioning' => ['enabled' => false]]),
                factory: $this->factory,
            );

            $subject1 = new Subject('user:123');
            $subject2 = new Subject('user:456');
            $resource1 = new Resource('document:456', 'document');
            $resource2 = new Resource('document:789', 'document');

            // Act
            $singlePolicy = $manager->fileMode(FileMode::Single)->policy()->getPoliciesFor($subject1, $resource1);
            $multiplePolicy = $manager->fileMode(FileMode::Multiple)->policy()->getPoliciesFor($subject2, $resource2);

            // Assert
            expect($singlePolicy->rules)->toHaveCount(1);
            expect($multiplePolicy->rules)->toHaveCount(1);
        });
    });

    describe('Policy Evaluation with Different Storage Drivers', function (): void {
        test('evaluates same policy across different drivers', function (): void {
            // Arrange - Create same policy in JSON and YAML
            $jsonDir = $this->tempDir.'/json/policies';
            $yamlDir = $this->tempDir.'/yaml/policies';

            mkdir($jsonDir, 0o755, true);
            mkdir($yamlDir, 0o755, true);

            $policyData = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ];

            file_put_contents($jsonDir.'/policies.json', json_encode($policyData));

            $yaml = <<<'YAML'
            - subject: user:123
              resource: document:456
              action: read
              effect: Allow
              priority: 1
            YAML;
            file_put_contents($yamlDir.'/policies.yaml', $yaml);

            $jsonConfig = array_merge($this->config, ['path' => $this->tempDir.'/json', 'versioning' => ['enabled' => false]]);
            $yamlConfig = array_merge($this->config, ['path' => $this->tempDir.'/yaml', 'versioning' => ['enabled' => false]]);

            $jsonManager = new StorageManager(StorageDriver::Json, $jsonConfig, $this->factory);
            $yamlManager = new StorageManager(StorageDriver::Yaml, $yamlConfig, $this->factory);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $jsonPolicy = $jsonManager->policy()->getPoliciesFor($subject, $resource);
            $yamlPolicy = $yamlManager->policy()->getPoliciesFor($subject, $resource);

            // Assert
            expect($jsonPolicy->rules)->toHaveCount(1);
            expect($yamlPolicy->rules)->toHaveCount(1);
            expect($jsonPolicy->rules[0]->effect)->toBe($yamlPolicy->rules[0]->effect);
        });
    });

    describe('Delegation Lifecycle with File Storage', function (): void {
        test('creates, retrieves, and revokes delegation', function (): void {
            // Arrange
            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: array_merge($this->config, ['versioning' => ['enabled' => false]]),
                factory: $this->factory,
            );

            $delegationDir = $this->tempDir.'/delegations';
            mkdir($delegationDir, 0o755, true);

            $delegation = new Delegation(
                id: 'delegation-123',
                delegatorId: 'user:1',
                delegateId: 'user:2',
                scope: new DelegationScope(
                    resources: ['document:*'],
                    actions: ['read'],
                ),
                createdAt: CarbonImmutable::parse('2024-01-01 10:00:00'),
                expiresAt: CarbonImmutable::parse('2025-12-31 23:59:59'),
                isTransitive: false,
                status: DelegationState::Active,
                metadata: [],
            );

            $repository = $manager->delegation();

            // Act - Create
            $repository->create($delegation);

            // Act - Retrieve
            $found = $repository->findById('delegation-123');

            // Act - Revoke
            $repository->revoke('delegation-123');
            $afterRevoke = $repository->findActiveForDelegate('user:2');

            // Assert
            expect($found)->toBeInstanceOf(Delegation::class);
            expect($found->id)->toBe('delegation-123');
            expect($afterRevoke)->toBeEmpty();
        });
    });

    describe('Version Migration Scenarios', function (): void {
        test('migrates policies from one version to another', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

            file_put_contents($this->tempDir.'/policies/1.0.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: $this->config,
                factory: $this->factory,
            );

            // Act - Read from v1.0.0
            $v1Repo = $manager->version('1.0.0')->policy();

            // Simulate migration - create v2.0.0
            mkdir($this->tempDir.'/policies/2.0.0', 0o755, true);
            file_put_contents($this->tempDir.'/policies/2.0.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 10, // Updated priority
                ],
            ]));

            $v2Repo = $manager->version('2.0.0')->policy();

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Assert
            $v1Policy = $v1Repo->getPoliciesFor($subject, $resource);
            $v2Policy = $v2Repo->getPoliciesFor($subject, $resource);

            expect($v1Policy->rules[0]->priority->value)->toBe(1);
            expect($v2Policy->rules[0]->priority->value)->toBe(10);
        });
    });

    describe('Concurrent Access with Caching', function (): void {
        test('caches file contents for repeated access', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act - First call (loads from file)
            $policy1 = $repository->getPoliciesFor($subject, $resource);

            // Act - Second call (should use cache)
            $policy2 = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy1->rules)->toHaveCount(1);
            expect($policy2->rules)->toHaveCount(1);
            expect($policy1->rules[0]->subject)->toBe($policy2->rules[0]->subject);
        });
    });

    describe('Complex Integration Scenarios', function (): void {
        test('combines driver switching, versioning, and file mode changes', function (): void {
            // Arrange
            mkdir($this->tempDir.'/policies/1.0.0', 0o755, true);

            file_put_contents($this->tempDir.'/policies/1.0.0/policies.json', json_encode([
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $manager = new StorageManager(
                driver: StorageDriver::Eloquent,
                config: $this->config,
                factory: $this->factory,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $manager
                ->driver(StorageDriver::Json)
                ->version('1.0.0')
                ->fileMode(FileMode::Single)
                ->policy()
                ->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('handles switching between all supported drivers', function (): void {
            // Arrange
            $manager = new StorageManager(
                driver: StorageDriver::Json,
                config: $this->config,
                factory: $this->factory,
            );

            // Act & Assert
            expect($manager->driver(StorageDriver::Json)->policy())->toBeInstanceOf(JsonPolicyRepository::class);
            expect($manager->driver(StorageDriver::Yaml)->policy())->toBeInstanceOf(YamlPolicyRepository::class);
            expect($manager->driver(StorageDriver::Xml)->policy())->toBeInstanceOf(XmlPolicyRepository::class);
            expect($manager->driver(StorageDriver::Toml)->policy())->toBeInstanceOf(TomlPolicyRepository::class);
            expect($manager->driver(StorageDriver::Serialized)->policy())->toBeInstanceOf(SerializedPolicyRepository::class);
        });
    });
});
