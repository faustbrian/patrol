<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\PolicyInheritance;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PolicyInheritance', function (): void {
    beforeEach(function (): void {
        $this->inheritance = new PolicyInheritance();
    });

    describe('Happy Paths', function (): void {
        test('expands inherited rules from single-level parent', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:abc', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:abc/document:xyz', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[0]->resource)->toBe('folder:abc');
            expect($expanded->rules[1]->resource)->toBe('folder:abc/document:xyz');
            expect($expanded->rules[1]->subject)->toBe('user:123');
            expect($expanded->rules[1]->action)->toBe('read');
            expect($expanded->rules[1]->effect)->toBe(Effect::Allow);
        });

        test('expands inherited rules from multi-level hierarchy', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'org:1', 'manage', Effect::Allow, new Priority(1)),
                new PolicyRule('user:123', 'org:1/dept:2', 'read', Effect::Allow, new Priority(2)),
            ]);
            $resource = resource('org:1/dept:2/doc:3', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(4);
            // Original rules preserved
            expect($expanded->rules[0]->resource)->toBe('org:1');
            expect($expanded->rules[2]->resource)->toBe('org:1/dept:2');
            // Inherited rules created
            expect($expanded->rules[1]->resource)->toBe('org:1/dept:2/doc:3');
            expect($expanded->rules[1]->action)->toBe('manage');
            expect($expanded->rules[3]->resource)->toBe('org:1/dept:2/doc:3');
            expect($expanded->rules[3]->action)->toBe('read');
        });

        test('preserves all original rules in expanded policy', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'folder:abc', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:2', 'folder:xyz', 'write', Effect::Deny, new Priority(2)),
                new PolicyRule('user:3', 'file:123', 'delete', Effect::Allow, new Priority(3)),
            ]);
            $resource = resource('folder:abc/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Should have 3 original + 1 inherited = 4 rules
            expect($expanded->rules)->toHaveCount(4);
            expect($expanded->rules[0]->subject)->toBe('user:1');
            expect($expanded->rules[2]->subject)->toBe('user:2');
            expect($expanded->rules[3]->subject)->toBe('user:3');
        });

        test('inherits rule with all properties intact', function (): void {
            $domain = new Domain('tenant-1');
            $priority = new Priority(100);
            $policy = new Policy([
                new PolicyRule('admin', 'folder:root', 'delete', Effect::Deny, $priority, $domain),
            ]);
            $resource = resource('folder:root/subfolder:child', 'folder');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            $inheritedRule = $expanded->rules[1];
            expect($inheritedRule->subject)->toBe('admin');
            expect($inheritedRule->resource)->toBe('folder:root/subfolder:child');
            expect($inheritedRule->action)->toBe('delete');
            expect($inheritedRule->effect)->toBe(Effect::Deny);
            expect($inheritedRule->priority)->toBe($priority);
            expect($inheritedRule->domain)->toBe($domain);
        });

        test('handles multiple parent rules inheriting to same child', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'folder:123', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:2', 'folder:123', 'write', Effect::Deny, new Priority(2)),
                new PolicyRule('user:3', 'folder:123', 'delete', Effect::Allow, new Priority(3)),
            ]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // 3 original + 3 inherited = 6 rules
            expect($expanded->rules)->toHaveCount(6);
            expect($expanded->rules[1]->resource)->toBe('folder:123/doc:456');
            expect($expanded->rules[1]->action)->toBe('read');
            expect($expanded->rules[3]->resource)->toBe('folder:123/doc:456');
            expect($expanded->rules[3]->action)->toBe('write');
            expect($expanded->rules[5]->resource)->toBe('folder:123/doc:456');
            expect($expanded->rules[5]->action)->toBe('delete');
        });

        test('works with different resource type hierarchies', function (): void {
            $policy = new Policy([
                new PolicyRule('manager', 'organization:acme', 'view', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('organization:acme/department:sales/team:west/user:john', 'user');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe('organization:acme/department:sales/team:west/user:john');
            expect($expanded->rules[1]->action)->toBe('view');
        });
    });

    describe('Sad Paths', function (): void {
        test('excludes wildcard resource from inheritance', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', '*', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only original rule, no inheritance
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('*');
        });

        test('excludes null resource from inheritance', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', null, 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only original rule, no inheritance
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBeNull();
        });

        test('non-matching resource paths do not create inherited rules', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:123', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:456/doc:789', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only original rule, no inheritance
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('folder:123');
        });

        test('sibling resources do not inherit from each other', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:123/doc:1', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/doc:2', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only original rule, no inheritance
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('folder:123/doc:1');
        });

        test('partial path matches do not trigger inheritance', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:12', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only original rule, no inheritance (folder:12 != folder:123)
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('folder:12');
        });
    });

    describe('Edge Cases', function (): void {
        test('empty policy returns empty expanded policy', function (): void {
            $policy = new Policy([]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toBeEmpty();
        });

        test('resource with no matching parents returns original policy', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'folder:abc', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:2', 'folder:xyz', 'write', Effect::Allow, new Priority(2)),
            ]);
            $resource = resource('unrelated:resource', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // Only 2 original rules, no inheritance
            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[0]->resource)->toBe('folder:abc');
            expect($expanded->rules[1]->resource)->toBe('folder:xyz');
        });

        test('deep nested hierarchy with 10+ levels', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'level0', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('level0/level1/level2/level3/level4/level5/level6/level7/level8/level9/level10', 'item');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe('level0/level1/level2/level3/level4/level5/level6/level7/level8/level9/level10');
        });

        test('resource path with special characters', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:abc-123_test', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:abc-123_test/doc:xyz-456_file', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe('folder:abc-123_test/doc:xyz-456_file');
        });

        test('very long resource paths', function (): void {
            $longParent = 'folder:'.str_repeat('a', 200);
            $longChild = $longParent.'/document:'.str_repeat('b', 200);

            $policy = new Policy([
                new PolicyRule('user:123', $longParent, 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource($longChild, 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe($longChild);
        });

        test('resource paths with similar prefixes', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:123', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:456', 'folder:1234', 'write', Effect::Allow, new Priority(2)),
            ]);
            $resource = resource('folder:1234/doc:abc', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // 2 original + 1 inherited (only from folder:1234, not folder:123)
            expect($expanded->rules)->toHaveCount(3);
            expect($expanded->rules[0]->resource)->toBe('folder:123');
            expect($expanded->rules[1]->resource)->toBe('folder:1234');
            expect($expanded->rules[2]->resource)->toBe('folder:1234/doc:abc');
            expect($expanded->rules[2]->subject)->toBe('user:456');
        });

        test('unicode characters in resource paths', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:文档', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:文档/document:файл', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe('folder:文档/document:файл');
        });

        test('multiple slashes in resource path', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:123/sub:456', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/sub:456/doc:789', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            expect($expanded->rules)->toHaveCount(2);
            expect($expanded->rules[1]->resource)->toBe('folder:123/sub:456/doc:789');
        });

        test('resource ID exactly matching rule resource without slash', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', 'folder:123', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123', 'folder');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // No inheritance because resource doesn't have slash after parent path
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('folder:123');
        });

        test('rule with empty string resource', function (): void {
            $policy = new Policy([
                new PolicyRule('user:123', '', 'read', Effect::Allow, new Priority(1)),
            ]);
            $resource = resource('folder:123/doc:456', 'document');

            $expanded = $this->inheritance->expandInheritedRules($policy, $resource);

            // No inheritance from empty string resource
            expect($expanded->rules)->toHaveCount(1);
            expect($expanded->rules[0]->resource)->toBe('');
        });
    });
});
