<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Laravel\Builders\RbacPolicyBuilder;

describe('RbacPolicyBuilder', function (): void {
    describe('Happy Paths', function (): void {
        test('can build role-based policy', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('admin')
                ->on('posts')
                ->can('edit')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('role:admin');
            expect($policy->rules[0]->action)->toBe('edit');
            expect($policy->rules[0]->resource)->toBe('posts');
        });

        test('can assign multiple actions to a role', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('editor')
                ->on('articles')
                ->can(['read', 'write', 'update'])
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(3);

            foreach ($policy->rules as $rule) {
                expect($rule->subject)->toBe('role:editor');
                expect($rule->resource)->toBe('articles');
                expect($rule->effect)->toBe(Effect::Allow);
            }

            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);
            expect($actions)->toContain('read', 'write', 'update');
        });

        test('fullAccess grants all permissions', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('superadmin')
                ->fullAccess()
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->subject)->toBe('role:superadmin');
            expect($policy->rules[0]->action)->toBe('*');
            expect($policy->rules[0]->resource)->toBe('*');
            expect($policy->rules[0]->priority->value)->toBe(100);
        });

        test('can deny actions for a role', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('guest')
                ->cannot(['delete', 'update'])
                ->on('posts')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);

            foreach ($policy->rules as $rule) {
                expect($rule->effect)->toBe(Effect::Deny);
            }
        });

        test('readOnly grants only read permission', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('viewer')
                ->readOnly()
                ->on('documents')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('readWrite grants read and write actions', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('contributor')
                ->readWrite()
                ->on('wiki')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(4);

            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);
            expect($actions)->toContain('read', 'write', 'create', 'update');
        });

        test('onAny allows access to all resources', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('moderator')
                ->onAny()
                ->can('moderate')
                ->build();

            // Assert
            expect($policy->rules[0]->resource)->toBe('*');
        });

        test('can set custom priorities', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('manager')
                ->withPriority(50)
                ->can('approve')
                ->on('requests')
                ->build();

            // Assert
            expect($policy->rules[0]->priority->value)->toBe(50);
        });

        test('can chain multiple roles fluently', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('admin')
                ->fullAccess()
                ->role('editor')
                ->can(['read', 'write'])
                ->on('posts')
                ->role('viewer')
                ->readOnly()
                ->onAny()
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(4);
        });

        test('resource context resets when switching roles', function (): void {
            // Arrange & Act
            $policy = RbacPolicyBuilder::make()
                ->role('editor')
                ->on('posts')
                ->can('edit')
                ->role('viewer')
                ->on('articles')
                ->can('read')
                ->build();

            // Assert
            expect($policy->rules[0]->resource)->toBe('posts');
            expect($policy->rules[1]->resource)->toBe('articles');
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when can() called without role()', function (): void {
            // Arrange & Act & Assert
            expect(fn (): RbacPolicyBuilder => RbacPolicyBuilder::make()->can('read'))
                ->toThrow(LogicException::class, 'Must call role() before can()');
        });

        test('throws exception when cannot() called without role()', function (): void {
            // Arrange & Act & Assert
            expect(fn (): RbacPolicyBuilder => RbacPolicyBuilder::make()->cannot('delete'))
                ->toThrow(LogicException::class, 'Must call role() before cannot()');
        });

        test('throws exception when fullAccess() called without role()', function (): void {
            // Arrange & Act & Assert
            expect(fn (): RbacPolicyBuilder => RbacPolicyBuilder::make()->fullAccess())
                ->toThrow(LogicException::class, 'Must call role() before fullAccess()');
        });
    });
});
