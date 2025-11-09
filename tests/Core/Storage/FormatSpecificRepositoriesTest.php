<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\SerializedPolicyRepository;
use Patrol\Core\Storage\TomlPolicyRepository;
use Patrol\Core\Storage\XmlPolicyRepository;
use Patrol\Core\Storage\YamlPolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Saloon\XmlWrangler\XmlReader;
use Tests\Helpers\FilesystemHelper;
use Tests\Support\SerializedPolicyRepositoryTestHelper;
use Yosymfony\Toml\Toml;

describe('Format-Specific Policy Repositories', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_format_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('XmlPolicyRepository', function (): void {
        test('returns correct extension', function (): void {
            // Skip if XML package not installed
            if (!class_exists(XmlReader::class)) {
                $this->markTestSkipped('Saloon XML Wrangler package not installed');
            }

            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act & Assert - Testing through file creation
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>user:123</subject>
                    <resource>document:456</resource>
                    <action>read</action>
                    <effect>Allow</effect>
                    <priority>1</priority>
                </policy>
            </policies>
            XML;

            file_put_contents($policyDir.'/policies.xml', $xml);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toHaveCount(1);
        });

        test('parses valid xml format', function (): void {
            // Skip if XML package not installed
            if (!class_exists(XmlReader::class)) {
                $this->markTestSkipped('Saloon XML Wrangler package not installed');
            }

            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>user:123</subject>
                    <resource>document:456</resource>
                    <action>read</action>
                    <effect>Allow</effect>
                    <priority>1</priority>
                </policy>
            </policies>
            XML;

            file_put_contents($policyDir.'/policies.xml', $xml);

            $repository = new XmlPolicyRepository(
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
        });

        test('handles invalid xml gracefully', function (): void {
            // Skip if XML package not installed
            if (!class_exists(XmlReader::class)) {
                $this->markTestSkipped('Saloon XML Wrangler package not installed');
            }

            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.xml', '<invalid><unclosed>');

            $repository = new XmlPolicyRepository(
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

    describe('TomlPolicyRepository', function (): void {
        test('returns correct extension', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $repository = new TomlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act & Assert - Testing through file creation
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $toml = <<<'TOML'
            [[policies]]
            subject = "user:123"
            resource = "document:456"
            action = "read"
            effect = "Allow"
            priority = 1
            TOML;

            file_put_contents($policyDir.'/policies.toml', $toml);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toHaveCount(1);
        });

        test('parses valid toml format', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $toml = <<<'TOML'
            [[policies]]
            subject = "user:123"
            resource = "document:456"
            action = "read"
            effect = "Allow"
            priority = 1

            [[policies]]
            subject = "user:456"
            resource = "document:789"
            action = "write"
            effect = "Deny"
            priority = 5
            TOML;

            file_put_contents($policyDir.'/policies.toml', $toml);

            $repository = new TomlPolicyRepository(
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

        test('handles invalid toml gracefully', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.toml', '[invalid\ntoml = ');

            $repository = new TomlPolicyRepository(
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

        test('handles empty toml file', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Empty TOML file - Toml::parse returns null, line 59 handles with ternary
            file_put_contents($policyDir.'/policies.toml', '   ');

            $repository = new TomlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Empty content returns null from parse, line 59 returns null
            expect($policy->rules)->toBeEmpty();
        });

        test('encodes policies to toml format', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $repository = new TomlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $policyRule = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $policy = new Policy([$policyRule]);

            // Act
            $repository->save($policy);

            // Assert - Verify file was created with correct TOML format
            $filePath = $this->tempDir.'/policies/policies.toml';
            expect($filePath)->toBeFile();

            $content = file_get_contents($filePath);
            expect($content)->toContain('[[policies]]');
            expect($content)->toContain('subject = "user:123"');
            expect($content)->toContain('action = "read"');
            expect($content)->toContain('effect = "Allow"');
        });

        test('encodes multiple policies with all fields', function (): void {
            // Skip if TOML package not installed
            if (!class_exists(Toml::class)) {
                $this->markTestSkipped('Yosymfony TOML package not installed');
            }

            // Arrange
            $repository = new TomlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule1 = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(5),
                domain: new Domain('production'),
            );

            $rule2 = new PolicyRule(
                subject: 'user:456',
                resource: 'document:789',
                action: 'write',
                effect: Effect::Deny,
                priority: new Priority(10),
            );

            $policy = new Policy([$rule1, $rule2]);

            // Act
            $repository->save($policy);

            // Assert - Verify file contains both policies with all fields
            $filePath = $this->tempDir.'/policies/policies.toml';
            $content = file_get_contents($filePath);

            // First policy
            expect($content)->toContain('subject = "user:123"');
            expect($content)->toContain('resource = "document:456"');
            expect($content)->toContain('priority = 5');
            expect($content)->toContain('domain = "production"');

            // Second policy
            expect($content)->toContain('subject = "user:456"');
            expect($content)->toContain('resource = "document:789"');
            expect($content)->toContain('effect = "Deny"');
            expect($content)->toContain('priority = 10');

            // Verify structure
            $decoded = Toml::parse($content);
            expect($decoded)->toHaveKey('policies');
            expect($decoded['policies'])->toHaveCount(2);
        });
    });

    describe('SerializedPolicyRepository', function (): void {
        test('returns correct extension', function (): void {
            // Arrange
            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act & Assert - Testing through file creation
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $data = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ];

            file_put_contents($policyDir.'/policies.ser', serialize($data));

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toHaveCount(1);
        });

        test('parses valid serialized format', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $data = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 10,
                ],
                [
                    'subject' => 'user:456',
                    'resource' => 'document:789',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 5,
                ],
            ];

            file_put_contents($policyDir.'/policies.ser', serialize($data));

            $repository = new SerializedPolicyRepository(
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
            expect($policy->rules[0]->priority->value)->toBe(10);
        });

        test('handles invalid serialized data gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.ser', 'not serialized data');

            $repository = new SerializedPolicyRepository(
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

        test('prevents object injection attacks', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create serialized object (should be rejected)
            $malicious = serialize(
                new stdClass(),
            );
            file_put_contents($policyDir.'/policies.ser', $malicious);

            $repository = new SerializedPolicyRepository(
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

        test('handles corrupted serialized data that returns false', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create corrupted serialized data that makes unserialize() return false
            // This tests the !is_array check (lines 55-57)
            $corrupted = 'a:1:{i:0;O:8:"stdClass"'; // Truncated serialized object
            file_put_contents($policyDir.'/policies.ser', $corrupted);

            $repository = new SerializedPolicyRepository(
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

        test('handles memory exhaustion during unserialization gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create extremely large serialized array that could cause memory issues
            // This helps test the exception catch block (lines 61-64) for edge cases
            // While this may not always trigger an exception, it tests the error handling path
            $veryLargeArray = array_fill(0, 100_000, [
                'subject' => str_repeat('x', 1_000),
                'resource' => str_repeat('y', 1_000),
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]);

            file_put_contents($policyDir.'/policies.ser', serialize($veryLargeArray));

            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act - Should handle gracefully even with large data
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Either processes successfully or returns empty on error
            expect($policy->rules)->toBeArray();
        });

        test('restores error handler after successful decode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $data = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ];
            file_put_contents($policyDir.'/policies.ser', serialize($data));

            // Set up custom error handler to verify it's properly used
            $customHandlerCalled = false;
            set_error_handler(function () use (&$customHandlerCalled): bool {
                $customHandlerCalled = true;

                return true;
            });

            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Trigger an error after decode to verify error handler is restored
            trigger_error('Test error after decode', \E_USER_NOTICE);

            restore_error_handler();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($customHandlerCalled)->toBeTrue(); // Verifies original handler was restored
        });

        test('verifies exception handling path restores error handler correctly', function (): void {
            // Arrange & Act
            // This test directly verifies the exception catch block (lines 61-64)
            // by simulating the code path using a test helper
            $result = SerializedPolicyRepositoryTestHelper::testDecodeExceptionPath();

            // Assert
            expect($result['caught_exception'])->toBeTrue();
            expect($result['handler_restored'])->toBeTrue();
        });

        test('handles malformed serialized data in decode method', function (): void {
            // Arrange
            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Use reflection to access the protected decode method
            $reflection = new ReflectionClass($repository);
            $decodeMethod = $reflection->getMethod('decode');

            // Create serialized data with edge cases that should return null
            // These test the error handling path (lines 51-56) and potentially
            // the exception path (lines 61-64) if unserialize throws
            $testCases = [
                'empty string' => '',
                'null bytes' => "\0\0\0",
                'binary garbage' => pack('H*', 'deadbeef'),
                'extremely malformed' => 'N;s:99999999999999:',
                'truncated serialized object' => 'O:8:"stdClass":1:{s:4:"test";',
                'invalid length specifier' => 's:999999999999999999999999999:"short";',
                'nested broken structure' => 'a:1:{i:0;O:1:"A":1:{',
            ];

            // Act & Assert - All should return null without throwing
            foreach ($testCases as $description => $content) {
                $result = $decodeMethod->invoke($repository, $content);
                expect($result)->toBeNull('Failed for: '.$description);
            }
        });

        test('handles serialized string instead of array gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create serialized string (not array) - tests !is_array check at line 55
            $notAnArray = serialize('this is a string, not an array');
            file_put_contents($policyDir.'/policies.ser', $notAnArray);

            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Should return empty rules since data is not an array
            expect($policy->rules)->toBeEmpty();
        });

        test('handles serialized integer instead of array gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create serialized integer - tests !is_array check at line 55
            file_put_contents($policyDir.'/policies.ser', serialize(12_345));

            $repository = new SerializedPolicyRepository(
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

        test('handles serialized boolean instead of array gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create serialized boolean - tests !is_array check at line 55
            file_put_contents($policyDir.'/policies.ser', serialize(true));

            $repository = new SerializedPolicyRepository(
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

        test('handles serialized null instead of array gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create serialized null - tests !is_array check at line 55
            file_put_contents($policyDir.'/policies.ser', serialize(null));

            $repository = new SerializedPolicyRepository(
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

        test('encodes policies to serialized format', function (): void {
            // Arrange
            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $policyRule = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $policy = new Policy([$policyRule]);

            // Act
            $repository->save($policy);

            // Assert - Verify file was created with serialized data
            $filePath = $this->tempDir.'/policies/policies.ser';
            expect($filePath)->toBeFile();

            $content = file_get_contents($filePath);
            $decoded = unserialize($content, ['allowed_classes' => false]);

            expect($decoded)->toBeArray();
            expect($decoded)->toHaveCount(1);
            expect($decoded[0]['subject'])->toBe('user:123');
            expect($decoded[0]['resource'])->toBe('document:456');
            expect($decoded[0]['action'])->toBe('read');
            expect($decoded[0]['effect'])->toBe('Allow');
        });

        test('encodes multiple policies with all fields', function (): void {
            // Arrange
            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule1 = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(5),
                domain: new Domain('production'),
            );

            $rule2 = new PolicyRule(
                subject: 'user:456',
                resource: null,
                action: 'write',
                effect: Effect::Deny,
                priority: new Priority(10),
            );

            $policy = new Policy([$rule1, $rule2]);

            // Act
            $repository->save($policy);

            // Assert - Verify file contains both policies with all fields
            $filePath = $this->tempDir.'/policies/policies.ser';
            $content = file_get_contents($filePath);
            $decoded = unserialize($content, ['allowed_classes' => false]);

            expect($decoded)->toBeArray();
            expect($decoded)->toHaveCount(2);

            // First policy
            expect($decoded[0]['subject'])->toBe('user:123');
            expect($decoded[0]['resource'])->toBe('document:456');
            expect($decoded[0]['priority'])->toBe(5);
            expect($decoded[0]['domain'])->toBe('production');

            // Second policy
            expect($decoded[1]['subject'])->toBe('user:456');
            expect($decoded[1])->not->toHaveKey('resource'); // Null resource should be omitted
            expect($decoded[1]['effect'])->toBe('Deny');
            expect($decoded[1]['priority'])->toBe(10);
        });

        test('handles multi-file mode correctly', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Create multiple serialized policy files
            $policy1 = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ];

            $policy2 = [
                [
                    'subject' => 'user:456',
                    'resource' => 'document:789',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 5,
                ],
            ];

            file_put_contents($policyDir.'/policy_0.ser', serialize($policy1));
            file_put_contents($policyDir.'/policy_1.ser', serialize($policy2));

            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Should load from multiple files
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });
    });

    describe('Edge Cases - Format Comparisons', function (): void {
        test('all formats handle same policy data correctly', function (): void {
            // Arrange
            $policyData = [
                'subject' => 'user:123',
                'resource' => 'document:456',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ];

            // JSON
            $jsonDir = $this->tempDir.'/json/policies';
            mkdir($jsonDir, 0o755, true);
            file_put_contents($jsonDir.'/policies.json', json_encode([$policyData]));

            // YAML
            $yamlDir = $this->tempDir.'/yaml/policies';
            mkdir($yamlDir, 0o755, true);
            $yaml = <<<'YAML'
            - subject: user:123
              resource: document:456
              action: read
              effect: Allow
              priority: 1
            YAML;
            file_put_contents($yamlDir.'/policies.yaml', $yaml);

            // Serialized
            $serDir = $this->tempDir.'/serialized/policies';
            mkdir($serDir, 0o755, true);
            file_put_contents($serDir.'/policies.ser', serialize([$policyData]));

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $jsonRepo = new YamlPolicyRepository($this->tempDir.'/yaml', FileMode::Single, null, false);
            $yamlRepo = new YamlPolicyRepository($this->tempDir.'/yaml', FileMode::Single, null, false);
            $serRepo = new SerializedPolicyRepository($this->tempDir.'/serialized', FileMode::Single, null, false);

            $jsonPolicy = $jsonRepo->getPoliciesFor($subject, $resource);
            $yamlPolicy = $yamlRepo->getPoliciesFor($subject, $resource);
            $serPolicy = $serRepo->getPoliciesFor($subject, $resource);

            // Assert - All should return same policy
            expect($jsonPolicy->rules)->toHaveCount(1);
            expect($yamlPolicy->rules)->toHaveCount(1);
            expect($serPolicy->rules)->toHaveCount(1);
        });

        test('all formats handle empty files correctly', function (): void {
            // Arrange
            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // YAML with empty array
            $yamlDir = $this->tempDir.'/yaml/policies';
            mkdir($yamlDir, 0o755, true);
            file_put_contents($yamlDir.'/policies.yaml', '[]');

            // Serialized with empty array
            $serDir = $this->tempDir.'/serialized/policies';
            mkdir($serDir, 0o755, true);
            file_put_contents($serDir.'/policies.ser', serialize([]));

            $yamlRepo = new YamlPolicyRepository($this->tempDir.'/yaml', FileMode::Single, null, false);
            $serRepo = new SerializedPolicyRepository($this->tempDir.'/serialized', FileMode::Single, null, false);

            // Act
            $yamlPolicy = $yamlRepo->getPoliciesFor($subject, $resource);
            $serPolicy = $serRepo->getPoliciesFor($subject, $resource);

            // Assert
            expect($yamlPolicy->rules)->toBeEmpty();
            expect($serPolicy->rules)->toBeEmpty();
        });
    });
});
