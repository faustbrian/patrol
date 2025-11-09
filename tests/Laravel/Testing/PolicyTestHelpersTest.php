<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Laravel\Testing\PolicyTestHelpers;

uses(PolicyTestHelpers::class);

describe('PolicyTestHelpers', function (): void {
    describe('Happy Paths', function (): void {
        test('createPolicy creates valid Policy object', function (): void {
            $rule1 = new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow);
            $rule2 = new PolicyRule('user:2', 'doc:2', 'write', Effect::Deny);

            $policy = $this->createPolicy([$rule1, $rule2]);

            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toHaveCount(2);
            expect($policy->rules[0])->toBe($rule1);
            expect($policy->rules[1])->toBe($rule2);
        });

        test('createPolicy creates empty Policy when no rules provided', function (): void {
            $policy = $this->createPolicy([]);

            expect($policy)->toBeInstanceOf(Policy::class);
            expect($policy->rules)->toBeEmpty();
        });

        test('createAllowRule creates rule with Allow effect', function (): void {
            $rule = $this->createAllowRule('user:1', 'doc:1', 'read');

            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('user:1');
            expect($rule->resource)->toBe('doc:1');
            expect($rule->action)->toBe('read');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(1);
            expect($rule->domain)->toBeNull();
        });

        test('createDenyRule creates rule with Deny effect', function (): void {
            $rule = $this->createDenyRule('user:2', 'doc:2', 'delete');

            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('user:2');
            expect($rule->resource)->toBe('doc:2');
            expect($rule->action)->toBe('delete');
            expect($rule->effect)->toBe(Effect::Deny);
            expect($rule->priority->value)->toBe(1);
            expect($rule->domain)->toBeNull();
        });

        test('createWildcardRule creates rule with wildcards', function (): void {
            $rule = $this->createWildcardRule('manage');

            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('*');
            expect($rule->resource)->toBe('*');
            expect($rule->action)->toBe('manage');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(1);
        });

        test('createWildcardRule accepts custom effect', function (): void {
            $rule = $this->createWildcardRule('restricted', Effect::Deny);

            expect($rule->effect)->toBe(Effect::Deny);
        });

        test('createSuperuserRule creates superuser rule', function (): void {
            $rule = $this->createSuperuserRule('admin');

            expect($rule)->toBeInstanceOf(PolicyRule::class);
            expect($rule->subject)->toBe('admin');
            expect($rule->resource)->toBe('*');
            expect($rule->action)->toBe('*');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority->value)->toBe(100);
        });

        test('rules with custom priority', function (): void {
            $allowRule = $this->createAllowRule('user:1', 'doc:1', 'read', 50);
            $denyRule = $this->createDenyRule('user:2', 'doc:2', 'write', 75);

            expect($allowRule->priority->value)->toBe(50);
            expect($denyRule->priority->value)->toBe(75);
        });

        test('rules with domain scoping', function (): void {
            $allowRule = $this->createAllowRule('user:1', 'doc:1', 'read', 1, 'tenant-123');
            $denyRule = $this->createDenyRule('user:2', 'doc:2', 'write', 1, 'tenant-456');

            expect($allowRule->domain)->toBeInstanceOf(Domain::class);
            expect($allowRule->domain->id)->toBe('tenant-123');
            expect($denyRule->domain)->toBeInstanceOf(Domain::class);
            expect($denyRule->domain->id)->toBe('tenant-456');
        });
    });

    describe('Edge Cases', function (): void {
        test('createAllowRule with null resource', function (): void {
            $rule = $this->createAllowRule('user:1', null, 'special-action');

            expect($rule->resource)->toBeNull();
            expect($rule->subject)->toBe('user:1');
            expect($rule->action)->toBe('special-action');
            expect($rule->effect)->toBe(Effect::Allow);
        });

        test('createDenyRule with null resource', function (): void {
            $rule = $this->createDenyRule('user:2', null, 'forbidden-action');

            expect($rule->resource)->toBeNull();
            expect($rule->subject)->toBe('user:2');
            expect($rule->action)->toBe('forbidden-action');
            expect($rule->effect)->toBe(Effect::Deny);
        });

        test('createWildcardRule with Deny effect', function (): void {
            $rule = $this->createWildcardRule('dangerous', Effect::Deny);

            expect($rule->subject)->toBe('*');
            expect($rule->resource)->toBe('*');
            expect($rule->action)->toBe('dangerous');
            expect($rule->effect)->toBe(Effect::Deny);
        });

        test('createWildcardRule with custom priority', function (): void {
            $rule = $this->createWildcardRule('action', Effect::Allow, 999);

            expect($rule->priority->value)->toBe(999);
        });

        test('createSuperuserRule with custom priority', function (): void {
            $rule = $this->createSuperuserRule('root', 1_000);

            expect($rule->subject)->toBe('root');
            expect($rule->priority->value)->toBe(1_000);
        });

        test('very high priority values', function (): void {
            $rule = $this->createAllowRule('user:1', 'doc:1', 'read', 999_999);

            expect($rule->priority->value)->toBe(999_999);
        });

        test('priority value of zero', function (): void {
            $rule = $this->createAllowRule('user:1', 'doc:1', 'read', 0);

            expect($rule->priority->value)->toBe(0);
        });

        test('domain with special characters', function (): void {
            $rule = $this->createAllowRule(
                'user:1',
                'doc:1',
                'read',
                1,
                'tenant-123-abc_def.test',
            );

            expect($rule->domain->id)->toBe('tenant-123-abc_def.test');
        });

        test('creating complex policy with multiple helper methods', function (): void {
            $rules = [
                $this->createSuperuserRule('admin', 100),
                $this->createAllowRule('editor', 'articles:*', 'write', 50),
                $this->createDenyRule('editor', 'articles:published', 'delete', 75),
                $this->createWildcardRule('read', Effect::Allow, 10),
                $this->createAllowRule('user:123', null, 'view-dashboard', 1, 'tenant-xyz'),
            ];

            $policy = $this->createPolicy($rules);

            expect($policy->rules)->toHaveCount(5);

            // Verify admin rule
            expect($policy->rules[0]->subject)->toBe('admin');
            expect($policy->rules[0]->priority->value)->toBe(100);

            // Verify editor allow rule
            expect($policy->rules[1]->subject)->toBe('editor');
            expect($policy->rules[1]->effect)->toBe(Effect::Allow);

            // Verify editor deny rule
            expect($policy->rules[2]->effect)->toBe(Effect::Deny);
            expect($policy->rules[2]->priority->value)->toBe(75);

            // Verify wildcard rule
            expect($policy->rules[3]->subject)->toBe('*');
            expect($policy->rules[3]->resource)->toBe('*');

            // Verify domain-scoped rule
            expect($policy->rules[4]->domain->id)->toBe('tenant-xyz');
            expect($policy->rules[4]->resource)->toBeNull();
        });

        test('subject with special characters and patterns', function (): void {
            $rule1 = $this->createAllowRule('user:123-abc', 'doc:1', 'read');
            $rule2 = $this->createDenyRule('role:admin-super_user', 'doc:2', 'write');

            expect($rule1->subject)->toBe('user:123-abc');
            expect($rule2->subject)->toBe('role:admin-super_user');
        });

        test('resource with special characters and patterns', function (): void {
            $rule1 = $this->createAllowRule('user:1', 'resource:123-abc_def', 'read');
            $rule2 = $this->createDenyRule('user:2', 'api:/v1/users/*', 'delete');

            expect($rule1->resource)->toBe('resource:123-abc_def');
            expect($rule2->resource)->toBe('api:/v1/users/*');
        });

        test('action with special characters and patterns', function (): void {
            $rule1 = $this->createAllowRule('user:1', 'doc:1', 'custom-action');
            $rule2 = $this->createDenyRule('user:2', 'doc:2', 'admin:delete-all');

            expect($rule1->action)->toBe('custom-action');
            expect($rule2->action)->toBe('admin:delete-all');
        });

        test('combining multiple rules with different priorities creates proper policy', function (): void {
            $policy = $this->createPolicy([
                $this->createAllowRule('user:1', 'doc:*', 'read', 1),
                $this->createDenyRule('user:1', 'doc:secret', 'read', 100),
                $this->createSuperuserRule('admin', 1_000),
            ]);

            $sorted = $policy->sortedByPriority();

            expect($sorted[0]->priority->value)->toBe(1_000); // Superuser highest
            expect($sorted[1]->priority->value)->toBe(100);  // Deny second
            expect($sorted[2]->priority->value)->toBe(1);    // Allow lowest
        });
    });
});
