<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Repositories\FilePolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

describe('FilePolicyRepository', function (): void {
    describe('Happy Paths', function (): void {
        test('loads policies from valid json file', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Only exact match, wildcard patterns like document:* don't match document:456
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->resource)->toBe('document:456');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('matches exact subject and resource', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - Only exact match
            expect($policy->rules)->toHaveCount(1);
            $exactMatch = array_values(array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->subject === 'user:123' && $rule->resource === 'document:456'));
            expect($exactMatch)->toHaveCount(1);
        });

        test('matches wildcard subject pattern', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/wildcard_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:999');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2); // Matches * subject rules
            expect($policy->rules[0]->subject)->toBe('*');
            expect($policy->rules[1]->subject)->toBe('*');
        });

        test('matches wildcard resource pattern', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/wildcard_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:999', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(2); // Matches * resource and user:123 with * resource
            $wildcardMatches = array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->resource === '*');
            expect($wildcardMatches)->toHaveCount(2);
        });

        test('matches policies without resource field', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/policies_without_resource.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:123');
            expect($policy->rules[0]->resource)->toBeNull();
        });

        test('loads domain-scoped policies', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/domain_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->domain)->toBeInstanceOf(Domain::class);
            expect($policy->rules[0]->domain->id)->toBe('tenant:1');
        });

        test('handles empty policy file', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/empty_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toBeEmpty();
        });

        test('uses default priority when not specified', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules[0]->priority->value)->toBe(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when file not found', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/nonexistent_file.json';

            // Act & Assert
            expect(fn (): FilePolicyRepository => new FilePolicyRepository($filePath))
                ->toThrow(InvalidArgumentException::class, 'Policy file not found');
        });

        test('throws exception when json is invalid', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/invalid_json.json';

            // Act & Assert
            expect(fn (): FilePolicyRepository => new FilePolicyRepository($filePath))
                ->toThrow(InvalidArgumentException::class, 'Invalid JSON in policy file');
        });

        test('returns empty policy when no rules match subject', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:999');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - No match because user:999 doesn't match user:123, and * doesn't match unless resource is document:*
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when no rules match resource', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:999', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - No match because document:999 doesn't match document:456 or document:*
            expect($policy->rules)->toBeEmpty();
        });

        test('returns empty policy when neither subject nor resource match', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:999');
            $resource = new Resource('project:888', 'project');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - No matches at all
            expect($policy->rules)->toBeEmpty();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles multiple matching policies with different priorities', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/wildcard_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert - should match all 3 rules
            expect($policy->rules)->toHaveCount(3);
            expect($policy->rules[0]->priority->value)->toBe(1);
            expect($policy->rules[1]->priority->value)->toBe(10);
            expect($policy->rules[2]->priority->value)->toBe(100);
        });

        test('combines exact matches with wildcard matches', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/wildcard_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(3);
            $exactSubjectMatches = array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->subject === 'user:123');
            $wildcardSubjectMatches = array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->subject === '*');
            expect($exactSubjectMatches)->toHaveCount(1);
            expect($wildcardSubjectMatches)->toHaveCount(2);
        });

        test('handles both allow and deny effects in same file', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/wildcard_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            $allowRules = array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->effect === Effect::Allow);
            $denyRules = array_filter($policy->rules, fn (PolicyRule $rule): bool => $rule->effect === Effect::Deny);
            expect($allowRules)->toHaveCount(2);
            expect($denyRules)->toHaveCount(1);
        });

        test('matches policies with null resource to any resource', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/policies_without_resource.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource1 = new Resource('document:123', 'document');
            $resource2 = new Resource('project:456', 'project');
            $resource3 = new Resource('file:789', 'file');

            // Act
            $policy1 = $repository->getPoliciesFor($subject, $resource1);
            $policy2 = $repository->getPoliciesFor($subject, $resource2);
            $policy3 = $repository->getPoliciesFor($subject, $resource3);

            // Assert - null resource matches all resources
            expect($policy1->rules)->toHaveCount(1);
            expect($policy2->rules)->toHaveCount(1);
            expect($policy3->rules)->toHaveCount(1);
        });

        test('handles special characters in subject and resource ids', function (): void {
            // Arrange
            $tempFile = sys_get_temp_dir().'/test_special_chars_'.uniqid().'.json';
            file_put_contents($tempFile, json_encode([
                [
                    'subject' => 'user:test@example.com',
                    'resource' => 'document:file/path/123',
                    'action' => 'read',
                    'effect' => 'Allow',
                    'priority' => 1,
                ],
            ]));

            $repository = new FilePolicyRepository($tempFile);

            $subject = new Subject('user:test@example.com');
            $resource = new Resource('document:file/path/123', 'document');

            // Act
            $policy = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('user:test@example.com');
            expect($policy->rules[0]->resource)->toBe('document:file/path/123');

            // Cleanup
            unlink($tempFile);
        });

        test('handles multiple domains for same subject', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/domain_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource1 = new Resource('document:456', 'document');
            $resource2 = new Resource('document:789', 'document');

            // Act
            $policy1 = $repository->getPoliciesFor($subject, $resource1);
            $policy2 = $repository->getPoliciesFor($subject, $resource2);

            // Assert
            expect($policy1->rules)->toHaveCount(1);
            expect($policy1->rules[0]->domain->id)->toBe('tenant:1');

            expect($policy2->rules)->toHaveCount(1);
            expect($policy2->rules[0]->domain->id)->toBe('tenant:2');
        });

        test('repository is immutable after construction', function (): void {
            // Arrange
            $filePath = __DIR__.'/../../Fixtures/Policies/valid_policies.json';
            $repository = new FilePolicyRepository($filePath);

            $subject = new Subject('user:123');
            $resource = new Resource('document:456', 'document');

            // Act - call multiple times
            $policy1 = $repository->getPoliciesFor($subject, $resource);
            $policy2 = $repository->getPoliciesFor($subject, $resource);

            // Assert - should return same results
            expect($policy1->rules)->toHaveCount(count($policy2->rules));
        });
    });

    describe('Save Method', function (): void {
        describe('Happy Paths', function (): void {
            test('saves policy to file in existing directory', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                $repository = new FilePolicyRepository(__DIR__.'/../../Fixtures/Policies/empty_policies.json');

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:123',
                        resource: 'document:456',
                        action: 'read',
                        effect: Effect::Allow,
                        priority: new Priority(10),
                    ),
                ]);

                // Create repository with temp file
                file_put_contents($tempFile, '[]');
                $saveRepository = new FilePolicyRepository($tempFile);

                // Act
                $saveRepository->save($policy);

                // Assert
                expect(file_exists($tempFile))->toBeTrue();
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded)->toBeArray();
                expect($decoded)->toHaveCount(1);
                expect($decoded[0]['subject'])->toBe('user:123');
                expect($decoded[0]['resource'])->toBe('document:456');
                expect($decoded[0]['action'])->toBe('read');
                expect($decoded[0]['effect'])->toBe('Allow');
                expect($decoded[0]['priority'])->toBe(10);

                // Cleanup
                unlink($tempFile);
            });

            test('saves policy to file in non-existent directory', function (): void {
                // Arrange
                $tempDir = sys_get_temp_dir().'/patrol_test_'.uniqid();
                $tempFile = $tempDir.'/policies.json';

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'admin',
                        resource: 'project:*',
                        action: 'delete',
                        effect: Effect::Deny,
                        priority: new Priority(100),
                    ),
                ]);

                // Create directory and initial file, then repository
                mkdir($tempDir, 0o755, true);
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                // Remove directory to simulate non-existent directory scenario
                unlink($tempFile);
                rmdir($tempDir);

                // Act - save should recreate directory
                $repository->save($policy);

                // Assert
                expect(is_dir($tempDir))->toBeTrue();
                expect(file_exists($tempFile))->toBeTrue();

                // Cleanup
                unlink($tempFile);
                rmdir($tempDir);
            });

            test('saves policy with resource field included', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:456',
                        resource: 'file:789',
                        action: 'write',
                        effect: Effect::Allow,
                        priority: new Priority(5),
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded[0])->toHaveKey('resource');
                expect($decoded[0]['resource'])->toBe('file:789');

                // Cleanup
                unlink($tempFile);
            });

            test('saves policy without resource field when resource is null', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'role:admin',
                        resource: null,
                        action: 'manage',
                        effect: Effect::Allow,
                        priority: new Priority(50),
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded[0])->not->toHaveKey('resource');
                expect($decoded[0]['subject'])->toBe('role:admin');
                expect($decoded[0]['action'])->toBe('manage');

                // Cleanup
                unlink($tempFile);
            });

            test('saves policy with domain field', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:123',
                        resource: 'document:456',
                        action: 'read',
                        effect: Effect::Allow,
                        priority: new Priority(1),
                        domain: new Domain('tenant:acme'),
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded[0])->toHaveKey('domain');
                expect($decoded[0]['domain'])->toBe('tenant:acme');

                // Cleanup
                unlink($tempFile);
            });

            test('saves policy without domain field when domain is null', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:999',
                        resource: 'file:111',
                        action: 'delete',
                        effect: Effect::Deny,
                        priority: new Priority(20),
                        domain: null,
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded[0])->not->toHaveKey('domain');

                // Cleanup
                unlink($tempFile);
            });

            test('saves multiple policies with mixed configurations', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:1',
                        resource: 'doc:1',
                        action: 'read',
                        effect: Effect::Allow,
                        priority: new Priority(1),
                    ),
                    new PolicyRule(
                        subject: 'user:2',
                        resource: null,
                        action: 'write',
                        effect: Effect::Deny,
                        priority: new Priority(10),
                        domain: new Domain('tenant:1'),
                    ),
                    new PolicyRule(
                        subject: 'admin',
                        resource: '*',
                        action: '*',
                        effect: Effect::Allow,
                        priority: new Priority(100),
                        domain: new Domain('tenant:2'),
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded)->toHaveCount(3);

                // First rule - has resource, no domain
                expect($decoded[0]['resource'])->toBe('doc:1');
                expect($decoded[0])->not->toHaveKey('domain');

                // Second rule - no resource, has domain
                expect($decoded[1])->not->toHaveKey('resource');
                expect($decoded[1]['domain'])->toBe('tenant:1');

                // Third rule - has both
                expect($decoded[2]['resource'])->toBe('*');
                expect($decoded[2]['domain'])->toBe('tenant:2');

                // Cleanup
                unlink($tempFile);
            });
        });

        describe('Edge Cases', function (): void {
            test('saves empty policy with no rules', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[{"subject":"temp","action":"temp","effect":"Allow"}]');
                $repository = new FilePolicyRepository($tempFile);

                $emptyPolicy = new Policy([]);

                // Act
                $repository->save($emptyPolicy);

                // Assert
                $content = file_get_contents($tempFile);
                $decoded = json_decode($content, true);
                expect($decoded)->toBeArray();
                expect($decoded)->toBeEmpty();

                // Cleanup
                unlink($tempFile);
            });

            test('saves policy and verifies json formatting', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'user:test',
                        resource: 'path/to/resource',
                        action: 'read',
                        effect: Effect::Allow,
                        priority: new Priority(1),
                    ),
                ]);

                // Act
                $repository->save($policy);

                // Assert
                $content = file_get_contents($tempFile);

                // Verify JSON is pretty-printed (contains newlines and indentation)
                expect($content)->toContain("\n");
                expect($content)->toContain('    ');

                // Verify slashes are unescaped (path/to/resource not path\/to\/resource)
                expect($content)->toContain('path/to/resource');
                expect($content)->not->toContain('path\/to\/resource');

                // Cleanup
                unlink($tempFile);
            });

            test('saves and reloads policy for round-trip verification', function (): void {
                // Arrange
                $tempFile = sys_get_temp_dir().'/patrol_test_'.uniqid().'.json';
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                $originalPolicy = new Policy([
                    new PolicyRule(
                        subject: 'user:round-trip',
                        resource: 'doc:test',
                        action: 'update',
                        effect: Effect::Allow,
                        priority: new Priority(42),
                        domain: new Domain('tenant:test'),
                    ),
                ]);

                // Act
                $repository->save($originalPolicy);

                // Reload from saved file
                $reloadedRepository = new FilePolicyRepository($tempFile);
                $subject = new Subject('user:round-trip');
                $resource = new Resource('doc:test', 'doc');
                $reloadedPolicy = $reloadedRepository->getPoliciesFor($subject, $resource);

                // Assert
                expect($reloadedPolicy->rules)->toHaveCount(1);
                expect($reloadedPolicy->rules[0]->subject)->toBe('user:round-trip');
                expect($reloadedPolicy->rules[0]->resource)->toBe('doc:test');
                expect($reloadedPolicy->rules[0]->action)->toBe('update');
                expect($reloadedPolicy->rules[0]->effect)->toBe(Effect::Allow);
                expect($reloadedPolicy->rules[0]->priority->value)->toBe(42);
                expect($reloadedPolicy->rules[0]->domain->id)->toBe('tenant:test');

                // Cleanup
                unlink($tempFile);
            });

            test('handles nested directory creation with multiple levels', function (): void {
                // Arrange
                $tempDir = sys_get_temp_dir().'/patrol_test_'.uniqid().'/nested/deep/path';
                $tempFile = $tempDir.'/policies.json';

                $policy = new Policy([
                    new PolicyRule(
                        subject: 'test',
                        resource: 'test',
                        action: 'test',
                        effect: Effect::Allow,
                        priority: new Priority(1),
                    ),
                ]);

                // Create nested directories and file, then repository
                mkdir($tempDir, 0o755, true);
                file_put_contents($tempFile, '[]');
                $repository = new FilePolicyRepository($tempFile);

                // Remove nested directories to simulate non-existent deep path
                unlink($tempFile);
                $parts = explode('/', mb_trim($tempDir, '/'));
                $paths = [];
                $currentPath = '/';

                foreach ($parts as $part) {
                    $currentPath .= $part.'/';

                    if (is_dir($currentPath) && str_contains($currentPath, 'patrol_test_')) {
                        $paths[] = mb_rtrim($currentPath, '/');
                    }
                }

                foreach (array_reverse($paths) as $dir) {
                    if (is_dir($dir)) {
                        rmdir($dir);
                    }
                }

                // Act - save should recreate deep directory structure
                $repository->save($policy);

                // Assert
                expect(file_exists($tempFile))->toBeTrue();
                expect(is_dir($tempDir))->toBeTrue();

                // Cleanup
                unlink($tempFile);

                foreach (array_reverse($paths) as $dir) {
                    if (is_dir($dir)) {
                        rmdir($dir);
                    }
                }
            });
        });
    });
});
