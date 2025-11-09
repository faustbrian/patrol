<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Storage\IniPolicyRepository;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Tests\Helpers\FilesystemHelper;

describe('IniPolicyRepository', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir().'/patrol_ini_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        FilesystemHelper::deleteDirectory($this->tempDir);
    });

    describe('Happy Paths', function (): void {
        test('saves and retrieves policy with all fields in single file mode', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
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
                    priority: new Priority(100),
                    domain: new Domain('engineering'),
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:alice'),
                resource('document:123', 'document'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            $rule = $retrieved->rules[0];
            expect($rule->subject)->toBe('user:alice');
            expect($rule->resource)->toBe('document:123');
            expect($rule->action)->toBe('read');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(100);
            expect($rule->domain->id)->toBe('engineering');
        })->group('happy-path', 'single-file');

        test('saves and retrieves policy with only required fields', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:bob',
                    resource: null,
                    action: 'write',
                    effect: Effect::Deny,
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:bob'),
                resource('any:resource', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            $rule = $retrieved->rules[0];
            expect($rule->subject)->toBe('user:bob');
            expect($rule->resource)->toBeNull();
            expect($rule->action)->toBe('write');
            expect($rule->effect)->toBe(Effect::Deny);
            expect($rule->priority->value)->toBe(1);
            expect($rule->domain)->toBeNull();
        })->group('happy-path', 'single-file');

        test('saves and retrieves multiple policies in single file mode', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
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
                ),
                new PolicyRule(
                    subject: 'user:bob',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(50),
                ),
                new PolicyRule(
                    subject: '*',
                    resource: 'document:public',
                    action: 'delete',
                    effect: Effect::Deny,
                    priority: new Priority(100),
                    domain: new Domain('admin'),
                ),
            ]);

            // Act
            $repository->save($policy);
            $fileContent = file_get_contents($this->tempDir.'/policies/policies.ini');

            // Assert
            expect($fileContent)->toContain('[policy_0]');
            expect($fileContent)->toContain('[policy_1]');
            expect($fileContent)->toContain('[policy_2]');
            expect($fileContent)->toContain('subject = "user:alice"');
            expect($fileContent)->toContain('subject = "user:bob"');
            expect($fileContent)->toContain('subject = "*"');
        })->group('happy-path', 'single-file');

        test('generates properly formatted INI with quoted strings', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
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
                    priority: new Priority(100),
                    domain: new Domain('engineering'),
                ),
            ]);

            // Act
            $repository->save($policy);
            $fileContent = file_get_contents($this->tempDir.'/policies/policies.ini');

            // Assert
            expect($fileContent)->toContain('subject = "user:alice"');
            expect($fileContent)->toContain('resource = "document:123"');
            expect($fileContent)->toContain('action = "read"');
            expect($fileContent)->toContain('effect = "Allow"');
            expect($fileContent)->toContain('priority = 100');
            expect($fileContent)->not->toContain('priority = "100"');
            expect($fileContent)->toContain('domain = "engineering"');
        })->group('happy-path', 'format');

        test('saves and retrieves policies in multiple file mode', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
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
                ),
                new PolicyRule(
                    subject: 'user:bob',
                    resource: 'document:456',
                    action: 'write',
                    effect: Effect::Deny,
                    priority: new Priority(50),
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:alice'),
                resource('document:123', 'document'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('user:alice');
            expect(file_exists($this->tempDir.'/policies/policy_0.ini'))->toBeTrue();
            expect(file_exists($this->tempDir.'/policies/policy_1.ini'))->toBeTrue();
        })->group('happy-path', 'multi-file');

        test('parses INI with integer priority correctly', function (): void {
            // Arrange
            $iniContent = <<<'INI'
[policy_0]
subject = "user:admin"
action = "manage"
effect = "Allow"
priority = 100
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:admin'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->priority->value)->toBe(100);
            expect($retrieved->rules[0]->priority->value)->toBeInt();
        })->group('happy-path', 'type-handling');
    });

    describe('Sad Paths', function (): void {
        test('returns empty policy for invalid INI syntax', function (): void {
            // Arrange
            $invalidIni = 'this is not valid INI [syntax';
            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $invalidIni);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toBeEmpty();
        })->group('sad-path', 'invalid-format');

        test('returns empty policy for missing file', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('document:123', 'document'),
            );

            // Assert
            expect($retrieved->rules)->toBeEmpty();
        })->group('sad-path', 'missing-file');

        test('skips non-array sections in INI', function (): void {
            // Arrange
            $iniContent = <<<'INI'
simple_key = "simple_value"

[policy_0]
subject = "user:alice"
action = "read"
effect = "Allow"
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:alice'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('user:alice');
        })->group('sad-path', 'malformed-content');

        test('handles parse_ini_string returning non-array', function (): void {
            // Arrange
            $iniContent = <<<'INI'
[
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toBeEmpty();
        })->group('sad-path', 'parse-failure');

        test('handles extremely malformed INI that could trigger parse errors', function (): void {
            // Arrange
            // Multiple malformations that stress the parser
            $iniContent = <<<'INI'
[[[[[invalid
key without equals
[section
key = "unclosed quote
[another]bad]format
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toBeEmpty();
        })->group('sad-path', 'extreme-malformation');

        test('handles binary or corrupted file content', function (): void {
            // Arrange
            // Binary content that might cause parse_ini_string to fail unexpectedly
            $iniContent = "\xFF\xFE\x00\x00[policy]\x00\x00invalid\xFF";

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toBeEmpty();
        })->group('sad-path', 'corrupted-file');

        test('handles parse_ini_string exceptions when warnings are converted to exceptions', function (): void {
            // Arrange
            $iniContent = '[[[invalid'; // Malformed INI that triggers warnings

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            // Set up error handler that converts warnings to exceptions
            // This simulates environments where strict error handling is configured
            $originalHandler = set_error_handler(static function (int $errno, string $errstr): bool {
                throw new ErrorException($errstr, 0, $errno);
            });

            try {
                $repository = new IniPolicyRepository(
                    basePath: $this->tempDir,
                    fileMode: FileMode::Single,
                    versioningEnabled: false,
                );

                // Act
                $retrieved = $repository->getPoliciesFor(
                    subject('user:test'),
                    resource('any', 'any'),
                );

                // Assert - should handle exception gracefully and return empty policy
                expect($retrieved->rules)->toBeEmpty();
            } finally {
                // Always restore the original error handler
                restore_error_handler();
            }
        })->group('sad-path', 'exception-handling');
    });

    describe('Edge Cases', function (): void {
        test('handles empty optional fields', function (): void {
            // Arrange
            $iniContent = <<<'INI'
[policy_0]
subject = "user:test"
resource = ""
action = "read"
effect = "Allow"
priority = ""
domain = ""
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('user:test'),
                resource('any', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            $rule = $retrieved->rules[0];
            expect($rule->resource)->toBeNull();
            expect($rule->domain)->toBeNull();
        })->group('edge-case', 'empty-values');

        test('handles special characters in values via round trip', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:alice@example.com',
                    resource: 'file:path/to/file.txt',
                    action: 'read:write',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:alice@example.com'),
                resource('file:path/to/file.txt', 'file'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('user:alice@example.com');
            expect($retrieved->rules[0]->resource)->toBe('file:path/to/file.txt');
            expect($retrieved->rules[0]->action)->toBe('read:write');
        })->group('edge-case', 'special-characters');

        test('handles unicode characters via round trip', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:José',
                    resource: 'document:文档',
                    action: 'read',
                    effect: Effect::Allow,
                    domain: new Domain('テスト'),
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:José'),
                resource('document:文档', 'document'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('user:José');
            expect($retrieved->rules[0]->resource)->toBe('document:文档');
            expect($retrieved->rules[0]->domain->id)->toBe('テスト');
        })->group('edge-case', 'unicode');

        test('handles very large policy arrays', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $rules = [];

            for ($i = 0; $i < 100; ++$i) {
                $rules[] = new PolicyRule(
                    subject: 'user:user'.$i,
                    resource: null,
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority($i),
                );
            }

            $policy = new Policy($rules);

            // Act
            $repository->save($policy);
            $fileContent = file_get_contents($this->tempDir.'/policies/policies.ini');

            // Assert
            expect($fileContent)->toContain('[policy_0]');
            expect($fileContent)->toContain('[policy_99]');
            expect($fileContent)->toContain('subject = "user:user0"');
            expect($fileContent)->toContain('subject = "user:user99"');
            expect($fileContent)->toContain('priority = 99');
        })->group('edge-case', 'large-dataset');

        test('handles numeric strings in non-priority fields', function (): void {
            // Arrange
            $iniContent = <<<'INI'
[policy_0]
subject = "123"
resource = "456"
action = "789"
effect = "Allow"
INI;

            $policyFile = $this->tempDir.'/policies/policies.ini';
            mkdir(dirname($policyFile), 0o755, true);
            file_put_contents($policyFile, $iniContent);

            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            // Act
            $retrieved = $repository->getPoliciesFor(
                subject('123'),
                resource('456', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('123');
            expect($retrieved->rules[0]->resource)->toBe('456');
            expect($retrieved->rules[0]->action)->toBe('789');
        })->group('edge-case', 'type-preservation');

        test('preserves policy array order', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(subject: 'first', resource: null, action: 'read', effect: Effect::Allow),
                new PolicyRule(subject: 'second', resource: null, action: 'write', effect: Effect::Deny),
                new PolicyRule(subject: 'third', resource: null, action: 'delete', effect: Effect::Deny),
            ]);

            // Act
            $repository->save($policy);
            $fileContent = file_get_contents($this->tempDir.'/policies/policies.ini');

            // Assert
            $firstPos = mb_strpos($fileContent, 'subject = "first"');
            $secondPos = mb_strpos($fileContent, 'subject = "second"');
            $thirdPos = mb_strpos($fileContent, 'subject = "third"');

            expect($firstPos)->toBeLessThan($secondPos);
            expect($secondPos)->toBeLessThan($thirdPos);
        })->group('edge-case', 'order-preservation');
    });

    describe('Wildcard Matching', function (): void {
        test('handles wildcard subject matching', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: '*',
                    resource: 'document:public',
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:anyone'),
                resource('document:public', 'document'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('*');
        })->group('wildcard', 'subject');

        test('handles wildcard resource matching', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:alice',
                    resource: '*',
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('user:alice'),
                resource('any:resource', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->resource)->toBe('*');
        })->group('wildcard', 'resource');

        test('handles both wildcard subject and resource', function (): void {
            // Arrange
            $repository = new IniPolicyRepository(
                basePath: $this->tempDir,
                fileMode: FileMode::Single,
                versioningEnabled: false,
            );

            $policy = new Policy([
                new PolicyRule(
                    subject: '*',
                    resource: '*',
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $repository->save($policy);
            $retrieved = $repository->getPoliciesFor(
                subject('any:user'),
                resource('any:resource', 'any'),
            );

            // Assert
            expect($retrieved->rules)->toHaveCount(1);
            expect($retrieved->rules[0]->subject)->toBe('*');
            expect($retrieved->rules[0]->resource)->toBe('*');
        })->group('wildcard', 'both');
    });
});
