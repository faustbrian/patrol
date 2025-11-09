<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\XmlPolicyRepository;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Saloon\XmlWrangler\XmlReader;
use Tests\Helpers\FilesystemHelper;

/**
 * Comprehensive test suite for XmlPolicyRepository.
 *
 * Achieves 100% code coverage by testing:
 * - decode() method:
 *   - Single policy decoding with wrapping (lines 59-61)
 *   - Multiple policies decoding (line 65)
 *   - Missing policies key handling (line 68)
 *   - Invalid XML exception handling (lines 69-71)
 * - encode() method:
 *   - XML encoding with XmlWriter (lines 86-90)
 *   - Single and multiple policy encoding
 *   - Empty array encoding
 * - getExtension() method:
 *   - Returns 'xml' extension (lines 99-102)
 *
 * Uses direct reflection-based testing for protected methods to ensure
 * all code paths are executed, plus integration tests via public API.
 *
 * @coversDefaultClass \Patrol\Core\Storage\XmlPolicyRepository
 */
describe('XmlPolicyRepository', function (): void {
    beforeEach(function (): void {
        // Skip all tests if XML package not installed
        if (!class_exists(XmlReader::class)) {
            $this->markTestSkipped('Saloon XML Wrangler package not installed');
        }

        $this->tempDir = sys_get_temp_dir().'/patrol_xml_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        if (is_dir($this->tempDir)) {
            FilesystemHelper::deleteDirectory($this->tempDir);
        }
    });

    describe('decode() method - Single Policy', function (): void {
        test('decodes single policy wrapped in array', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Single policy structure - will have 'subject' key at top level
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

            // Act - This exercises lines 59-61 (single policy branch)
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });
    });

    describe('decode() method - Multiple Policies', function (): void {
        test('decodes multiple policies from array', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Multiple policies structure - 'policy' contains array of policies
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
                <policy>
                    <subject>user:789</subject>
                    <resource>document:789</resource>
                    <action>write</action>
                    <effect>Deny</effect>
                    <priority>5</priority>
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

            $subject1 = new Subject('user:123');
            $subject2 = new Subject('user:789');
            $resource1 = new Resource('document:456', 'document');
            $resource2 = new Resource('document:789', 'document');

            // Act - This exercises lines 65 (multiple policies array branch)
            $policy1 = $repository->getPoliciesFor($subject1, $resource1);
            $policy2 = $repository->getPoliciesFor($subject2, $resource2);

            // Assert
            expect($policy1->rules)->toHaveCount(1);
            expect($policy1->rules[0]->subject)->toBe('user:123');
            expect($policy1->rules[0]->effect)->toBe(Effect::Allow);

            expect($policy2->rules)->toHaveCount(1);
            expect($policy2->rules[0]->subject)->toBe('user:789');
            expect($policy2->rules[0]->effect)->toBe(Effect::Deny);
        });
    });

    describe('decode() method - Error Handling', function (): void {
        test('handles malformed xml gracefully', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Invalid XML - unclosed tags
            file_put_contents($policyDir.'/policies.xml', '<invalid><unclosed>');

            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act - This exercises lines 69-71 (exception handling)
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Should return empty policy on parse error
            expect($policy->rules)->toBeEmpty();
        });

        test('handles xml without policy key', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // XML without expected structure
            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <other>something</other>
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

            // Act - This exercises line 68 (return null when no policy key)
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('handles empty xml file', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            file_put_contents($policyDir.'/policies.xml', '');

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

    describe('encode() method - Single File Mode', function (): void {
        test('encodes single policy to xml via save', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $policy = new Policy([$rule]);

            // Act - This exercises lines 86-90 (encode method)
            $repository->save($policy);

            // Assert - Verify file was created and contains valid XML
            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('<policies>');
            expect($content)->toContain('<policy>');
            expect($content)->toContain('<subject>user:123</subject>');
            expect($content)->toContain('<resource>document:456</resource>');
            expect($content)->toContain('<action>read</action>');
            expect($content)->toContain('<effect>Allow</effect>');
            expect($content)->toContain('<priority>1</priority>');
        });

        test('encodes multiple policies to xml via save', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
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
                priority: new Priority(1),
            );

            $rule2 = new PolicyRule(
                subject: 'user:789',
                resource: 'document:789',
                action: 'write',
                effect: Effect::Deny,
                priority: new Priority(5),
            );

            $policy = new Policy([$rule1, $rule2]);

            // Act - This exercises lines 86-90 (encode method with multiple policies)
            $repository->save($policy);

            // Assert - Verify XML structure
            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('user:123');
            expect($content)->toContain('user:789');
            expect($content)->toContain('Allow');
            expect($content)->toContain('Deny');
        });
    });

    describe('encode() method - Multiple File Mode', function (): void {
        test('encodes each policy to separate xml file', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: null,
                versioningEnabled: false,
            );

            $rule1 = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $rule2 = new PolicyRule(
                subject: 'user:789',
                resource: 'document:789',
                action: 'write',
                effect: Effect::Deny,
                priority: new Priority(5),
            );

            $policy = new Policy([$rule1, $rule2]);

            // Act - This exercises lines 86-90 (encode method in multi-file mode)
            $repository->save($policy);

            // Assert - Verify separate files were created
            $file1Path = $this->tempDir.'/policies/policy_0.xml';
            $file2Path = $this->tempDir.'/policies/policy_1.xml';

            expect(file_exists($file1Path))->toBeTrue();
            expect(file_exists($file2Path))->toBeTrue();

            $content1 = file_get_contents($file1Path);
            $content2 = file_get_contents($file2Path);

            expect($content1)->toContain('user:123');
            expect($content2)->toContain('user:789');
        });
    });

    describe('getExtension() method', function (): void {
        test('returns xml extension', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act - Indirectly test by verifying file creation uses .xml extension
            $rule = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $policy = new Policy([$rule]);
            $repository->save($policy);

            // Assert - This exercises lines 99-102 (getExtension method)
            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();
        });
    });

    describe('Direct decode() Method Testing', function (): void {
        test('decode returns array for multiple policies', function (): void {
            // Arrange - Create repository with reflection access
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Create XML with multiple policies
            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>user:123</subject>
                    <action>read</action>
                    <effect>Allow</effect>
                </policy>
                <policy>
                    <subject>user:456</subject>
                    <action>write</action>
                    <effect>Deny</effect>
                </policy>
            </policies>
            XML;

            // Act - Use reflection to call protected decode method
            $reflection = new ReflectionClass($repository);
            $decodeMethod = $reflection->getMethod('decode');

            $result = $decodeMethod->invoke($repository, $xml);

            // Assert - This directly tests line 65 (return $policies)
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0]['subject'])->toBe('user:123');
            expect($result[1]['subject'])->toBe('user:456');
        });

        test('decode returns wrapped array for single policy', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Single policy XML
            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>user:123</subject>
                    <action>read</action>
                    <effect>Allow</effect>
                </policy>
            </policies>
            XML;

            // Act - Use reflection to call protected decode method
            $reflection = new ReflectionClass($repository);
            $decodeMethod = $reflection->getMethod('decode');

            $result = $decodeMethod->invoke($repository, $xml);

            // Assert - This tests lines 59-61 (single policy wrapped in array)
            expect($result)->toBeArray();
            expect($result)->toHaveCount(1);
            expect($result[0]['subject'])->toBe('user:123');
        });

        test('decode returns null for xml without policies key', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <root>
                <other>data</other>
            </root>
            XML;

            // Act - Use reflection to call protected decode method
            $reflection = new ReflectionClass($repository);
            $decodeMethod = $reflection->getMethod('decode');

            $result = $decodeMethod->invoke($repository, $xml);

            // Assert - This tests line 68 (return null when no policy key)
            expect($result)->toBeNull();
        });

        test('decode returns null for malformed xml', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act - Use reflection to call protected decode method
            $reflection = new ReflectionClass($repository);
            $decodeMethod = $reflection->getMethod('decode');

            $result = $decodeMethod->invoke($repository, '<invalid><unclosed>');

            // Assert - This tests lines 69-71 (exception handling)
            expect($result)->toBeNull();
        });
    });

    describe('Direct encode() Method Testing', function (): void {
        test('encode generates valid xml from array', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $data = [
                [
                    'subject' => 'user:123',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:789',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 5,
                ],
            ];

            // Act - Use reflection to call protected encode method
            $reflection = new ReflectionClass($repository);
            $encodeMethod = $reflection->getMethod('encode');

            $result = $encodeMethod->invoke($repository, $data);

            // Assert - This directly tests lines 86-90
            expect($result)->toBeString();
            expect($result)->toContain('<?xml version="1.0"');
            expect($result)->toContain('<policies>');
            expect($result)->toContain('<policy>');
            expect($result)->toContain('<subject>user:123</subject>');
            expect($result)->toContain('<subject>user:789</subject>');
            expect($result)->toContain('</policies>');
        });

        test('encode handles empty array', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act - Use reflection to call protected encode method
            $reflection = new ReflectionClass($repository);
            $encodeMethod = $reflection->getMethod('encode');

            $result = $encodeMethod->invoke($repository, []);

            // Assert
            expect($result)->toBeString();
            expect($result)->toContain('<policies>');
        });

        test('encode handles single policy', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $data = [
                [
                    'subject' => 'user:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ];

            // Act
            $reflection = new ReflectionClass($repository);
            $encodeMethod = $reflection->getMethod('encode');

            $result = $encodeMethod->invoke($repository, $data);

            // Assert - This tests lines 86-90 with single policy
            expect($result)->toBeString();
            expect($result)->toContain('<subject>user:123</subject>');
        });
    });

    describe('Direct getExtension() Method Testing', function (): void {
        test('getExtension returns xml', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // Act - Use reflection to call protected getExtension method
            $reflection = new ReflectionClass($repository);
            $getExtensionMethod = $reflection->getMethod('getExtension');

            $result = $getExtensionMethod->invoke($repository);

            // Assert - This directly tests lines 99-102
            expect($result)->toBe('xml');
        });
    });

    describe('Coverage for Specific Code Paths', function (): void {
        test('decode method line 65 - returns array for multiple policies without subject key', function (): void {
            // Arrange - Create XML that results in multiple policies
            // This will make XmlReader return ['policies' => ['policy' => [0 => [...], 1 => [...]]]]
            // where $policies will be [0 => [...], 1 => [...]] with NO 'subject' key at top level
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>admin:999</subject>
                    <resource>system:config</resource>
                    <action>manage</action>
                    <effect>Allow</effect>
                    <priority>10</priority>
                </policy>
                <policy>
                    <subject>guest:000</subject>
                    <resource>public:page</resource>
                    <action>view</action>
                    <effect>Deny</effect>
                    <priority>20</priority>
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

            // Act - This MUST execute line 65: return $policies;
            // because the array has numeric keys [0, 1], not 'subject' key
            $subject = new Subject('admin:999');
            $resource = new Resource('system:config', 'system');
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Verify we got the policies back
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('admin:999');
            expect($result->rules[0]->priority->value)->toBe(10);
        });

        test('decode method line 68 - returns null for missing policies structure', function (): void {
            // Arrange - Create XML without 'policies' key
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <root>
                <something>else</something>
            </root>
            XML;

            file_put_contents($policyDir.'/policies.xml', $xml);

            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('any:user');
            $resource = new Resource('any:resource', 'any');

            // Act - This MUST execute line 68: return null;
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Should return empty policy when decode returns null
            expect($result->rules)->toBeEmpty();
        });

        test('encode method lines 86-90 - creates XmlWriter and encodes data', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule = new PolicyRule(
                subject: 'coverage:test',
                resource: 'line:86-90',
                action: 'execute',
                effect: Effect::Allow,
                priority: new Priority(100),
            );

            $policy = new Policy([$rule]);

            // Act - This MUST execute lines 86-90 in encode() method
            // Line 86: $writer = new XmlWriter();
            // Lines 88-90: return $writer->write('policies', ['policy' => $data]);
            $repository->save($policy);

            // Assert - Verify the file was created with correct XML structure
            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);

            // Verify XmlWriter was used by checking XML structure
            expect($content)->toContain('<?xml version="1.0"');
            expect($content)->toContain('<policies>');
            expect($content)->toContain('<policy>');
            expect($content)->toContain('<subject>coverage:test</subject>');
            expect($content)->toContain('<resource>line:86-90</resource>');
            expect($content)->toContain('</policy>');
            expect($content)->toContain('</policies>');
        });

        test('comprehensive coverage - all encode and decode paths', function (): void {
            // This test exercises ALL missing lines in a single comprehensive test
            // to ensure maximum coverage tracking

            // Part 1: Test encode (lines 86-90) with multiple policies
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:alpha',
                    resource: 'doc:100',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(5),
                ),
                new PolicyRule(
                    subject: 'user:beta',
                    resource: 'doc:200',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(10),
                ),
            ];

            $policy = new Policy($rules);

            // Save - this executes encode() lines 86-90
            $repository->save($policy);

            // Part 2: Test decode line 65 (return $policies for multiple policies)
            // Read back the policies we just saved
            $subject1 = new Subject('user:alpha');
            $resource1 = new Resource('doc:100', 'doc');
            $result1 = $repository->getPoliciesFor($subject1, $resource1);

            expect($result1->rules)->toHaveCount(1);
            expect($result1->rules[0]->subject)->toBe('user:alpha');

            $subject2 = new Subject('user:beta');
            $resource2 = new Resource('doc:200', 'doc');
            $result2 = $repository->getPoliciesFor($subject2, $resource2);

            expect($result2->rules)->toHaveCount(1);
            expect($result2->rules[0]->subject)->toBe('user:beta');

            // Part 3: Test decode line 68 (return null for bad structure)
            $policyDir = $this->tempDir.'/policies';
            $badFilePath = $policyDir.'/bad_policies.xml';

            // Create XML file without proper structure
            file_put_contents($badFilePath, '<?xml version="1.0"?><root><invalid>data</invalid></root>');

            // Rename it to replace the good file temporarily
            $goodFilePath = $policyDir.'/policies.xml';
            $backupPath = $policyDir.'/backup.xml';
            rename($goodFilePath, $backupPath);
            rename($badFilePath, $goodFilePath);

            // Create new repository instance to clear any caching
            $repository2 = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // This should hit line 68 (return null)
            $subject3 = new Subject('any:user');
            $resource3 = new Resource('any:doc', 'any');
            $result3 = $repository2->getPoliciesFor($subject3, $resource3);

            expect($result3->rules)->toBeEmpty();

            // Clean up
            rename($goodFilePath, $badFilePath);
            rename($backupPath, $goodFilePath);
        });
    });

    describe('Additional Coverage for Missing Lines', function (): void {
        test('decode line 65 - direct return of policies array when not single policy', function (): void {
            // This test specifically targets line 65: return $policies;
            // The key is that $policies must NOT have a 'subject' key but IS an array
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // XML with 2 policies - XmlReader will parse this as an indexed array [0 => [...], 1 => [...]]
            // So $policies will be [0 => [...], 1 => [...]] with NO 'subject' key
            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <policy>
                    <subject>line65:user1</subject>
                    <action>read</action>
                    <effect>Allow</effect>
                    <priority>1</priority>
                </policy>
                <policy>
                    <subject>line65:user2</subject>
                    <action>write</action>
                    <effect>Deny</effect>
                    <priority>2</priority>
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

            // This will trigger decode() which should hit line 65
            $subject = new Subject('line65:user1');
            $resource = new Resource('any:resource', 'any');
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Verify we got the policy
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('line65:user1');
        });

        test('decode lines 66-68 - return null when policies key exists but policy key missing', function (): void {
            // This test targets lines 66-68: the closing brace and return null
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // XML with 'policies' key but NO 'policy' key inside
            $xml = <<<'XML'
            <?xml version="1.0"?>
            <policies>
                <notpolicy>
                    <subject>should:not:work</subject>
                </notpolicy>
            </policies>
            XML;

            file_put_contents($policyDir.'/policies.xml', $xml);

            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            // This should trigger line 68: return null
            $subject = new Subject('any:user');
            $resource = new Resource('any:resource', 'any');
            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toBeEmpty();
        });

        test('decode line 68 - return null when policies key completely missing', function (): void {
            // Another path to line 68
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $xml = <<<'XML'
            <?xml version="1.0"?>
            <root>
                <data>value</data>
            </root>
            XML;

            file_put_contents($policyDir.'/policies.xml', $xml);

            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $subject = new Subject('any:user');
            $resource = new Resource('any:resource', 'any');
            $policy = $repository->getPoliciesFor($subject, $resource);

            expect($policy->rules)->toBeEmpty();
        });

        test('encode lines 86-90 - creates xml via XmlWriter with single policy', function (): void {
            // This test specifically targets lines 86-90
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule = new PolicyRule(
                subject: 'encode:test:single',
                resource: 'resource:encode',
                action: 'test',
                effect: Effect::Allow,
                priority: new Priority(42),
            );

            $policy = new Policy([$rule]);

            // This will call encode() which executes lines 86-90
            $repository->save($policy);

            // Verify the encoded XML
            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('<policies>');
            expect($content)->toContain('<policy>');
            expect($content)->toContain('<subject>encode:test:single</subject>');
            expect($content)->toContain('<resource>resource:encode</resource>');
        });

        test('encode lines 86-90 - creates xml via XmlWriter with multiple policies', function (): void {
            // Ensure line 86-90 coverage with multiple policies
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'encode:multi:1',
                    resource: 'res:1',
                    action: 'action1',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
                new PolicyRule(
                    subject: 'encode:multi:2',
                    resource: 'res:2',
                    action: 'action2',
                    effect: Effect::Deny,
                    priority: new Priority(2),
                ),
                new PolicyRule(
                    subject: 'encode:multi:3',
                    resource: 'res:3',
                    action: 'action3',
                    effect: Effect::Allow,
                    priority: new Priority(3),
                ),
            ];

            $policy = new Policy($rules);

            // This will call encode() which executes lines 86-90
            $repository->save($policy);

            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('encode:multi:1');
            expect($content)->toContain('encode:multi:2');
            expect($content)->toContain('encode:multi:3');
        });

        test('encode lines 86-90 - creates xml with empty policy array', function (): void {
            // Edge case: empty array
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $policy = new Policy([]);

            // This will call encode() with empty array
            $repository->save($policy);

            $filePath = $this->tempDir.'/policies/policies.xml';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('<policies>');
            expect($content)->toContain('</policies>');
        });
    });

    describe('Round-trip Encoding/Decoding', function (): void {
        test('can save and reload single policy', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
            );

            $rule = new PolicyRule(
                subject: 'user:123',
                resource: 'document:456',
                action: 'read',
                effect: Effect::Allow,
                priority: new Priority(1),
            );

            $policy = new Policy([$rule]);

            // Act - Save then reload
            $repository->save($policy);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');
            $reloadedPolicy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($reloadedPolicy->rules)->toHaveCount(1);
            expect($reloadedPolicy->rules[0]->subject)->toBe('user:123');
            expect($reloadedPolicy->rules[0]->resource)->toBe('document:456');
            expect($reloadedPolicy->rules[0]->action)->toBe('read');
            expect($reloadedPolicy->rules[0]->effect)->toBe(Effect::Allow);
            expect($reloadedPolicy->rules[0]->priority->value)->toBe(1);
        });

        test('can save and reload multiple policies', function (): void {
            // Arrange
            $repository = new XmlPolicyRepository(
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
                priority: new Priority(1),
            );

            $rule2 = new PolicyRule(
                subject: 'user:789',
                resource: 'document:789',
                action: 'write',
                effect: Effect::Deny,
                priority: new Priority(5),
            );

            $policy = new Policy([$rule1, $rule2]);

            // Act - Save then reload both
            $repository->save($policy);

            $subject1 = new Subject('user:123');
            $resource1 = new Resource('document:456', 'document');
            $reloadedPolicy1 = $repository->getPoliciesFor($subject1, $resource1);

            $subject2 = new Subject('user:789');
            $resource2 = new Resource('document:789', 'document');
            $reloadedPolicy2 = $repository->getPoliciesFor($subject2, $resource2);

            // Assert
            expect($reloadedPolicy1->rules)->toHaveCount(1);
            expect($reloadedPolicy1->rules[0]->effect)->toBe(Effect::Allow);

            expect($reloadedPolicy2->rules)->toHaveCount(1);
            expect($reloadedPolicy2->rules[0]->effect)->toBe(Effect::Deny);
        });
    });
});
