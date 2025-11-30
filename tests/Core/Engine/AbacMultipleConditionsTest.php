<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AbacRuleMatcher;
use Patrol\Core\Engine\AttributeResolver;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;

describe('ABAC Multiple Conditions', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new AbacRuleMatcher(
                new AttributeResolver(),
            ),
            new EffectResolver(),
        );
    });

    test('multiple ABAC conditions demonstrate OR logic', function (): void {
        $policy = new Policy([
            // Can edit if you're the owner
            new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow),
            // OR if you're in the same department
            new PolicyRule('subject.department == resource.department', 'document:*', 'edit', Effect::Allow),
        ]);

        $subject = subject('user-1', ['department' => 'engineering']);

        // Scenario 1: Is owner but wrong department - ALLOW (first rule matches)
        $resource1 = resource('doc-1', 'document', [
            'owner' => 'user-1',
            'department' => 'sales',
        ]);
        $result = $this->evaluator->evaluate($policy, $subject, $resource1, patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Scenario 2: Right department but not owner - ALLOW (second rule matches)
        $resource2 = resource('doc-2', 'document', [
            'owner' => 'user-2',
            'department' => 'engineering',
        ]);
        $result = $this->evaluator->evaluate($policy, $subject, $resource2, patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Scenario 3: Neither owner nor same department - DENY
        $resource3 = resource('doc-3', 'document', [
            'owner' => 'user-2',
            'department' => 'sales',
        ]);
        $result = $this->evaluator->evaluate($policy, $subject, $resource3, patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);
    });

    test('ABAC conditions can be combined with deny rules', function (): void {
        $policy = new Policy([
            // Can edit if you're the owner
            new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow),
            // But not if the document is archived
            new PolicyRule('resource.status == archived', 'document:*', 'edit', Effect::Deny),
        ]);

        $subject = subject('user-1');

        // Can edit own active documents
        $activeDoc = resource('doc-1', 'document', [
            'owner' => 'user-1',
            'status' => 'active',
        ]);
        expect($this->evaluator->evaluate($policy, $subject, $activeDoc, patrol_action('edit')))
            ->toBe(Effect::Allow);

        // Cannot edit even own archived documents (deny wins)
        $archivedDoc = resource('doc-2', 'document', [
            'owner' => 'user-1',
            'status' => 'archived',
        ]);
        expect($this->evaluator->evaluate($policy, $subject, $archivedDoc, patrol_action('edit')))
            ->toBe(Effect::Deny);
    });
});
