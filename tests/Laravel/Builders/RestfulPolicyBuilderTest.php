<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Laravel\Builders\RestfulPolicyBuilder;

describe('RestfulPolicyBuilder', function (): void {
    describe('Happy Paths', function (): void {
        test('can build RESTful policy with GET permissions', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('posts')
                ->allowGetFor('role:viewer')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('role:viewer');
            expect($policy->rules[0]->resource)->toBe('posts');
            expect($policy->rules[0]->action)->toBe('GET');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('can build RESTful policy with all HTTP methods', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('api/users')
                ->allowGetFor('*')
                ->allowPostFor('role:admin')
                ->allowPutFor('role:editor')
                ->allowPatchFor('role:editor')
                ->allowDeleteFor('role:admin')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(5);
            expect($policy->rules[0]->action)->toBe('GET');
            expect($policy->rules[1]->action)->toBe('POST');
            expect($policy->rules[2]->action)->toBe('PUT');
            expect($policy->rules[3]->action)->toBe('PATCH');
            expect($policy->rules[4]->action)->toBe('DELETE');
        });

        test('can set custom priorities for rules', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('documents')
                ->allowGetFor('role:viewer', priority: 5)
                ->denyGetFor('role:banned', priority: 10)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->priority->value)->toBe(5);
            expect($policy->rules[1]->priority->value)->toBe(10);
        });

        test('can deny HTTP methods', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('sensitive')
                ->denyDeleteFor('*')
                ->denyPutFor('role:guest')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });

        test('can deny POST requests for a subject', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('protected-resource')
                ->denyPostFor('role:guest')
                ->denyPostFor('role:banned', priority: 20)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->action)->toBe('POST');
            expect($policy->rules[0]->subject)->toBe('role:guest');
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[0]->priority->value)->toBe(10);
            expect($policy->rules[1]->action)->toBe('POST');
            expect($policy->rules[1]->subject)->toBe('role:banned');
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
            expect($policy->rules[1]->priority->value)->toBe(20);
        });

        test('can deny PATCH requests for a subject', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('protected-resource')
                ->denyPatchFor('role:guest')
                ->denyPatchFor('role:banned', priority: 15)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->action)->toBe('PATCH');
            expect($policy->rules[0]->subject)->toBe('role:guest');
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[0]->priority->value)->toBe(10);
            expect($policy->rules[1]->action)->toBe('PATCH');
            expect($policy->rules[1]->subject)->toBe('role:banned');
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
            expect($policy->rules[1]->priority->value)->toBe(15);
        });

        test('allowAllFor grants all HTTP methods', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('admin-panel')
                ->allowAllFor('role:admin')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(5);

            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);
            expect($actions)->toContain('GET', 'POST', 'PUT', 'PATCH', 'DELETE');

            foreach ($policy->rules as $rule) {
                expect($rule->subject)->toBe('role:admin');
                expect($rule->effect)->toBe(Effect::Allow);
            }
        });

        test('allowReadOnlyFor only grants GET permission', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('public-api')
                ->allowReadOnlyFor('*')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('GET');
            expect($policy->rules[0]->subject)->toBe('*');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('can chain multiple rules fluently', function (): void {
            // Arrange & Act
            $policy = RestfulPolicyBuilder::for('posts')
                ->allowGetFor('*')
                ->allowPostFor('role:contributor')
                ->allowPutFor('role:editor')
                ->denyDeleteFor('role:contributor')
                ->allowDeleteFor('role:admin', priority: 10)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(5);
        });
    });
});
