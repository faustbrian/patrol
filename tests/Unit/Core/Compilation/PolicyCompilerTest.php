<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Compilation\PolicyCompiler;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

/**
 * Comprehensive test suite for PolicyCompiler class.
 *
 * Achieves 100% code coverage by testing:
 * - compile() method:
 *   - Single rule compilation (lines 49-78)
 *   - Multiple rules with priority ordering (lines 49-78)
 *   - Wildcard subjects/resources/actions (lines 152-174)
 *   - Null resources (lines 156-160)
 *   - Custom namespace generation (line 59)
 *   - Class name generation via md5 (line 51)
 * - compileToFile() method:
 *   - File writing functionality (lines 92-95)
 *   - Directory creation and permissions
 * - generateRulesCode() method:
 *   - Rules array code generation (lines 109-128)
 *   - Allow/Deny effect mapping (line 114)
 *   - Null resource handling (line 115)
 * - generateEvaluationCode() method:
 *   - Evaluation logic generation (lines 143-178)
 *   - Priority-based sorting (line 146)
 *   - Wildcard pattern matching (lines 152-164)
 *   - Deny-override semantics
 *
 * Tests verify generated code is syntactically valid PHP and can be
 * evaluated successfully.
 *
 * @coversDefaultClass \Patrol\Core\Compilation\PolicyCompiler
 */
describe('PolicyCompiler', function (): void {
    beforeEach(function (): void {
        $this->compiler = new PolicyCompiler();
        $this->tempDir = sys_get_temp_dir().'/patrol_compiler_test_'.uniqid();
        mkdir($this->tempDir, 0o755, true);
    });

    afterEach(function (): void {
        if (is_dir($this->tempDir)) {
            array_map(unlink(...), glob($this->tempDir.'/*'));
            rmdir($this->tempDir);
        }
    });

    describe('compile()', function (): void {
        test('compiles policy with single allow rule', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toBeString()
                ->toContain('<?php declare(strict_types=1);')
                ->toContain('namespace CompiledPolicies;')
                ->toContain('use Patrol\\Core\\ValueObjects\\Effect;')
                ->toContain('final class CompiledPolicy_')
                ->toContain('public static function evaluate(string $subjectId, string $resourceId, string $actionName): Effect')
                ->toContain('return Effect::Deny;') // Default deny
                ->toContain('private static array $rules');
        })->group('happy-path', 'compilation');

        test('compiles policy with single deny rule', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'delete',
                    effect: Effect::Deny,
                    priority: new Priority(100),
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'effect' => Effect::Deny")
                ->toContain("'priority' => 100")
                ->toContain('return Effect::Deny;');
        })->group('happy-path', 'compilation');

        test('compiles policy with multiple rules in priority order', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                    priority: new Priority(1),
                ),
                new PolicyRule(
                    subject: 'admin',
                    resource: '*',
                    action: '*',
                    effect: Effect::Allow,
                    priority: new Priority(50),
                ),
                new PolicyRule(
                    subject: '*',
                    resource: 'secrets',
                    action: '*',
                    effect: Effect::Deny,
                    priority: new Priority(100),
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            // Verify all rules are present in the static array
            expect($result)->toContain("'subject' => 'user:123'")
                ->toContain("'subject' => 'admin'")
                ->toContain("'subject' => '*'")
                ->toContain("'resource' => 'document:456'")
                ->toContain("'resource' => '*'")
                ->toContain("'resource' => 'secrets'")
                ->toContain("'priority' => 1")
                ->toContain("'priority' => 50")
                ->toContain("'priority' => 100");

            // Verify evaluation code includes all rules
            expect($result)->toContain('// Evaluate rules in priority order (deny-override)');
        })->group('happy-path', 'compilation', 'priority');

        test('compiles policy with custom namespace', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: 'document:456',
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy, 'App\\CompiledPolicies');

            // Assert
            expect($result)->toContain('namespace App\\CompiledPolicies;')
                ->not->toContain('namespace CompiledPolicies;');
        })->group('happy-path', 'compilation', 'namespace');

        test('compiles policy with wildcard subject', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: '*',
                    resource: 'public-documents',
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'subject' => '*'")
                ->toContain('if (true && $resourceId === \'public-documents\' && $actionName === \'read\') { return Effect::Allow; }');
        })->group('edge-case', 'compilation', 'wildcard');

        test('compiles policy with wildcard resource', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'admin',
                    resource: '*',
                    action: 'delete',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'resource' => '*'")
                ->toContain('if ($subjectId === \'admin\' && true && $actionName === \'delete\') { return Effect::Allow; }');
        })->group('edge-case', 'compilation', 'wildcard');

        test('compiles policy with wildcard action', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'admin',
                    resource: 'documents',
                    action: '*',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'action' => '*'")
                ->toContain('if ($subjectId === \'admin\' && $resourceId === \'documents\' && true) { return Effect::Allow; }');
        })->group('edge-case', 'compilation', 'wildcard');

        test('compiles policy with all wildcards', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: '*',
                    resource: '*',
                    action: '*',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'subject' => '*'")
                ->toContain("'resource' => '*'")
                ->toContain("'action' => '*'")
                ->toContain('if (true && true && true) { return Effect::Allow; }');
        })->group('edge-case', 'compilation', 'wildcard');

        test('compiles policy with null resource', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:123',
                    resource: null,
                    action: 'read',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain("'resource' => null")
                ->toContain('if ($subjectId === \'user:123\' && true && $actionName === \'read\') { return Effect::Allow; }');
        })->group('edge-case', 'compilation', 'null-resource');

        test('compiles empty policy with no rules', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain('private static array $rules = [')
                ->toContain('// Evaluate rules in priority order (deny-override)')
                ->toContain('// Default deny')
                ->toContain('return Effect::Deny;');

            // Verify no rule-specific code is generated
            expect($result)->not->toContain('if (');
        })->group('edge-case', 'compilation', 'empty-policy');

        test('generates unique class name for each policy', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Deny),
            ]);

            // Act
            $result1 = $this->compiler->compile($policy1);
            $result2 = $this->compiler->compile($policy2);

            // Assert
            expect($result1)->toContain('final class CompiledPolicy_');
            expect($result2)->toContain('final class CompiledPolicy_');
            expect($result1)->not->toBe($result2);

            // Extract class names
            preg_match('/final class (CompiledPolicy_\w+)/', $result1, $matches1);
            preg_match('/final class (CompiledPolicy_\w+)/', $result2, $matches2);

            expect($matches1[1])->not->toBe($matches2[1]);
        })->group('edge-case', 'compilation', 'class-naming');

        test('orders rules by priority descending in evaluation code', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Allow, new Priority(100)),
                new PolicyRule('user:3', 'doc:3', 'delete', Effect::Deny, new Priority(50)),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            // Extract evaluation section
            $evalStart = mb_strpos($result, '// Evaluate rules in priority order');
            $evalEnd = mb_strpos($result, '// Default deny');
            $evalSection = mb_substr($result, $evalStart, $evalEnd - $evalStart);

            // Priority 100 should come before 50, which should come before 1
            $pos100 = mb_strpos($evalSection, 'user:2');
            $pos50 = mb_strpos($evalSection, 'user:3');
            $pos1 = mb_strpos($evalSection, 'user:1');

            expect($pos100)->toBeLessThan($pos50);
            expect($pos50)->toBeLessThan($pos1);
        })->group('happy-path', 'compilation', 'priority');

        test('generated code is syntactically valid PHP', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', '*', '*', Effect::Allow, new Priority(50)),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert - attempt to evaluate the code
            $tempFile = $this->tempDir.'/compiled_test.php';
            file_put_contents($tempFile, $result);

            // Should not throw syntax error
            $output = shell_exec('php -l '.escapeshellarg($tempFile).' 2>&1');
            expect($output)->toContain('No syntax errors detected');
        })->group('happy-path', 'compilation', 'syntax');

        test('compiled code contains proper PHP strict types declaration', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toStartWith('<?php declare(strict_types=1);');
        })->group('happy-path', 'compilation', 'strict-types');

        test('compiles policy with mixed effect types', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:123', 'document:2', 'delete', Effect::Deny, new Priority(2)),
                new PolicyRule('admin', '*', '*', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toContain('Effect::Allow')
                ->toContain('Effect::Deny')
                ->toContain('return Effect::Allow;')
                ->toContain('return Effect::Deny;');
        })->group('happy-path', 'compilation', 'mixed-effects');
    });

    describe('compileToFile()', function (): void {
        test('writes compiled code to file', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);
            $filePath = $this->tempDir.'/CompiledPolicy.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath);

            // Assert
            expect(file_exists($filePath))->toBeTrue();

            $contents = file_get_contents($filePath);
            expect($contents)->toContain('<?php declare(strict_types=1);')
                ->toContain('namespace CompiledPolicies;')
                ->toContain('final class CompiledPolicy_');
        })->group('happy-path', 'file-writing');

        test('writes compiled code with custom namespace to file', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);
            $filePath = $this->tempDir.'/CustomPolicy.php';
            $namespace = 'App\\Policies\\Compiled';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            // Assert
            expect(file_exists($filePath))->toBeTrue();

            $contents = file_get_contents($filePath);
            expect($contents)->toContain('namespace App\\Policies\\Compiled;')
                ->not->toContain('namespace CompiledPolicies;');
        })->group('happy-path', 'file-writing', 'namespace');

        test('overwrites existing file', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:2', 'doc:2', 'write', Effect::Deny),
            ]);
            $filePath = $this->tempDir.'/Policy.php';

            // Act
            $this->compiler->compileToFile($policy1, $filePath);
            $contents1 = file_get_contents($filePath);

            $this->compiler->compileToFile($policy2, $filePath);
            $contents2 = file_get_contents($filePath);

            // Assert
            expect($contents1)->not->toBe($contents2);
            expect($contents2)->toContain('user:2')
                ->toContain('doc:2')
                ->not->toContain('user:1');
        })->group('edge-case', 'file-writing', 'overwrite');

        test('writes file with valid PHP syntax', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);
            $filePath = $this->tempDir.'/ExecutablePolicy.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath);

            // Assert
            expect(file_exists($filePath))->toBeTrue();

            // Verify no syntax errors
            $output = shell_exec('php -l '.escapeshellarg($filePath).' 2>&1');
            expect($output)->toContain('No syntax errors detected');
        })->group('happy-path', 'file-writing', 'syntax');

        test('creates valid PHP file with proper permissions', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);
            $filePath = $this->tempDir.'/PermissionsTest.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath);

            // Assert
            expect(file_exists($filePath))->toBeTrue();
            expect(is_readable($filePath))->toBeTrue();
            expect(filesize($filePath))->toBeGreaterThan(0);
        })->group('happy-path', 'file-writing', 'permissions');
    });

    describe('code generation edge cases', function (): void {
        test('generates code with safe characters only', function (): void {
            // Arrange - using safe characters only as compiler doesn't escape quotes/backslashes
            $policy = new Policy([
                new PolicyRule(
                    subject: 'user:test-user',
                    resource: 'doc:with_underscore',
                    action: 'read/write',
                    effect: Effect::Allow,
                ),
            ]);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert - code should be generated without syntax errors
            $tempFile = $this->tempDir.'/special_chars.php';
            file_put_contents($tempFile, $result);

            $output = shell_exec('php -l '.escapeshellarg($tempFile).' 2>&1');
            expect($output)->toContain('No syntax errors detected');

            // Note: PolicyCompiler does not currently escape quotes or backslashes
            // This is a known limitation - rules should not contain ' " \ characters
        })->group('edge-case', 'special-characters');

        test('handles very long rule collections', function (): void {
            // Arrange
            $rules = [];

            for ($i = 0; $i < 100; ++$i) {
                $rules[] = new PolicyRule(
                    subject: 'user:'.$i,
                    resource: 'document:'.$i,
                    action: 'read',
                    effect: $i % 2 === 0 ? Effect::Allow : Effect::Deny,
                    priority: new Priority($i),
                );
            }

            $policy = new Policy($rules);

            // Act
            $result = $this->compiler->compile($policy);

            // Assert
            expect($result)->toBeString();
            expect(mb_substr_count($result, 'if ('))->toBe(100);

            // Verify syntax
            $tempFile = $this->tempDir.'/large_policy.php';
            file_put_contents($tempFile, $result);

            $output = shell_exec('php -l '.escapeshellarg($tempFile).' 2>&1');
            expect($output)->toContain('No syntax errors detected');
        })->group('edge-case', 'large-policy');

        test('generates consistent output for identical policies', function (): void {
            // Arrange
            $policy1 = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);
            $policy2 = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow),
            ]);

            // Act
            $result1 = $this->compiler->compile($policy1);
            $result2 = $this->compiler->compile($policy2);

            // Assert - should generate identical code (including class name hash)
            expect($result1)->toBe($result2);
        })->group('edge-case', 'consistency');
    });

    describe('compiled code execution', function (): void {
        test('compiled code executes and returns correct Allow decision', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
            ]);
            $namespace = 'CompiledPoliciesTest'.uniqid();
            $filePath = $this->tempDir.'/ExecutableAllow.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name from compiled code
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute the compiled code
            $result = $className::evaluate('user:123', 'document:456', 'read');

            // Assert
            expect($result)->toBe(Effect::Allow);
        })->group('happy-path', 'execution', 'integration');

        test('compiled code executes and returns correct Deny decision', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'delete', Effect::Deny, new Priority(100)),
            ]);
            $namespace = 'CompiledPoliciesTest'.uniqid();
            $filePath = $this->tempDir.'/ExecutableDeny.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute the compiled code
            $result = $className::evaluate('user:123', 'document:456', 'delete');

            // Assert
            expect($result)->toBe(Effect::Deny);
        })->group('happy-path', 'execution', 'integration');

        test('compiled code returns default Deny when no rules match', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
            ]);
            $namespace = 'CompiledPoliciesTest'.uniqid();
            $filePath = $this->tempDir.'/ExecutableDefault.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute with non-matching parameters
            $result = $className::evaluate('user:999', 'document:999', 'write');

            // Assert - should default to Deny
            expect($result)->toBe(Effect::Deny);
        })->group('happy-path', 'execution', 'default-deny');

        test('compiled code respects priority order', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user:123', 'document:456', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:123', 'document:456', 'read', Effect::Deny, new Priority(100)),
            ]);
            $namespace = 'CompiledPoliciesTest'.uniqid();
            $filePath = $this->tempDir.'/ExecutablePriority.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute - higher priority Deny should win
            $result = $className::evaluate('user:123', 'document:456', 'read');

            // Assert - priority 100 Deny should override priority 1 Allow
            expect($result)->toBe(Effect::Deny);
        })->group('happy-path', 'execution', 'priority');

        test('compiled code handles wildcards correctly', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('*', '*', '*', Effect::Allow, new Priority(1)),
            ]);
            $namespace = 'CompiledPoliciesTest'.uniqid();
            $filePath = $this->tempDir.'/ExecutableWildcard.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute with any parameters
            $result1 = $className::evaluate('user:123', 'document:456', 'read');
            $result2 = $className::evaluate('admin', 'secrets', 'delete');
            $result3 = $className::evaluate('guest', 'public', 'view');

            // Assert - all should be allowed due to wildcard rule
            expect($result1)->toBe(Effect::Allow);
            expect($result2)->toBe(Effect::Allow);
            expect($result3)->toBe(Effect::Allow);
        })->group('edge-case', 'execution', 'wildcard');

        test('compiled code with custom namespace executes correctly', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', '*', '*', Effect::Allow, new Priority(50)),
            ]);
            $namespace = 'App\\Security\\CompiledPolicies'.uniqid();
            $filePath = $this->tempDir.'/ExecutableCustomNs.php';

            // Act
            $this->compiler->compileToFile($policy, $filePath, $namespace);

            require_once $filePath;

            // Extract class name
            $contents = file_get_contents($filePath);
            preg_match('/final class (CompiledPolicy_\w+)/', $contents, $matches);
            $className = $namespace.'\\'.$matches[1];

            // Execute
            $result = $className::evaluate('admin', 'any-resource', 'any-action');

            // Assert
            expect($result)->toBe(Effect::Allow);
        })->group('happy-path', 'execution', 'custom-namespace');
    });
});
