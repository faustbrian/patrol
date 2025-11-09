<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Laravel\Visualization\PolicyMatrix;

describe('PolicyMatrix', function (): void {
    describe('Happy Paths', function (): void {
        test('generates permission matrix from single rule policy', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix)->toBeArray();
            expect($matrix)->toHaveKeys(['subjects', 'resources', 'permissions']);
            expect($matrix['subjects'])->toBe(['admin']);
            expect($matrix['resources'])->toBe(['document']);
            expect($matrix['permissions'])->toHaveCount(1);
            expect($matrix['permissions'][0])->toMatchArray([
                'subject' => 'admin',
                'resource' => 'document',
                'action' => 'read',
                'effect' => Effect::Allow,
                'priority' => 1,
            ]);
        });

        test('generates matrix with multiple subjects and resources', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'post', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('viewer', 'image', 'view', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toBe(['admin', 'editor', 'viewer']);
            expect($matrix['resources'])->toBe(['document', 'post', 'image']);
            expect($matrix['permissions'])->toHaveCount(3);
        });

        test('deduplicates subjects in matrix', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('admin', 'post', 'edit', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toBe(['admin']);
            expect($matrix['permissions'])->toHaveCount(3);
        });

        test('deduplicates resources in matrix', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'document', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('viewer', 'document', 'view', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['resources'])->toBe(['document']);
            expect($matrix['permissions'])->toHaveCount(3);
        });

        test('handles duplicate subject-resource-action combinations by keeping last', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['permissions'])->toHaveCount(1);
            expect($matrix['permissions'][0])->toMatchArray([
                'subject' => 'admin',
                'resource' => 'document',
                'action' => 'read',
                'effect' => Effect::Deny,
                'priority' => 2,
            ]);
        });

        test('generates valid HTML table from policy', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toBeString();
            expect($html)->toContain('<table border="1" cellpadding="5" cellspacing="0">');
            expect($html)->toContain('<thead>');
            expect($html)->toContain('<tbody>');
            expect($html)->toContain('</table>');
            expect($html)->toContain('<th>Subject / Resource</th>');
            expect($html)->toContain('<th>document</th>');
            expect($html)->toContain('<strong>admin</strong>');
        });

        test('HTML table color codes allow rules as green', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toContain("style='color: green;'>read</span>");
        });

        test('HTML table color codes deny rules as red', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'delete', Effect::Deny, new Priority(1)),
            ]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toContain("style='color: red;'>delete</span>");
        });

        test('generates valid CSV from policy', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toBeString();
            expect($csv)->toStartWith('Subject,Resource,Action,Effect,Priority');
            expect($csv)->toContain('"admin","document","read",ALLOW,1');
        });

        test('CSV uses ALLOW for allow effect', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('ALLOW');
        });

        test('CSV uses DENY for deny effect', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Deny, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('DENY');
        });

        test('CSV quotes subject and resource values', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('"admin"');
            expect($csv)->toContain('"document"');
            expect($csv)->toContain('"read"');
        });
    });

    describe('Sad Paths', function (): void {
        test('generates empty matrix for policy with no rules', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix)->toBeArray();
            expect($matrix['subjects'])->toBe([]);
            expect($matrix['resources'])->toBe([]);
            expect($matrix['permissions'])->toBe([]);
        });

        test('HTML table shows empty table for policy with no rules', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toContain('<table');
            expect($html)->toContain('<thead>');
            expect($html)->toContain('<tbody>');
            expect($html)->toContain('</table>');
        });

        test('CSV shows only header for policy with no rules', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toBe("Subject,Resource,Action,Effect,Priority\n");
        });

        test('handles null resource in policy rule', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', null, 'manage', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['resources'])->toBe([null]);
            expect($matrix['permissions'][0]['resource'])->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles special characters in subject names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user@example.com', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toBe(['user@example.com']);
        });

        test('handles unicode characters in subject and resource names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('用户', '文档', '阅读', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toBe(['用户']);
            expect($matrix['resources'])->toBe(['文档']);
            expect($matrix['permissions'][0]['action'])->toBe('阅读');
        });

        test('HTML table displays multiple actions for same subject-resource pair', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('admin', 'document', 'delete', Effect::Deny, new Priority(3)),
            ]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toContain('>read</span>');
            expect($html)->toContain('>write</span>');
            expect($html)->toContain('>delete</span>');
            expect($html)->toContain("style='color: green;'>read</span>");
            expect($html)->toContain("style='color: green;'>write</span>");
            expect($html)->toContain("style='color: red;'>delete</span>");
        });

        test('HTML table handles empty cells for subject-resource pairs without permissions', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'post', 'write', Effect::Allow, new Priority(2)),
            ]);

            // Act
            $html = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html)->toContain('<td></td>');
        });

        test('CSV handles commas in subject names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user,with,commas', 'resource', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('"user,with,commas"');
        });

        test('CSV handles quotes in subject names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user"with"quotes', 'resource', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('"user"with"quotes"');
        });

        test('CSV handles newlines in field values', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule("user\nwith\nnewlines", 'resource', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toContain('"user');
        });

        test('generates large matrix with many subjects and resources', function (): void {
            // Arrange
            $rules = [];

            for ($i = 1; $i <= 10; ++$i) {
                for ($j = 1; $j <= 10; ++$j) {
                    $rules[] = new PolicyRule(
                        'user'.$i,
                        'resource'.$j,
                        'read',
                        Effect::Allow,
                        new Priority($i * $j),
                    );
                }
            }

            $policy = new Policy($rules);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toHaveCount(10);
            expect($matrix['resources'])->toHaveCount(10);
            expect($matrix['permissions'])->toHaveCount(100);
        });

        test('handles wildcard subjects and resources', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('*', '*', 'read', Effect::Deny, new Priority(100)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['subjects'])->toBe(['*']);
            expect($matrix['resources'])->toBe(['*']);
        });

        test('handles complex resource identifiers', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('api-client', '/api/v2/users/:id', 'GET', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['resources'])->toBe(['/api/v2/users/:id']);
            expect($matrix['permissions'][0]['action'])->toBe('GET');
        });

        test('handles zero priority value', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(0)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['permissions'][0]['priority'])->toBe(0);
        });

        test('handles negative priority values', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Deny, new Priority(-10)),
            ]);

            // Act
            $matrix = PolicyMatrix::generate($policy);

            // Assert
            expect($matrix['permissions'][0]['priority'])->toBe(-10);
        });

        test('CSV maintains order of multiple rules', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'doc1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'doc2', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('viewer', 'doc3', 'view', Effect::Deny, new Priority(3)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            $lines = explode("\n", mb_trim($csv));
            expect($lines)->toHaveCount(4); // Header + 3 rules
            expect($lines[1])->toContain('"admin","doc1","read",ALLOW,1');
            expect($lines[2])->toContain('"editor","doc2","write",ALLOW,2');
            expect($lines[3])->toContain('"viewer","doc3","view",DENY,3');
        });
    });

    describe('Regressions', function (): void {
        test('ensures consistent matrix structure across multiple generations', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $matrix1 = PolicyMatrix::generate($policy);
            $matrix2 = PolicyMatrix::generate($policy);

            // Assert - Multiple generations should produce identical output
            expect($matrix1)->toBe($matrix2);
        });

        test('ensures consistent HTML output across multiple generations', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $html1 = PolicyMatrix::toHtml($policy);
            $html2 = PolicyMatrix::toHtml($policy);

            // Assert
            expect($html1)->toBe($html2);
        });

        test('ensures consistent CSV output across multiple generations', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv1 = PolicyMatrix::toCsv($policy);
            $csv2 = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv1)->toBe($csv2);
        });

        test('CSV ends with newline character', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $csv = PolicyMatrix::toCsv($policy);

            // Assert
            expect($csv)->toEndWith("\n");
        });
    });
});
