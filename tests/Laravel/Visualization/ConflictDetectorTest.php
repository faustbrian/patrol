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
use Patrol\Laravel\Visualization\ConflictDetector;

describe('ConflictDetector', function (): void {
    describe('Happy Paths', function (): void {
        test('detects allow deny conflict for same subject resource action', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts)->toBeArray();
            expect($conflicts)->toHaveKeys(['allow_deny_conflicts', 'priority_collisions', 'unreachable_rules']);
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0])->toMatchArray([
                'subject' => 'admin',
                'resource' => 'document',
                'action' => 'read',
            ]);
            expect($conflicts['allow_deny_conflicts'][0]['rules'])->toHaveCount(2);
        });

        test('detects priority collision when multiple rules have same priority', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(10)),
                new PolicyRule('editor', 'post', 'write', Effect::Allow, new Priority(10)),
                new PolicyRule('viewer', 'image', 'view', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['priority_collisions'])->toHaveCount(1);
            expect($conflicts['priority_collisions'][0])->toMatchArray([
                'priority' => 10,
                'count' => 3,
            ]);
            expect($conflicts['priority_collisions'][0]['rules'])->toHaveCount(3);
        });

        test('detects unreachable rule shadowed by higher priority deny', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret', 'read', Effect::Deny, new Priority(100)),
                new PolicyRule('admin', 'secret', 'read', Effect::Allow, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['unreachable_rules'])->toHaveCount(1);
            expect($conflicts['unreachable_rules'][0])->toMatchArray([
                'subject' => 'admin',
                'resource' => 'secret',
                'action' => 'read',
                'priority' => 10,
                'reason' => 'Shadowed by higher-priority deny rule',
            ]);
        });

        test('returns empty arrays when no conflicts exist', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'post', 'write', Effect::Allow, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
            expect($conflicts['priority_collisions'])->toBe([]);
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('detects multiple allow deny conflicts', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'doc1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'doc1', 'read', Effect::Deny, new Priority(2)),
                new PolicyRule('editor', 'doc2', 'write', Effect::Allow, new Priority(3)),
                new PolicyRule('editor', 'doc2', 'write', Effect::Deny, new Priority(4)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(2);
        });

        test('detects multiple priority collisions at different priority levels', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user1', 'res1', 'act1', Effect::Allow, new Priority(5)),
                new PolicyRule('user2', 'res2', 'act2', Effect::Allow, new Priority(5)),
                new PolicyRule('user3', 'res3', 'act3', Effect::Deny, new Priority(10)),
                new PolicyRule('user4', 'res4', 'act4', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['priority_collisions'])->toHaveCount(2);
        });

        test('includes rule details in allow deny conflict report', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(5)),
                new PolicyRule('admin', 'document', 'read', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            $conflict = $conflicts['allow_deny_conflicts'][0];
            expect($conflict['rules'])->toHaveCount(2);
            expect($conflict['rules'][0])->toHaveKeys(['effect', 'priority']);
            expect($conflict['rules'][1])->toHaveKeys(['effect', 'priority']);
        });

        test('includes rule details in priority collision report', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(10)),
                new PolicyRule('editor', 'post', 'write', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            $collision = $conflicts['priority_collisions'][0];
            expect($collision['rules'])->toHaveCount(2);
            expect($collision['rules'][0])->toHaveKeys(['subject', 'resource', 'action', 'effect']);
            expect($collision['rules'][1])->toHaveKeys(['subject', 'resource', 'action', 'effect']);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty conflict arrays for policy with no rules', function (): void {
            // Arrange
            $policy = new Policy([]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
            expect($conflicts['priority_collisions'])->toBe([]);
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('returns empty conflict arrays for policy with single rule', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
            expect($conflicts['priority_collisions'])->toBe([]);
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('handles null resource in conflict detection', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', null, 'manage', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', null, 'manage', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['resource'])->toBeNull();
        });
    });

    describe('Edge Cases', function (): void {
        test('does not detect conflict when same subject resource action all have same effect', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
        });

        test('detects conflict with three or more conflicting rules', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'read', Effect::Deny, new Priority(2)),
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['rules'])->toHaveCount(3);
        });

        test('handles wildcard subjects in conflict detection', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('*', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('*', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['subject'])->toBe('*');
        });

        test('handles wildcard resources in conflict detection', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', '*', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', '*', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['resource'])->toBe('*');
        });

        test('handles special characters in subject resource action', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user@example.com', '/api/v2/users/:id', 'GET', Effect::Allow, new Priority(1)),
                new PolicyRule('user@example.com', '/api/v2/users/:id', 'GET', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
        });

        test('detects collision with zero priority', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user1', 'resource', 'action', Effect::Allow, new Priority(0)),
                new PolicyRule('user2', 'resource', 'action', Effect::Allow, new Priority(0)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['priority_collisions'])->toHaveCount(1);
            expect($conflicts['priority_collisions'][0]['priority'])->toBe(0);
        });

        test('detects collision with negative priorities', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user1', 'resource', 'action', Effect::Allow, new Priority(-10)),
                new PolicyRule('user2', 'resource', 'action', Effect::Deny, new Priority(-10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['priority_collisions'])->toHaveCount(1);
            expect($conflicts['priority_collisions'][0]['priority'])->toBe(-10);
        });

        test('does not detect unreachable rule when allow has higher priority than deny', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret', 'read', Effect::Allow, new Priority(100)),
                new PolicyRule('admin', 'secret', 'read', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('does not detect unreachable rule when effects are both deny', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret', 'read', Effect::Deny, new Priority(100)),
                new PolicyRule('admin', 'secret', 'read', Effect::Deny, new Priority(10)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('detects multiple unreachable rules', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret1', 'read', Effect::Deny, new Priority(100)),
                new PolicyRule('admin', 'secret1', 'read', Effect::Allow, new Priority(10)),
                new PolicyRule('admin', 'secret2', 'write', Effect::Deny, new Priority(50)),
                new PolicyRule('admin', 'secret2', 'write', Effect::Allow, new Priority(5)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['unreachable_rules'])->toHaveCount(2);
        });

        test('handles unicode characters in conflict detection', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('用户', '文档', '阅读', Effect::Allow, new Priority(1)),
                new PolicyRule('用户', '文档', '阅读', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['subject'])->toBe('用户');
            expect($conflicts['allow_deny_conflicts'][0]['resource'])->toBe('文档');
            expect($conflicts['allow_deny_conflicts'][0]['action'])->toBe('阅读');
        });

        test('detects unreachable rule with equal priorities', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'secret', 'read', Effect::Deny, new Priority(50)),
                new PolicyRule('admin', 'secret', 'read', Effect::Allow, new Priority(50)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['unreachable_rules'])->toBe([]);
        });

        test('handles large number of rules efficiently', function (): void {
            // Arrange
            $rules = [];

            for ($i = 1; $i <= 100; ++$i) {
                $rules[] = new PolicyRule(
                    'user'.$i,
                    'resource'.$i,
                    'read',
                    $i % 2 === 0 ? Effect::Allow : Effect::Deny,
                    new Priority($i % 10),
                );
            }

            $policy = new Policy($rules);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts)->toBeArray();
            expect($conflicts)->toHaveKeys(['allow_deny_conflicts', 'priority_collisions', 'unreachable_rules']);
        });

        test('detects collision when many rules share same priority', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('user1', 'res1', 'act1', Effect::Allow, new Priority(5)),
                new PolicyRule('user2', 'res2', 'act2', Effect::Allow, new Priority(5)),
                new PolicyRule('user3', 'res3', 'act3', Effect::Deny, new Priority(5)),
                new PolicyRule('user4', 'res4', 'act4', Effect::Allow, new Priority(5)),
                new PolicyRule('user5', 'res5', 'act5', Effect::Deny, new Priority(5)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['priority_collisions'])->toHaveCount(1);
            expect($conflicts['priority_collisions'][0]['count'])->toBe(5);
        });

        test('does not confuse different actions as conflicts', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'write', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
        });

        test('does not confuse different subjects as conflicts', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
        });

        test('does not confuse different resources as conflicts', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document2', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toBe([]);
        });
    });

    describe('Regressions', function (): void {
        test('ensures consistent conflict detection across multiple runs', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts1 = ConflictDetector::detect($policy);
            $conflicts2 = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts1)->toBe($conflicts2);
        });

        test('maintains proper rule ordering in conflict reports', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin', 'doc', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin', 'doc', 'read', Effect::Deny, new Priority(2)),
                new PolicyRule('admin', 'doc', 'read', Effect::Allow, new Priority(3)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'][0]['rules'])->toHaveCount(3);
            expect($conflicts['allow_deny_conflicts'][0]['rules'][0]['priority'])->toBe(1);
            expect($conflicts['allow_deny_conflicts'][0]['rules'][1]['priority'])->toBe(2);
            expect($conflicts['allow_deny_conflicts'][0]['rules'][2]['priority'])->toBe(3);
        });

        test('properly separates pipe delimited keys in conflict detection', function (): void {
            // Arrange
            $policy = new Policy([
                new PolicyRule('admin|user', 'document', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('admin|user', 'document', 'read', Effect::Deny, new Priority(2)),
            ]);

            // Act
            $conflicts = ConflictDetector::detect($policy);

            // Assert
            expect($conflicts['allow_deny_conflicts'])->toHaveCount(1);
            expect($conflicts['allow_deny_conflicts'][0]['subject'])->toBe('admin|user');
        });
    });
});
