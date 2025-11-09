<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Exceptions\InvalidPolicyFileFormatException;
use Patrol\Core\Exceptions\PolicyFileNotFoundException;
use Patrol\Core\Exceptions\StorageVersionNotFoundException;
use Patrol\Core\Storage\JsonPolicyRepository;
use Patrol\Core\Storage\SerializedPolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Tests\Helpers\FilesystemHelper;

describe('JsonPolicyRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_json_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        test('retrieves policies matching subject and resource in single file mode', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:bob',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            $rule = $result->rules[0];
            expect($rule->subject)->toBe('user:alice');
            expect($rule->resource)->toBe('document:123');
            expect($rule->action)->toBe('read');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(1);
        });

        test('retrieves policies with wildcard subject', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => '*',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:anyone');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('*');
        });

        test('retrieves policies with wildcard resource', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => '*',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:any', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->resource)->toBe('*');
        });

        test('retrieves policies without resource field', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'action' => 'admin',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->resource)->toBeNull();
        });

        test('retrieves policies with domain', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'domain' => 'tenant:acme',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($result->rules[0]->domain->id)->toBe('tenant:acme');
        });

        test('retrieves policies with default priority when not specified', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->priority->value)->toBe(1);
        });

        test('retrieves deny effect policies', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'delete',
                    'effect' => 'Deny',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->effect)->toBe(Effect::Deny);
        });

        test('retrieves multiple matching policies', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 2,
                ],
                [
                    'subject' => 'user:bob',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Deny',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(2);
            expect($result->rules[0]->action)->toBe('read');
            expect($result->rules[1]->action)->toBe('write');
        });

        test('loads policies from multiple files in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $policy1 = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            $policy2 = [
                'subject' => 'user:alice',
                'resource' => 'document:456',
                'action' => 'write',
                'effect' => 'Allow',
            ];

            file_put_contents($policyDir.'/policy1.json', json_encode($policy1));
            file_put_contents($policyDir.'/policy2.json', json_encode($policy2));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->resource)->toBe('document:123');
        });

        test('retrieves policies with specific version', function (): void {
            // Arrange
            $versionDir = $this->tempDir.'/policies/1.0.0';
            mkdir($versionDir, 0o755, true);

            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            file_put_contents($versionDir.'/policies.json', json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '1.0.0',
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
        });

        test('retrieves policies from latest version automatically', function (): void {
            // Arrange
            $version1Dir = $this->tempDir.'/policies/1.0.0';
            $version2Dir = $this->tempDir.'/policies/2.0.0';
            mkdir($version1Dir, 0o755, true);
            mkdir($version2Dir, 0o755, true);

            $oldPolicies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Deny',
                ],
            ];

            $newPolicies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            file_put_contents($version1Dir.'/policies.json', json_encode($oldPolicies));
            file_put_contents($version2Dir.'/policies.json', json_encode($newPolicies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->effect)->toBe(Effect::Allow);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when policy file does not exist in single file mode', function (): void {
            // Arrange
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act & Assert
            expect(fn (): Policy => $repository->getPoliciesFor($subject, $resource))
                ->toThrow(PolicyFileNotFoundException::class);
        });

        test('throws exception when policy file contains invalid JSON', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, 'invalid json {{{');

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act & Assert
            expect(fn (): Policy => $repository->getPoliciesFor($subject, $resource))
                ->toThrow(InvalidPolicyFileFormatException::class);
        });

        test('throws exception when policy file contains non-array data', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode('not an array'));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act & Assert
            expect(fn (): Policy => $repository->getPoliciesFor($subject, $resource))
                ->toThrow(InvalidPolicyFileFormatException::class);
        });

        test('throws exception when specified version does not exist', function (): void {
            // Arrange
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '999.0.0',
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act & Assert
            expect(fn (): Policy => $repository->getPoliciesFor($subject, $resource))
                ->toThrow(StorageVersionNotFoundException::class);
        });

        test('returns empty policy when no policies match subject', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:bob',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('returns empty policy when no policies match resource', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:456',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns empty policy when directory does not exist in multiple file mode', function (): void {
            // Arrange
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('returns empty policy when no JSON files exist in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('handles files gracefully in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $validPolicy1 = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            $validPolicy2 = [
                'subject' => 'user:bob',
                'resource' => 'document:456',
                'action' => 'write',
                'effect' => 'Allow',
            ];

            file_put_contents($policyDir.'/policy1.json', json_encode($validPolicy1));
            file_put_contents($policyDir.'/policy2.json', json_encode($validPolicy2));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('skips files with invalid JSON in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $validPolicy = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            file_put_contents($policyDir.'/valid.json', json_encode($validPolicy));
            file_put_contents($policyDir.'/invalid.json', 'invalid json');

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
        });

        test('handles empty policies array', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode([]));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toBeEmpty();
        });

        test('handles unicode characters in policy data', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:José',
                    'resource' => 'document:文档',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:José');
            $resource = resource('document:文档', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:José');
            expect($result->rules[0]->resource)->toBe('document:文档');
        });

        test('retrieves consistent results across multiple calls', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act - Multiple calls should return consistent results
            $result1 = $repository->getPoliciesFor($subject, $resource);
            $result2 = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result1->rules)->toHaveCount(1);
            expect($result2->rules)->toHaveCount(1);
            expect($result1->rules[0]->subject)->toBe($result2->rules[0]->subject);
        });

        test('handles high priority values', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 9_999,
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->priority->value)->toBe(9_999);
        });

        test('handles negative priority values', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => -100,
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->priority->value)->toBe(-100);
        });

        test('handles semver pre-release versions', function (): void {
            // Arrange
            $versionDir = $this->tempDir.'/policies/2.0.0-beta.1';
            mkdir($versionDir, 0o755, true);

            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            file_put_contents($versionDir.'/policies.json', json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                version: '2.0.0-beta.1',
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
        });

        test('handles multiple versioned directories and selects highest version', function (): void {
            // Arrange
            $version1Dir = $this->tempDir.'/policies/1.0.0';
            $version2Dir = $this->tempDir.'/policies/1.5.0';
            $version3Dir = $this->tempDir.'/policies/2.0.0';
            mkdir($version1Dir, 0o755, true);
            mkdir($version2Dir, 0o755, true);
            mkdir($version3Dir, 0o755, true);

            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            file_put_contents($version1Dir.'/policies.json', json_encode($policies));
            file_put_contents($version2Dir.'/policies.json', json_encode($policies));
            file_put_contents($version3Dir.'/policies.json', json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Should use version 2.0.0
            expect($result->rules)->toHaveCount(1);
        });

        test('ignores non-json files in multiple file mode', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $validPolicy = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            file_put_contents($policyDir.'/valid.json', json_encode($validPolicy));
            file_put_contents($policyDir.'/readme.txt', 'This is not a policy file');
            file_put_contents($policyDir.'/config.xml', '<config></config>');

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
        });

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
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
                cache: $cache,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act - Should hit line 116: return $this->cache[$cacheKey];
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Data comes from cache
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
            expect($result->rules[0]->resource)->toBe('document:123');
            expect($result->rules[0]->priority->value)->toBe(5);
            expect($result->rules[0]->domain->id)->toBe('tenant:test');
        });

        test('handles unreadable file in single file mode by returning empty array', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode([['subject' => 'user:alice', 'action' => 'read', 'effect' => 'Allow']]));

            // Make file unreadable by changing permissions (Unix-only test)
            if (\PHP_OS_FAMILY !== 'Windows') {
                chmod($policyFile, 0o000);
            }

            // Use parent class method via SerializedPolicyRepository that doesn't validate
            $repository = new SerializedPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            try {
                // Act - Should return empty array when file_get_contents fails
                $result = $repository->getPoliciesFor($subject, $resource);

                // Assert - Returns empty policy
                expect($result->rules)->toBeEmpty();
            } finally {
                // Cleanup - restore permissions so file can be deleted
                if (\PHP_OS_FAMILY !== 'Windows') {
                    chmod($policyFile, 0o644);
                }
            }
        })->skipOnCI();

        test('skips unreadable files in multiple file mode covering line 186', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            $validPolicy = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            $unreadablePolicy = [
                'subject' => 'user:bob',
                'resource' => 'document:456',
                'action' => 'write',
                'effect' => 'Allow',
            ];

            file_put_contents($policyDir.'/valid.json', json_encode($validPolicy));
            file_put_contents($policyDir.'/unreadable.json', json_encode($unreadablePolicy));

            // Make second file unreadable to trigger line 186 continue
            $unreadableFile = $policyDir.'/unreadable.json';

            if (\PHP_OS_FAMILY !== 'Windows') {
                chmod($unreadableFile, 0o000);
            }

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

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
        })->skipOnCI();

        test('handles glob failure in multiple file mode', function (): void {
            // Arrange - This scenario is difficult to test reliably as glob rarely fails
            // We test the related scenario where the directory is completely inaccessible
            $policyDir = $this->tempDir.'/policies';
            mkdir($policyDir, 0o755, true);

            // Make directory unreadable/unexecutable (Unix-only test)
            if (\PHP_OS_FAMILY !== 'Windows') {
                chmod($policyDir, 0o000);
            }

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            try {
                // Act - Should handle glob failure gracefully
                $result = $repository->getPoliciesFor($subject, $resource);

                // Assert - Returns empty policy when glob fails
                expect($result->rules)->toBeEmpty();
            } finally {
                // Cleanup - restore permissions
                if (\PHP_OS_FAMILY !== 'Windows') {
                    chmod($policyDir, 0o755);
                }
            }
        });

        test('uses versioned directory path when versioning is enabled in multiple file mode', function (): void {
            // Arrange
            $version1Dir = $this->tempDir.'/policies/1.0.0';
            $version2Dir = $this->tempDir.'/policies/2.0.0';
            mkdir($version1Dir, 0o755, true);
            mkdir($version2Dir, 0o755, true);

            $policy1 = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Deny',
            ];

            $policy2 = [
                'subject' => 'user:alice',
                'resource' => 'document:123',
                'action' => 'read',
                'effect' => 'Allow',
            ];

            // Write single policy objects (not arrays) to test the firstKey === 'subject' path
            file_put_contents($version1Dir.'/policy.json', json_encode($policy1));
            file_put_contents($version2Dir.'/policy.json', json_encode($policy2));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '2.0.0',
                versioningEnabled: true,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act - Should load from version 2.0.0 directory
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - Gets policy from version 2.0.0
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('saves to versioned directory when versioning is enabled in multiple file mode', function (): void {
            // Arrange
            $versionDir = $this->tempDir.'/policies/3.0.0';
            // Version directory must exist for versioning to work
            mkdir($versionDir, 0o755, true);

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                version: '3.0.0',
                versioningEnabled: true,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Assert - Files should be in versioned directory
            expect(file_exists($versionDir.'/policy_0.json'))->toBeTrue();

            $content = file_get_contents($versionDir.'/policy_0.json');
            $decoded = json_decode($content, true);
            expect($decoded[0]['subject'])->toBe('user:alice');
        });

        test('saves policy to single file with proper JSON encoding', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.json';

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: new Domain('tenant:acme'),
                ),
                new PolicyRule(
                    subject: 'user:bob',
                    resource: null,
                    action: 'admin',
                    effect: Effect::Deny,
                    priority: new Priority(2),
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Assert - File should exist and contain properly formatted JSON
            expect(file_exists($policyFile))->toBeTrue();

            $content = file_get_contents($policyFile);
            expect($content)->toBeString();

            // Verify JSON is valid and formatted
            $decoded = json_decode($content, true);
            expect($decoded)->toBeArray();
            expect($decoded)->toHaveCount(2);

            // Verify first policy rule
            expect($decoded[0]['subject'])->toBe('user:alice');
            expect($decoded[0]['resource'])->toBe('document:123');
            expect($decoded[0]['action'])->toBe('read');
            expect($decoded[0]['effect'])->toBe('Allow');
            expect($decoded[0]['priority'])->toBe(1);
            expect($decoded[0]['domain'])->toBe('tenant:acme');

            // Verify second policy rule (no resource, no domain)
            expect($decoded[1]['subject'])->toBe('user:bob');
            expect($decoded[1])->not->toHaveKey('resource');
            expect($decoded[1]['action'])->toBe('admin');
            expect($decoded[1]['effect'])->toBe('Deny');
            expect($decoded[1]['priority'])->toBe(2);
            expect($decoded[1])->not->toHaveKey('domain');

            // Verify JSON formatting (pretty print, unescaped slashes)
            expect($content)->toContain("\n");
            expect($content)->not->toContain('\/');
        });

        test('saves policy to multiple files with proper JSON encoding', function (): void {
            // Arrange
            $policyDir = $this->tempDir.'/policies';

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $rules = [
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(10),
                ),
                new PolicyRule(
                    subject: 'user:bob',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(20),
                    domain: new Domain('tenant:xyz'),
                ),
            ];

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);

            // Assert - Multiple files should be created
            expect(file_exists($policyDir.'/policy_0.json'))->toBeTrue();
            expect(file_exists($policyDir.'/policy_1.json'))->toBeTrue();

            // Verify first policy file
            $content0 = file_get_contents($policyDir.'/policy_0.json');
            $decoded0 = json_decode($content0, true);
            expect($decoded0)->toBeArray();
            expect($decoded0)->toHaveCount(1);
            expect($decoded0[0]['subject'])->toBe('user:alice');
            expect($decoded0[0]['action'])->toBe('read');
            expect($decoded0[0]['priority'])->toBe(10);

            // Verify second policy file
            $content1 = file_get_contents($policyDir.'/policy_1.json');
            $decoded1 = json_decode($content1, true);
            expect($decoded1)->toBeArray();
            expect($decoded1)->toHaveCount(1);
            expect($decoded1[0]['subject'])->toBe('user:bob');
            expect($decoded1[0]['action'])->toBe('write');
            expect($decoded1[0]['priority'])->toBe(20);
            expect($decoded1[0]['domain'])->toBe('tenant:xyz');

            // Verify JSON formatting
            expect($content0)->toContain("\n");
            expect($content1)->toContain("\n");
        });

        test('soft delete methods are no-ops and return expected values', function (): void {
            // Arrange
            $basePath = sys_get_temp_dir().'/patrol_test_'.uniqid('', true);
            $repository = new JsonPolicyRepository(
                basePath: $basePath,
                fileMode: FileMode::Single,
                version: null,
                versioningEnabled: false,
                cache: [],
            );

            // Create test file
            $filePath = $basePath.'/policies/policies.json';
            mkdir(dirname($filePath), 0o755, true);
            file_put_contents($filePath, json_encode([[
                'subject' => 'user:test',
                'resource' => 'doc:test',
                'action' => 'read',
                'effect' => 'Allow',
                'priority' => 1,
            ]]));

            $subject = new Subject('user:test');
            $resource = new Resource('doc:test', 'doc');

            // Act & Assert - soft delete operations should not throw
            $repository->softDelete('id-1');
            $repository->restore('id-2');
            $repository->forceDelete('id-3');

            // Act & Assert - getTrashed should return empty
            $trashed = $repository->getTrashed();
            expect($trashed->rules)->toBeEmpty();

            // Act & Assert - getWithTrashed should behave like getPoliciesFor
            $normal = $repository->getPoliciesFor($subject, $resource);
            $withTrashed = $repository->getWithTrashed($subject, $resource);
            expect($withTrashed->rules)->toHaveCount(1);
            expect($normal->rules[0]->subject)->toBe($withTrashed->rules[0]->subject);

            // Cleanup
            unlink($filePath);
            rmdir(dirname($filePath));
            rmdir($basePath);
        });

        test('saveMany returns early when given empty array (line 114-116)', function (): void {
            // Arrange
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - saveMany with empty array should return immediately without saving
            $repository->saveMany([]);

            // Assert - No file should be created
            $policyFile = $this->tempDir.'/policies/policies.json';
            expect(file_exists($policyFile))->toBeFalse();
        });

        test('saveMany combines multiple policies and saves them (lines 118-128)', function (): void {
            // Arrange
            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy1 = new Policy([
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            $policy2 = new Policy([
                new PolicyRule(
                    subject: 'user:bob',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(2),
                ),
                new PolicyRule(
                    subject: 'user:charlie',
                    resource: 'document:789',
                    action: 'delete',
                    effect: Effect::Allow,
                    priority: new Priority(3),
                    domain: new Domain('tenant:acme'),
                ),
            ]);

            // Act - saveMany should combine all rules into one policy
            $repository->saveMany([$policy1, $policy2]);

            // Assert - All rules should be saved in the file
            $policyFile = $this->tempDir.'/policies/policies.json';
            expect(file_exists($policyFile))->toBeTrue();

            $content = file_get_contents($policyFile);
            $decoded = json_decode($content, true);

            expect($decoded)->toHaveCount(3);
            expect($decoded[0]['subject'])->toBe('user:alice');
            expect($decoded[1]['subject'])->toBe('user:bob');
            expect($decoded[2]['subject'])->toBe('user:charlie');
            expect($decoded[2]['domain'])->toBe('tenant:acme');

            // Verify policies can be retrieved
            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');
            $result = $repository->getPoliciesFor($subject, $resource);
            expect($result->rules)->toHaveCount(1);
        });

        test('deleteMany is a no-op for file repositories (lines 140-145)', function (): void {
            // Arrange - Create repository with existing policies
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:bob',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Deny',
                    'priority' => 2,
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act - deleteMany should do nothing
            $repository->deleteMany(['rule-id-1', 'rule-id-2', 'rule-id-3']);

            // Assert - File should still exist with all policies intact
            expect(file_exists($policyFile))->toBeTrue();
            $content = file_get_contents($policyFile);
            $decoded = json_decode($content, true);
            expect($decoded)->toHaveCount(2);

            // Verify policies are still retrievable
            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');
            $result = $repository->getPoliciesFor($subject, $resource);
            expect($result->rules)->toHaveCount(1);
        });

        test('getPoliciesForBatch retrieves policies for multiple resources (lines 231-237)', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:456',
                    'action' => 'write',
                    'effect' => 'Allow',
                    'priority' => 2,
                ],
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:789',
                    'action' => 'delete',
                    'effect' => 'Deny',
                    'priority' => 3,
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resources = [
                resource('document:123', 'document'),
                resource('document:456', 'document'),
                resource('document:789', 'document'),
            ];

            // Act - getPoliciesForBatch should return map of resource ID to policy
            $result = $repository->getPoliciesForBatch($subject, $resources);

            // Assert - Should have entry for each resource
            expect($result)->toHaveCount(3);
            expect($result)->toHaveKey('document:123');
            expect($result)->toHaveKey('document:456');
            expect($result)->toHaveKey('document:789');

            // Verify each policy has correct rules
            expect($result['document:123']->rules)->toHaveCount(1);
            expect($result['document:123']->rules[0]->action)->toBe('read');

            expect($result['document:456']->rules)->toHaveCount(1);
            expect($result['document:456']->rules[0]->action)->toBe('write');

            expect($result['document:789']->rules)->toHaveCount(1);
            expect($result['document:789']->rules[0]->action)->toBe('delete');
        });

        test('getPoliciesForBatch returns empty policies for resources without matches', function (): void {
            // Arrange
            $policies = [
                [
                    'subject' => 'user:alice',
                    'resource' => 'document:123',
                    'action' => 'read',
                    'effect' => 'Allow',
                ],
            ];

            $policyFile = $this->tempDir.'/policies/policies.json';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, json_encode($policies));

            $repository = new JsonPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resources = [
                resource('document:123', 'document'),
                resource('document:999', 'document'), // No matching policy
            ];

            // Act
            $result = $repository->getPoliciesForBatch($subject, $resources);

            // Assert
            expect($result)->toHaveCount(2);
            expect($result['document:123']->rules)->toHaveCount(1);
            expect($result['document:999']->rules)->toBeEmpty();
        });
    });
});
