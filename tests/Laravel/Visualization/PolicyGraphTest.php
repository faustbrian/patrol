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
use Patrol\Laravel\Visualization\PolicyGraph;

describe('PolicyGraph', function (): void {
    describe('Happy Paths', function (): void {
        test('generates valid DOT graph from single rule policy', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document:123', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toBeString();
            expect($dot)->toContain('digraph Policy {');
            expect($dot)->toContain('rankdir=LR;');
            expect($dot)->toContain('node [shape=box];');
            expect($dot)->toContain('"admin" -> "document:123"');
            expect($dot)->toContain('label="read\npriority: 1"');
            expect($dot)->toContain('color=green');
            expect($dot)->toContain('style=solid');
            expect($dot)->toEndWith("}\n");
        });

        test('generates DOT graph with multiple allow rules', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document:123', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'document:456', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('viewer', 'document:789', 'view', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('"admin" -> "document:123"');
            expect($dot)->toContain('label="read\npriority: 1"');
            expect($dot)->toContain('"editor" -> "document:456"');
            expect($dot)->toContain('label="write\npriority: 2"');
            expect($dot)->toContain('"viewer" -> "document:789"');
            expect($dot)->toContain('label="view\npriority: 3"');
        });

        test('color codes allow rules as green with solid style', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('color=green');
            expect($dot)->toContain('style=solid');
        });

        test('color codes deny rules as red with dashed style', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Deny, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('color=red');
            expect($dot)->toContain('style=dashed');
        });

        test('includes priority labels on edges', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'edit', Effect::Allow, new Priority(100)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('priority: 100');
        });

        test('generates graph with mixed allow and deny rules', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret', 'read', Effect::Allow, new Priority(10)),
                new PolicyRule('guest', 'secret', 'read', Effect::Deny, new Priority(5)),
                new PolicyRule('editor', 'document', 'write', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('color=green');
            expect($dot)->toContain('color=red');
            expect($dot)->toContain('style=solid');
            expect($dot)->toContain('style=dashed');
        });
    });

    describe('Sad Paths', function (): void {
        test('generates minimal graph structure for empty policy', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toBeString();
            expect($dot)->toContain('digraph Policy {');
            expect($dot)->toContain('rankdir=LR;');
            expect($dot)->toContain('node [shape=box];');
            expect($dot)->toEndWith("}\n");
            expect($dot)->not->toContain('->');
        });

        test('handles null resource in policy rule', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', null, 'manage', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toBeString();
            expect($dot)->toContain('"admin" -> ""');
        });
    });

    describe('Edge Cases', function (): void {
        test('escapes double quotes in subject names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user"with"quotes', 'resource', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('user\\"with\\"quotes');
            expect($dot)->not->toContain('user"with"quotes');
        });

        test('escapes double quotes in resource names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource"with"quotes', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('resource\\"with\\"quotes');
        });

        test('escapes double quotes in action names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action"with"quotes', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('action\\"with\\"quotes');
        });

        test('handles special characters in subject names', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user@example.com', 'resource', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('"user@example.com"');
        });

        test('handles wildcard subjects and resources', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('*', '*', 'read', Effect::Deny, new Priority(100)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('"*" -> "*"');
            expect($dot)->toContain('color=red');
        });

        test('handles unicode characters in labels', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('用户', '文档', '阅读', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('"用户"');
            expect($dot)->toContain('"文档"');
            expect($dot)->toContain('阅读');
        });

        test('handles very high priority values', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'resource', 'action', Effect::Allow, new Priority(999_999)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('priority: 999999');
        });

        test('handles zero priority value', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(0)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('priority: 0');
        });

        test('handles negative priority values', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Deny, new Priority(-10)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('priority: -10');
        });

        test('generates distinct edges for multiple actions on same subject-resource pair', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('admin', 'document', 'delete', Effect::Deny, new Priority(3)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('label="read\npriority: 1"');
            expect($dot)->toContain('label="write\npriority: 2"');
            expect($dot)->toContain('label="delete\npriority: 3"');
        });

        test('handles complex resource identifiers with colons and slashes', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('api-client', '/api/v2/users/:id', 'GET', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert
            expect($dot)->toContain('"/api/v2/users/:id"');
        });
    });

    describe('Regressions', function (): void {
        test('ensures consistent DOT format structure across multiple generations', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot1 = PolicyGraph::generate($policy);
            $dot2 = PolicyGraph::generate($policy);

            // Assert - Multiple generations should produce identical output
            expect($dot1)->toBe($dot2);
        });

        test('maintains proper DOT syntax with newline escaping in labels', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user', 'resource', 'action', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $dot = PolicyGraph::generate($policy);

            // Assert - Ensure \n is used for newline in labels, not actual newlines
            expect($dot)->toContain('label="action\npriority: 1"');
            expect($dot)->toMatch('/label="[^"]*\\\\n[^"]*"/');
        });
    });
});
