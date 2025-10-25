<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PolicyEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new AclRuleMatcher(),
            new EffectResolver(),
        );
    });

    describe('Happy Paths', function (): void {
        test('allows access when matching allow rule exists', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('denies access when matching deny rule exists', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('applies deny-override when both allow and deny rules match', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(2)),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('evaluates only matching rules', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-2', 'doc-1', 'read', Effect::Deny), // Different subject
                new PolicyRule('user-1', 'doc-2', 'read', Effect::Deny), // Different resource
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('handles wildcard resource matching', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'document:*', 'read', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-123', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('handles superuser with wildcard permissions', function (): void {
            $policy = new Policy([
                new PolicyRule('*', '*', '*', Effect::Allow, new Priority(100)),
            ]);

            $subject = subject('admin-1', ['superuser' => true]);
            $resource = resource('any-resource', 'any-type');
            $action = patrol_action('any-action');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });
    });

    describe('Sad Paths', function (): void {
        test('denies access when no rules match', function (): void {
            $policy = new Policy([
                new PolicyRule('user-2', 'doc-1', 'read', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('denies access when policy is empty', function (): void {
            $policy = new Policy([]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles complex priority scenarios', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'document:*', 'read', Effect::Deny, new Priority(50)),
                new PolicyRule('*', '*', '*', Effect::Allow, new Priority(100)),
            ]);

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');

            // All three rules match, but deny-override applies
            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('handles type-based permissions without specific resource', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', null, 'write-article', Effect::Allow),
            ]);

            $subject = subject('user-1');
            $resource = resource('any-id', 'any-type');
            $action = patrol_action('write-article');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });
    });
});
