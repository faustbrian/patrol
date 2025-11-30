<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\BatchPolicyEvaluator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

describe('BatchPolicyEvaluator', function (): void {
    test('evaluates batch of resources', function (): void {
        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $batchEvaluator = new BatchPolicyEvaluator($repository, $evaluator);

        $subject = new Subject('user:1');
        $resources = [
            new Resource('doc:1', 'document'),
            new Resource('doc:2', 'document'),
            new Resource('doc:3', 'document'),
        ];
        $action = new Action('read');

        $repository->shouldReceive('getPoliciesForBatch')
            ->once()
            ->with($subject, $resources)
            ->andReturn([
                'doc:1' => new Policy([
                    new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow, new Priority(1)),
                ]),
                'doc:2' => new Policy([
                    new PolicyRule('user:1', 'doc:2', 'read', Effect::Deny, new Priority(1)),
                ]),
                'doc:3' => new Policy([]), // No matching rules
            ]);

        $results = $batchEvaluator->evaluateBatch($subject, $resources, $action);

        expect($results)->toBe([
            'doc:1' => Effect::Allow,
            'doc:2' => Effect::Deny,
            'doc:3' => Effect::Deny, // Default deny
        ]);
    });

    test('handles empty resources array', function (): void {
        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $batchEvaluator = new BatchPolicyEvaluator($repository, $evaluator);

        $repository->shouldReceive('getPoliciesForBatch')
            ->once()
            ->with(Mockery::type(Subject::class), [])
            ->andReturn([]);

        $results = $batchEvaluator->evaluateBatch(
            new Subject('user:1'),
            [],
            new Action('read'),
        );

        expect($results)->toBe([]);
    });

    test('applies wildcard policies to all resources', function (): void {
        $repository = Mockery::mock(PolicyRepositoryInterface::class);
        $matcher = new RbacRuleMatcher();
        $resolver = new EffectResolver();
        $evaluator = new PolicyEvaluator($matcher, $resolver);
        $batchEvaluator = new BatchPolicyEvaluator($repository, $evaluator);

        $subject = new Subject('user:1');
        $resources = [
            new Resource('doc:1', 'document'),
            new Resource('doc:2', 'document'),
        ];
        $action = new Action('read');

        $wildcardRule = new PolicyRule('user:1', '*', 'read', Effect::Allow, new Priority(1));

        $repository->shouldReceive('getPoliciesForBatch')
            ->once()
            ->andReturn([
                'doc:1' => new Policy([$wildcardRule]),
                'doc:2' => new Policy([$wildcardRule]),
            ]);

        $results = $batchEvaluator->evaluateBatch($subject, $resources, $action);

        expect($results)->toBe([
            'doc:1' => Effect::Allow,
            'doc:2' => Effect::Allow,
        ]);
    });
});
