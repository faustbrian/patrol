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

describe('Policy', function (): void {
    test('creates empty policy', function (): void {
        $policy = new Policy();

        expect($policy->rules)->toBe([]);
    });

    test('creates policy with rules', function (): void {
        $rule = new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow);
        $policy = new Policy([$rule]);

        expect($policy->rules)->toHaveCount(1);
        expect($policy->rules[0])->toBe($rule);
    });

    test('adds rule immutably', function (): void {
        $policy = new Policy();
        $rule = new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow);

        $newPolicy = $policy->addRule($rule);

        expect($policy->rules)->toHaveCount(0);
        expect($newPolicy->rules)->toHaveCount(1);
    });

    test('sorts rules by priority descending', function (): void {
        $lowPriority = new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1));
        $medPriority = new PolicyRule('user-1', 'doc-2', 'read', Effect::Deny, new Priority(50));
        $highPriority = new PolicyRule('user-1', 'doc-3', 'read', Effect::Allow, new Priority(100));

        $policy = new Policy([$lowPriority, $highPriority, $medPriority]);
        $sorted = $policy->sortedByPriority();

        expect($sorted[0]->priority->value)->toBe(100);
        expect($sorted[1]->priority->value)->toBe(50);
        expect($sorted[2]->priority->value)->toBe(1);
    });

    test('creates policy with name', function (): void {
        $policy = new Policy([], 'BaseDocumentPolicy');

        expect($policy->name)->toBe('BaseDocumentPolicy');
    });

    test('creates policy with extends', function (): void {
        $policy = new Policy([], 'PdfPolicy', 'BaseDocumentPolicy');

        expect($policy->name)->toBe('PdfPolicy');
        expect($policy->extends)->toBe('BaseDocumentPolicy');
    });

    test('inherits rules from base policy', function (): void {
        $baseRule1 = new PolicyRule('user:*', 'doc:*', 'read', Effect::Allow, new Priority(1));
        $baseRule2 = new PolicyRule('admin:*', 'doc:*', '*', Effect::Allow, new Priority(10));
        $basePolicy = new Policy([$baseRule1, $baseRule2], 'BaseDocumentPolicy');

        $childRule = new PolicyRule('user:*', 'pdf:*', 'print', Effect::Deny, new Priority(20));
        $childPolicy = new Policy([$childRule], 'PdfPolicy', 'BaseDocumentPolicy');

        $merged = $childPolicy->inheritFrom($basePolicy);

        expect($merged->rules)->toHaveCount(3);
        expect($merged->rules[0])->toBe($baseRule1);
        expect($merged->rules[1])->toBe($baseRule2);
        expect($merged->rules[2])->toBe($childRule);
        expect($merged->name)->toBe('PdfPolicy');
        expect($merged->extends)->toBe('BaseDocumentPolicy');
    });

    test('preserves name and extends when adding rule', function (): void {
        $policy = new Policy([], 'TestPolicy', 'BasePolicy');
        $rule = new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow);

        $newPolicy = $policy->addRule($rule);

        expect($newPolicy->name)->toBe('TestPolicy');
        expect($newPolicy->extends)->toBe('BasePolicy');
    });
});
