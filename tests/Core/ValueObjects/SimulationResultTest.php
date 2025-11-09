<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\SimulationResult;
use Patrol\Core\ValueObjects\Subject;

describe('SimulationResult', function (): void {
    test('creates result with all fields', function (): void {
        $policy = new Policy([
            new PolicyRule('user:1', 'doc:*', 'read', Effect::Allow, new Priority(1)),
        ]);
        $subject = new Subject('user:1');
        $resource = new Resource('doc:123', 'document');
        $action = new Action('read');
        $matchedRules = [$policy->rules[0]];

        $result = new SimulationResult(
            effect: Effect::Allow,
            policy: $policy,
            subject: $subject,
            resource: $resource,
            action: $action,
            executionTime: 1.23,
            matchedRules: $matchedRules,
        );

        expect($result->effect)->toBe(Effect::Allow);
        expect($result->policy)->toBe($policy);
        expect($result->subject)->toBe($subject);
        expect($result->resource)->toBe($resource);
        expect($result->action)->toBe($action);
        expect($result->executionTime)->toBe(1.23);
        expect($result->matchedRules)->toBe($matchedRules);
    });

    test('converts to array', function (): void {
        $policy = new Policy([]);
        $subject = new Subject('user:1');
        $resource = new Resource('doc:123', 'document');
        $action = new Action('read');

        $result = new SimulationResult(
            effect: Effect::Allow,
            policy: $policy,
            subject: $subject,
            resource: $resource,
            action: $action,
            executionTime: 2.45,
            matchedRules: [],
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'effect' => 'Allow',
            'subject' => 'user:1',
            'resource' => 'doc:123',
            'action' => 'read',
            'execution_time_ms' => 2.45,
            'matched_rules' => 0,
        ]);
    });

    test('array includes matched rules count', function (): void {
        $policy = new Policy([
            new PolicyRule('user:1', 'doc:*', 'read', Effect::Allow, new Priority(1)),
            new PolicyRule('user:1', 'doc:*', 'write', Effect::Deny, new Priority(2)),
        ]);
        $subject = new Subject('user:1');
        $resource = new Resource('doc:123', 'document');
        $action = new Action('read');

        $result = new SimulationResult(
            effect: Effect::Allow,
            policy: $policy,
            subject: $subject,
            resource: $resource,
            action: $action,
            executionTime: 1.0,
            matchedRules: [$policy->rules[0], $policy->rules[1]],
        );

        expect($result->toArray()['matched_rules'])->toBe(2);
    });

    test('is immutable', function (): void {
        $policy = new Policy([]);
        $result = new SimulationResult(
            effect: Effect::Allow,
            policy: $policy,
            subject: new Subject('user:1'),
            resource: new Resource('doc:123', 'document'),
            action: new Action('read'),
            executionTime: 1.0,
            matchedRules: [],
        );

        $reflection = new ReflectionClass($result);
        expect($reflection->isReadOnly())->toBeTrue();
    });
});
