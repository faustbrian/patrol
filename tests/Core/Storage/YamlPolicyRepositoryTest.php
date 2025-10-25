<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\YamlPolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Tests\Helpers\FilesystemHelper;

describe('YamlPolicyRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_yaml_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('decode() method', function (): void {
        test('returns correct extension', function (): void {
            // Arrange
            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act & Assert - Testing through file creation
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $yaml = <<<'YAML'
            - subject: user:123
              resource: document:456
              action: read
              effect: Allow
              priority: 1
            YAML;

            file_put_contents($policyDir.'/policies.yaml', $yaml);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toHaveCount(1);
        });

        test('parses valid yaml format', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $yaml = <<<'YAML'
            - subject: user:123
              resource: document:456
              action: read
              effect: Allow
              priority: 1
            - subject: user:456
              resource: document:789
              action: write
              effect: Deny
              priority: 5
            YAML;

            file_put_contents($policyDir.'/policies.yaml', $yaml);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('handles invalid yaml gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.yaml', "invalid:\n\t\tyaml: [unclosed");

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles non-array yaml content gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // YAML that parses to a string, not an array
            file_put_contents($policyDir.'/policies.yaml', 'just a string');

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles yaml that parses to integer', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // YAML that parses to an integer
            file_put_contents($policyDir.'/policies.yaml', '12345');

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles yaml with exception during parsing', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Malformed YAML that triggers parse exception
            file_put_contents($policyDir.'/policies.yaml', "---\n\t bad indentation\n  - item");

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });
    });

    describe('encode() method', function (): void {
        test('encodes policy data to yaml format', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: null,
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Assert
            $filePath = $policyDir.'/policies.yaml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain("subject: 'user:123'");
            expect($content)->toContain("resource: 'document:456'");
            expect($content)->toContain('action: read');
            expect($content)->toContain('effect: Allow');
            expect($content)->toContain('priority: 1');
        });

        test('encodes and decodes complex yaml with multiple policies', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(10),
                    domain: null,
                ),
                new PolicyRule(
                    subject: 'user:456',
                    resource: 'document:789',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(5),
                    domain: new Domain('admin'),
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Load it back
            $subject1 = new Subject('user:123');
            $resource1 = new Resource('document:456', 'document');
            $loadedPolicy1 = $repository->getPoliciesFor($subject1, $resource1);

            $subject2 = new Subject('user:456');
            $resource2 = new Resource('document:789', 'document');
            $loadedPolicy2 = $repository->getPoliciesFor($subject2, $resource2);

            // Assert
            expect($loadedPolicy1->rules)->toHaveCount(1);
            expect($loadedPolicy1->rules[0]->effect)->toBe(Effect::Allow);
            expect($loadedPolicy1->rules[0]->priority->value)->toBe(10);

            expect($loadedPolicy2->rules)->toHaveCount(1);
            expect($loadedPolicy2->rules[0]->effect)->toBe(Effect::Deny);
            expect($loadedPolicy2->rules[0]->priority->value)->toBe(5);
            expect($loadedPolicy2->rules[0]->domain?->id)->toBe('admin');
        });

        test('encodes yaml with proper indentation and structure', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: new Domain('production'),
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Assert
            $filePath = $policyDir.'/policies.yaml';
            $content = file_get_contents($filePath);

            // Verify YAML structure (array indicator)
            expect($content)->toMatch('/^-\s+/m');

            // Verify all fields are present
            expect($content)->toContain("subject: 'user:123'");
            expect($content)->toContain('action: read');
            expect($content)->toContain('effect: Allow');
            expect($content)->toContain('priority: 1');
            expect($content)->toContain("resource: 'document:456'");
            expect($content)->toContain('domain: production');
        });

        test('encodes empty policy to empty yaml array', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $policy = new Policy([]);

            // Act
            $repository->save($policy);

            // Assert
            $filePath = $policyDir.'/policies.yaml';
            $content = file_get_contents($filePath);

            // Empty array in YAML is represented as {  }
            expect($content)->toBe('{  }');
        });
    });

    describe('getExtension() method', function (): void {
        test('returns yaml extension', function (): void {
            // Arrange
            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act - Save a policy to trigger file creation with extension
            $rules = [
                new PolicyRule(
                    subject: 'user:123',
                    resource: null,
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: null,
                ),
            ];

            $policy = new Policy($rules);
            $repository->save($policy);

            // Assert - Check file was created with .yaml extension
            $expectedPath = $this->tempDir.'/policies/policies.yaml';
            expect(file_exists($expectedPath))->toBeTrue();
        });
    });

    describe('edge cases', function (): void {
        test('returns cached data when cache key exists covering line 116', function (): void {
            // Arrange - Pre-populate cache to explicitly test line 116
            $cachedPolicies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 5,
                    'domain' => 'tenant:test',
                ],
            ];

            // Cache key format must match getCacheKey(): policies:{version}:{fileMode}
            $cacheKey = 'policies:latest:single';
            $cache = [$cacheKey => $cachedPolicies];

            // Create repository with pre-populated cache (line 116 path)
            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                cache: $cache,
            );

            $subject = new Subject('user:alice');
            $resource = new Resource('document:123', 'document');

            // Act - Should hit line 116: return $this->cache[$cacheKey];
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Data comes from cache
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
            expect($result->rules[0]->resource)->toBe('document:123');
            expect($result->rules[0]->priority->value)->toBe(5);
            expect($result->rules[0]->domain->id)->toBe('tenant:test');
        });

        test('skips unreadable files in multiple file mode covering line 186', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $yaml1 = <<<'YAML'
            - subject: user:alice
              resource: document:123
              action: read
              effect: Allow
            YAML;

            $yaml2 = <<<'YAML'
            - subject: user:bob
              resource: document:456
              action: write
              effect: Allow
            YAML;

            file_put_contents($policyDir.'/valid.yaml', $yaml1);
            file_put_contents($policyDir.'/unreadable.yaml', $yaml2);

            // Make second file unreadable to trigger line 186 continue
            $unreadableFile = $policyDir.'/unreadable.yaml';

            if (\PHP_OS_FAMILY !== 'Windows') {
                chmod($unreadableFile, 0o000);
            }

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = new Subject('user:alice');
            $resource = new Resource('document:123', 'document');

            try {
                // Act - Should skip unreadable file (line 186) and process valid file
                $result = $repository->getPoliciesFor($subject, $resource);

                // Assert - Only reads the valid file, skipped the unreadable one
                expect($result->rules)->toHaveCount(1);
                expect($result->rules[0]->subject)->toBe('user:alice');
            } finally {
                // Cleanup - restore permissions
                if (\PHP_OS_FAMILY !== 'Windows') {
                    chmod($unreadableFile, 0o644);
                }
            }
        });

        test('handles yaml with missing resource field gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $yaml = <<<'YAML'
            - subject: user:123
              action: read
              effect: Allow
              priority: 1
            YAML;

            file_put_contents($policyDir.'/policies.yaml', $yaml);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Missing resource field means policy applies to any resource
            expect($policy->rules)->toHaveCount(1);
        });

        test('handles yaml with special characters', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:john@example.com',
                    resource: 'file://path/to/resource',
                    action: 'read:write',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: null,
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Load it back
            $subject = new Subject('user:john@example.com');
            $resource = new Resource('file://path/to/resource', 'file');
            $loadedPolicy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($loadedPolicy->rules)->toHaveCount(1);
            expect($loadedPolicy->rules[0]->subject)->toBe('user:john@example.com');
            expect($loadedPolicy->rules[0]->resource)->toBe('file://path/to/resource');
            expect($loadedPolicy->rules[0]->action)->toBe('read:write');
        });

        test('handles multiline yaml', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $yaml = <<<'YAML'
            -
              subject: user:123
              resource: document:456
              action: read
              effect: Allow
              priority: 1
            -
              subject: user:456
              resource: document:789
              action: write
              effect: Deny
              priority: 2
            YAML;

            file_put_contents($policyDir.'/policies.yaml', $yaml);

            $repository = new YamlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });
    });
});
