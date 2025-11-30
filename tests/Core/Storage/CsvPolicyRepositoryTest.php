<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\CsvPolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Tests\Helpers\FilesystemHelper;

describe('CsvPolicyRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_csv_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        test('retrieves policies matching subject and resource in single file mode', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
user:bob,document:456,write,Deny,,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
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
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
*,document:123,read,Allow,,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
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
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,*,delete,Deny,5,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:999', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->resource)->toBe('*');
            expect($result->rules[0]->priority->value)->toBe(5);
        });

        test('retrieves policies with domain scope', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,project:100,manage,Allow,1,engineering
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('project:100', 'project');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->domain?->id)->toBe('engineering');
        });

        test('loads policies from multiple CSV files', function (): void {
            // Arrange
            $dir = $this->tempDir.'/policies';
            mkdir($dir, 0o755, true);

            file_put_contents($dir.'/policy_0.csv', <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
CSV);

            file_put_contents($dir.'/policy_1.csv', <<<'CSV'
subject,resource,action,effect,priority,domain
user:bob,document:456,write,Deny,2,
CSV);

            $repository = new CsvPolicyRepository(
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

        test('saves policies to single CSV file', function (): void {
            // Arrange
            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Act
            $repository->save($policy);

            // Assert
            $filePath = $this->tempDir.'/policies/policies.csv';
            expect(file_exists($filePath))->toBeTrue();

            $content = file_get_contents($filePath);
            expect($content)->toContain('user:alice');
            expect($content)->toContain('document:123');
            expect($content)->toContain('read');
            expect($content)->toContain('Allow');
        });

        test('saves policies to multiple CSV files', function (): void {
            // Arrange
            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'document:123',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
                new PolicyRule(
                    subject: 'user:bob',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(2),
                ),
            ]);

            // Act
            $repository->save($policy);

            // Assert
            expect(file_exists($this->tempDir.'/policies/policy_0.csv'))->toBeTrue();
            expect(file_exists($this->tempDir.'/policies/policy_1.csv'))->toBeTrue();
        });

        test('returns empty policy when no matching rules', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:charlie');
            $resource = resource('document:999', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(0);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty policy when CSV file does not exist in single file mode', function (): void {
            // Arrange
            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(0);
        });

        test('returns empty policy when directory does not exist in multiple file mode', function (): void {
            // Arrange
            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(0);
        });

        test('handles malformed CSV gracefully in multiple file mode', function (): void {
            // Arrange
            $dir = $this->tempDir.'/policies';
            mkdir($dir, 0o755, true);

            // Valid CSV
            file_put_contents($dir.'/policy_0.csv', <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
CSV);

            // Malformed CSV - will be skipped
            file_put_contents($dir.'/policy_1.csv', 'invalid,csv,content');

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - should only get valid policy
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });

        test('handles CSV parsing exception gracefully in single file mode', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);

            // Write empty string which causes League\Csv\SyntaxError when setting header offset
            file_put_contents($policyFile, '');

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - should return empty policy when decode fails due to exception
            expect($result->rules)->toHaveCount(0);
        });

        test('handles CSV parsing exception gracefully in multiple file mode', function (): void {
            // Arrange
            $dir = $this->tempDir.'/policies';
            mkdir($dir, 0o755, true);

            // Valid CSV
            file_put_contents($dir.'/policy_0.csv', <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
CSV);

            // File with empty content that triggers SyntaxError exception
            file_put_contents($dir.'/policy_1.csv', '');

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Multiple,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert - should get valid policy, exception file skipped
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty CSV file', function (): void {
            // Arrange
            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, "subject,resource,action,effect,priority,domain\n");

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice');
            $resource = resource('document:123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(0);
        });

        test('handles policies without optional resource field', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,,read,Allow,1,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
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

        test('handles policies with missing priority using default', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
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

        test('preserves domain information on save and load', function (): void {
            // Arrange
            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:alice',
                    resource: 'project:100',
                    action: 'manage',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                    domain: new Domain('engineering'),
                ),
            ]);

            // Act - Save
            $repository->save($policy);

            // Reload
            $subject = subject('user:alice');
            $resource = resource('project:100', 'project');
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->domain?->id)->toBe('engineering');
        });
    });

    describe('Regression Tests', function (): void {
        test('handles special characters in CSV fields', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
"user:alice@example.com","document:test,123",read,Allow,1,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $subject = subject('user:alice@example.com');
            $resource = resource('document:test,123', 'document');

            // Act
            $result = $repository->getPoliciesFor($subject, $resource);

            // Assert
            expect($result->rules)->toHaveCount(1);
            expect($result->rules[0]->subject)->toBe('user:alice@example.com');
            expect($result->rules[0]->resource)->toBe('document:test,123');
        });

        test('handles both Allow and Deny effects', function (): void {
            // Arrange
            $csvContent = <<<'CSV'
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
user:alice,document:123,delete,Deny,2,
CSV;

            $policyFile = $this->tempDir.'/policies/policies.csv';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $csvContent);

            $repository = new CsvPolicyRepository(
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
            expect($result->rules[0]->effect)->toBe(Effect::Allow);
            expect($result->rules[1]->effect)->toBe(Effect::Deny);
        });
    });
});
