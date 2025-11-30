<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\PolicyComparator;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PolicyComparator', function (): void {
    test('identifies identical policies', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule2 = new PolicyRule('user', 'doc:*', 'read', Effect::Allow, new Priority(1));

        $policy1 = new Policy([$rule1, $rule2]);
        $policy2 = new Policy([$rule1, $rule2]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($policy1, $policy2);

        expect($diff->isEmpty())->toBeTrue();
        expect($diff->getChangeCount())->toBe(0);
        expect($diff->addedRules)->toHaveCount(0);
        expect($diff->removedRules)->toHaveCount(0);
        expect($diff->unchangedRules)->toHaveCount(2);
    });

    test('identifies added rules', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule2 = new PolicyRule('user', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule3 = new PolicyRule('owner', 'doc:*', 'write', Effect::Allow, new Priority(5));

        $oldPolicy = new Policy([$rule1, $rule2]);
        $newPolicy = new Policy([$rule1, $rule2, $rule3]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($oldPolicy, $newPolicy);

        expect($diff->isEmpty())->toBeFalse();
        expect($diff->getChangeCount())->toBe(1);
        expect($diff->addedRules)->toHaveCount(1);
        expect($diff->addedRules[0])->toBe($rule3);
        expect($diff->removedRules)->toHaveCount(0);
        expect($diff->unchangedRules)->toHaveCount(2);
    });

    test('identifies removed rules', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule2 = new PolicyRule('user', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule3 = new PolicyRule('owner', 'doc:*', 'write', Effect::Allow, new Priority(5));

        $oldPolicy = new Policy([$rule1, $rule2, $rule3]);
        $newPolicy = new Policy([$rule1, $rule2]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($oldPolicy, $newPolicy);

        expect($diff->isEmpty())->toBeFalse();
        expect($diff->getChangeCount())->toBe(1);
        expect($diff->addedRules)->toHaveCount(0);
        expect($diff->removedRules)->toHaveCount(1);
        expect($diff->removedRules[0])->toBe($rule3);
        expect($diff->unchangedRules)->toHaveCount(2);
    });

    test('identifies both added and removed rules', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule2 = new PolicyRule('user', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule3 = new PolicyRule('owner', 'doc:*', 'write', Effect::Allow, new Priority(5));
        $rule4 = new PolicyRule('guest', 'doc:*', 'view', Effect::Allow, new Priority(1));

        $oldPolicy = new Policy([$rule1, $rule2, $rule3]);
        $newPolicy = new Policy([$rule1, $rule2, $rule4]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($oldPolicy, $newPolicy);

        expect($diff->getChangeCount())->toBe(2);
        expect($diff->addedRules)->toHaveCount(1);
        expect($diff->addedRules[0])->toBe($rule4);
        expect($diff->removedRules)->toHaveCount(1);
        expect($diff->removedRules[0])->toBe($rule3);
        expect($diff->unchangedRules)->toHaveCount(2);
    });

    test('compares empty policies', function (): void {
        $policy1 = new Policy([]);
        $policy2 = new Policy([]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($policy1, $policy2);

        expect($diff->isEmpty())->toBeTrue();
        expect($diff->getChangeCount())->toBe(0);
    });

    test('compares empty policy with non-empty policy', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));

        $emptyPolicy = new Policy([]);
        $nonEmptyPolicy = new Policy([$rule1]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($emptyPolicy, $nonEmptyPolicy);

        expect($diff->addedRules)->toHaveCount(1);
        expect($diff->removedRules)->toHaveCount(0);
    });

    test('matches rules by signature ignoring effect and priority', function (): void {
        $rule1 = new PolicyRule('admin', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $rule2 = new PolicyRule('admin', 'doc:*', 'read', Effect::Deny, new Priority(10));

        $policy1 = new Policy([$rule1]);
        $policy2 = new Policy([$rule2]);

        $comparator = new PolicyComparator();
        $diff = $comparator->compare($policy1, $policy2);

        // Same signature, so treated as unchanged
        expect($diff->unchangedRules)->toHaveCount(1);
        expect($diff->addedRules)->toHaveCount(0);
        expect($diff->removedRules)->toHaveCount(0);
    });
});
