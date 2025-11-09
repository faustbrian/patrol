<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Laravel\Testing\Factories\PolicyFactory;

describe('PolicyFactory', function (): void {
    describe('allow()', function (): void {
        test('creates policy with single allow rule', function (): void {
            $policy = PolicyFactory::allow('admin', 'doc:*', 'read');

            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('admin');
            expect($policy->rules[0]->resource)->toBe('doc:*');
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
            expect($policy->rules[0]->priority->value)->toBe(1);
        });

        test('creates policy with custom priority', function (): void {
            $policy = PolicyFactory::allow('admin', 'doc:*', 'read', 10);

            expect($policy->rules[0]->priority->value)->toBe(10);
        });

        test('creates multiple rules for array of subjects', function (): void {
            $policy = PolicyFactory::allow(['admin', 'owner'], 'doc:*', 'read');

            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->subject)->toBe('admin');
            expect($policy->rules[1]->subject)->toBe('owner');
        });

        test('creates multiple rules for array of resources', function (): void {
            $policy = PolicyFactory::allow('admin', ['doc:*', 'pdf:*'], 'read');

            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->resource)->toBe('doc:*');
            expect($policy->rules[1]->resource)->toBe('pdf:*');
        });

        test('creates multiple rules for array of actions', function (): void {
            $policy = PolicyFactory::allow('admin', 'doc:*', ['read', 'write']);

            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[1]->action)->toBe('write');
        });

        test('creates cartesian product for multiple arrays', function (): void {
            $policy = PolicyFactory::allow(
                ['admin', 'owner'],
                ['doc:*', 'pdf:*'],
                ['read', 'write'],
            );

            // 2 subjects × 2 resources × 2 actions = 8 rules
            expect($policy->rules)->toHaveCount(8);
        });
    });

    describe('deny()', function (): void {
        test('creates policy with single deny rule', function (): void {
            $policy = PolicyFactory::deny('guest', 'doc:*', 'delete');

            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('guest');
            expect($policy->rules[0]->resource)->toBe('doc:*');
            expect($policy->rules[0]->action)->toBe('delete');
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
        });

        test('creates multiple deny rules for arrays', function (): void {
            $policy = PolicyFactory::deny(['guest', 'user'], 'admin:*', ['delete', 'update']);

            expect($policy->rules)->toHaveCount(4);
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
        });
    });

    describe('empty()', function (): void {
        test('creates empty policy', function (): void {
            $policy = PolicyFactory::empty();

            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toHaveCount(0);
        });
    });

    describe('merge()', function (): void {
        test('merges multiple policies', function (): void {
            $policy1 = PolicyFactory::allow('admin', 'doc:*', 'read');
            $policy2 = PolicyFactory::allow('owner', 'doc:*', 'write');
            $policy3 = PolicyFactory::deny('guest', 'doc:*', 'delete');

            $merged = PolicyFactory::merge($policy1, $policy2, $policy3);

            expect($merged->rules)->toHaveCount(3);
            expect($merged->rules[0]->subject)->toBe('admin');
            expect($merged->rules[1]->subject)->toBe('owner');
            expect($merged->rules[2]->subject)->toBe('guest');
        });

        test('merges empty policy', function (): void {
            $policy1 = PolicyFactory::allow('admin', 'doc:*', 'read');
            $empty = PolicyFactory::empty();

            $merged = PolicyFactory::merge($policy1, $empty);

            expect($merged->rules)->toHaveCount(1);
        });

        test('merges policies with multiple rules', function (): void {
            $policy1 = PolicyFactory::allow(['admin', 'owner'], 'doc:*', 'read');
            $policy2 = PolicyFactory::deny('guest', 'doc:*', ['delete', 'update']);

            $merged = PolicyFactory::merge($policy1, $policy2);

            expect($merged->rules)->toHaveCount(4);
        });
    });
});
