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
use Patrol\Core\ValueObjects\Priority;

describe('PolicyEvaluator with ABAC', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new AbacRuleMatcher(
                new AttributeResolver(),
            ),
            new EffectResolver(),
        );
    });

    describe('Happy Paths', function (): void {
        test('allows resource owner to edit their document', function (): void {
            $policy = new Policy([
                new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['owner' => 'user-1']);
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('allows users in same department to read documents', function (): void {
            $policy = new Policy([
                new PolicyRule('subject.department == resource.department', 'document:*', 'read', Effect::Allow),
            ]);

            $subject = subject('user-1', ['department' => 'engineering']);
            $resource = resource('doc-1', 'document', ['department' => 'engineering']);
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('allows editing non-archived documents', function (): void {
            $policy = new Policy([
                new PolicyRule('resource.status != archived', 'document:*', 'edit', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['status' => 'draft']);
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });
    });

    describe('Sad Paths', function (): void {
        test('denies when resource owner does not match', function (): void {
            $policy = new Policy([
                new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['owner' => 'user-2']);
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('denies editing archived documents', function (): void {
            $policy = new Policy([
                new PolicyRule('resource.status != archived', 'document:*', 'edit', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['status' => 'archived']);
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });
    });

    describe('Edge Cases', function (): void {
        test('combines ABAC with deny-override', function (): void {
            $policy = new Policy([
                new PolicyRule('resource.owner == subject.id', 'document:*', 'delete', Effect::Allow, new Priority(1)),
                new PolicyRule('resource.protected == true', 'document:*', 'delete', Effect::Deny, new Priority(100)),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['owner' => 'user-1', 'protected' => true]);
            $action = patrol_action('delete');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });
    });
});
