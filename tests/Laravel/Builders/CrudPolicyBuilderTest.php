<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Laravel\Builders\CrudPolicyBuilder;

describe('CrudPolicyBuilder', function (): void {
    describe('Happy Paths', function (): void {
        test('can build CRUD policy with read permissions', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('documents')
                ->allowReadFor('*')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->subject)->toBe('*');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('can build CRUD policy with all operations', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('posts')
                ->allowCreateFor('role:contributor')
                ->allowReadFor('*')
                ->allowUpdateFor('role:editor')
                ->allowDeleteFor('role:admin')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(4);
            expect($policy->rules[0]->action)->toBe('create');
            expect($policy->rules[1]->action)->toBe('read');
            expect($policy->rules[2]->action)->toBe('update');
            expect($policy->rules[3]->action)->toBe('delete');
        });

        test('can deny CRUD operations', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('protected')
                ->denyDeleteFor('*')
                ->denyUpdateFor('role:guest')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[1]->effect)->toBe(Effect::Deny);
        });

        test('denyCreateFor denies create action with default priority', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('restricted')
                ->denyCreateFor('role:guest')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('create');
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[0]->subject)->toBe('role:guest');
            expect($policy->rules[0]->priority->value)->toBe(10);
        });

        test('denyCreateFor denies create action with custom priority', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('restricted')
                ->denyCreateFor('role:guest', priority: 50)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('create');
            expect($policy->rules[0]->effect)->toBe(Effect::Deny);
            expect($policy->rules[0]->priority->value)->toBe(50);
        });

        test('can set custom priorities', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('files')
                ->allowReadFor('*', priority: 1)
                ->denyReadFor('role:banned', priority: 100)
                ->build();

            // Assert
            expect($policy->rules[0]->priority->value)->toBe(1);
            expect($policy->rules[1]->priority->value)->toBe(100);
        });

        test('allowAllFor grants all CRUD operations', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('admin-resources')
                ->allowAllFor('role:superadmin')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(4);

            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);
            expect($actions)->toContain('create', 'read', 'update', 'delete');

            foreach ($policy->rules as $rule) {
                expect($rule->subject)->toBe('role:superadmin');
                expect($rule->effect)->toBe(Effect::Allow);
            }
        });

        test('allowReadOnlyFor only grants read permission', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('public-data')
                ->allowReadOnlyFor('*')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(1);
            expect($policy->rules[0]->action)->toBe('read');
            expect($policy->rules[0]->effect)->toBe(Effect::Allow);
        });

        test('allowReadWriteFor grants read, create, and update but not delete', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('shared-docs')
                ->allowReadWriteFor('role:collaborator')
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(3);

            $actions = array_map(fn (PolicyRule $rule): string => $rule->action, $policy->rules);
            expect($actions)->toContain('read', 'create', 'update');
            expect($actions)->not->toContain('delete');

            foreach ($policy->rules as $rule) {
                expect($rule->subject)->toBe('role:collaborator');
                expect($rule->effect)->toBe(Effect::Allow);
            }
        });

        test('can chain multiple rules fluently', function (): void {
            // Arrange & Act
            $policy = CrudPolicyBuilder::for('articles')
                ->allowReadFor('*')
                ->allowCreateFor('role:writer')
                ->allowUpdateFor('role:editor')
                ->denyDeleteFor('role:writer')
                ->allowDeleteFor('role:admin', priority: 10)
                ->build();

            // Assert
            expect($policy->rules)->toHaveCount(5);
        });
    });
});
