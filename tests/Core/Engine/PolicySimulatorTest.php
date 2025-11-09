<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\PolicySimulator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

describe('PolicySimulator', function (): void {
    test('simulates policy evaluation', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([
            new PolicyRule('user:1', 'doc:123', 'read', Effect::Allow, new Priority(1)),
        ]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        expect($result->effect)->toBe(Effect::Allow);
        expect($result->policy)->toBe($policy);
        expect($result->subject->id)->toBe('user:1');
        expect($result->resource->id)->toBe('doc:123');
        expect($result->action->name)->toBe('read');
        expect($result->executionTime)->toBeGreaterThan(0.0);
    });

    test('simulation does not affect real policies', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([
            new PolicyRule('user:1', 'doc:*', 'read', Effect::Deny, new Priority(1)),
        ]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        expect($result->effect)->toBe(Effect::Deny);

        // Policy object is unchanged
        expect($policy->rules)->toHaveCount(1);
    });

    test('measures execution time', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([
            new PolicyRule('user:1', 'doc:*', 'read', Effect::Allow, new Priority(1)),
        ]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        expect($result->executionTime)->toBeFloat();
        expect($result->executionTime)->toBeGreaterThanOrEqual(0.0);
    });

    test('simulates deny effect', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([
            new PolicyRule('user:1', 'doc:*', 'read', Effect::Deny, new Priority(1)),
        ]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        expect($result->effect)->toBe(Effect::Deny);
    });

    test('simulates with no matching rules', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([
            new PolicyRule('user:2', 'doc:*', 'read', Effect::Allow, new Priority(1)),
        ]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        // No matching rules defaults to Deny
        expect($result->effect)->toBe(Effect::Deny);
    });

    test('simulates with empty policy', function (): void {
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $simulator = new PolicySimulator($evaluator);

        $policy = new Policy([]);

        $result = $simulator->simulate(
            $policy,
            new Subject('user:1'),
            new Resource('doc:123', 'document'),
            new Action('read'),
        );

        expect($result->effect)->toBe(Effect::Deny);
    });
});
