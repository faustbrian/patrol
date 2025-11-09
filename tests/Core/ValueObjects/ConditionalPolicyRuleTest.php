<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\Date;
use Patrol\Core\ValueObjects\ConditionalPolicyRule;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('ConditionalPolicyRule', function (): void {
    describe('Happy Paths', function (): void {
        test('creates conditional rule with all required parameters', function (): void {
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
            );

            expect($rule->subject)->toBe('user:123');
            expect($rule->resource)->toBe('documents');
            expect($rule->action)->toBe('read');
            expect($rule->effect)->toBe(Effect::Allow);
            expect($rule->priority)->toBeInstanceOf(Priority::class);
            expect($rule->domain)->toBeNull();
            expect($rule->condition)->toBeNull();
        });

        test('creates conditional rule with optional domain parameter', function (): void {
            $domain = new Domain('tenant-1');
            $rule = new ConditionalPolicyRule(
                subject: 'admin',
                resource: 'secrets',
                action: 'write',
                effect: Effect::Deny,
                domain: $domain,
            );

            expect($rule->domain)->toBe($domain);
        });

        test('creates conditional rule with condition closure', function (): void {
            $condition = fn (array $context): bool => $context['is_admin'] ?? false;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->condition)->toBe($condition);
        });

        test('creates conditional rule with custom priority', function (): void {
            $priority = new Priority(100);
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                priority: $priority,
            );

            expect($rule->priority)->toBe($priority);
        });

        test('evaluates condition and returns true when condition passes', function (): void {
            $condition = fn (array $context): bool => $context['is_admin'] === true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition(['is_admin' => true]);

            expect($result)->toBeTrue();
        });

        test('evaluates condition and returns false when condition fails', function (): void {
            $condition = fn (array $context): bool => $context['is_admin'] === true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition(['is_admin' => false]);

            expect($result)->toBeFalse();
        });

        test('converts conditional rule to policy rule preserving all attributes', function (): void {
            $domain = new Domain('tenant-1');
            $priority = new Priority(100);
            $condition = fn (): bool => true;

            $conditionalRule = new ConditionalPolicyRule(
                subject: 'admin',
                resource: 'secrets',
                action: 'read',
                effect: Effect::Deny,
                priority: $priority,
                domain: $domain,
                condition: $condition,
            );

            $policyRule = $conditionalRule->toPolicyRule();

            expect($policyRule->subject)->toBe('admin');
            expect($policyRule->resource)->toBe('secrets');
            expect($policyRule->action)->toBe('read');
            expect($policyRule->effect)->toBe(Effect::Deny);
            expect($policyRule->priority)->toBe($priority);
            expect($policyRule->domain)->toBe($domain);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when condition receives context without required key', function (): void {
            $condition = fn (array $context): bool => ($context['required_key'] ?? null) === true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            // Missing key with null coalescing returns false
            $result = $rule->evaluateCondition([]);

            expect($result)->toBeFalse();
        });

        test('handles condition with explicit null coalescing safely', function (): void {
            $condition = fn (array $context): bool => ($context['is_admin'] ?? false) === true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition([]);

            expect($result)->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns true when condition is null (unconditional rule)', function (): void {
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: null,
            );

            $result = $rule->evaluateCondition(['any' => 'context']);

            expect($result)->toBeTrue();
        });

        test('evaluates with empty context array', function (): void {
            $condition = fn (array $context): bool => $context === [];
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition([]);

            expect($result)->toBeTrue();
        });

        test('handles null resource correctly', function (): void {
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: null,
                action: 'read',
                effect: Effect::Allow,
            );

            expect($rule->resource)->toBeNull();
        });

        test('handles null domain correctly', function (): void {
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
            );

            expect($rule->domain)->toBeNull();
        });

        test('evaluates condition with complex nested context data', function (): void {
            $condition = fn (array $context): bool => $context['user']['attributes']['is_admin'] ?? false;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition([
                'user' => [
                    'attributes' => [
                        'is_admin' => true,
                    ],
                ],
            ]);

            expect($result)->toBeTrue();
        });

        test('evaluates condition with unicode characters in context', function (): void {
            $condition = fn (array $context): bool => $context['name'] === '文档';
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $result = $rule->evaluateCondition(['name' => '文档']);

            expect($result)->toBeTrue();
        });

        test('handles condition that always returns true', function (): void {
            $condition = fn (array $context): bool => true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->evaluateCondition([]))->toBeTrue();
            expect($rule->evaluateCondition(['any' => 'data']))->toBeTrue();
        });

        test('handles condition that always returns false', function (): void {
            $condition = fn (array $context): bool => false;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->evaluateCondition([]))->toBeFalse();
            expect($rule->evaluateCondition(['any' => 'data']))->toBeFalse();
        });

        test('converts to policy rule strips condition completely', function (): void {
            $condition = fn (): bool => true;
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            $policyRule = $rule->toPolicyRule();

            // PolicyRule doesn't have a condition property
            expect($policyRule)->toBeInstanceOf(PolicyRule::class);
        });

        test('converts to policy rule with null domain preserves null', function (): void {
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
            );

            $policyRule = $rule->toPolicyRule();

            expect($policyRule->domain)->toBeNull();
        });

        test('converts to policy rule maintains priority object reference', function (): void {
            $priority = new Priority(50);
            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                priority: $priority,
            );

            $policyRule = $rule->toPolicyRule();

            expect($policyRule->priority)->toBe($priority);
        });

        test('handles condition closure with multiple context keys', function (): void {
            $condition = fn (array $context): bool => ($context['is_admin'] ?? false)
                && ($context['has_permission'] ?? false)
                && ($context['ip_allowed'] ?? false);

            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->evaluateCondition([
                'is_admin' => true,
                'has_permission' => true,
                'ip_allowed' => true,
            ]))->toBeTrue();

            expect($rule->evaluateCondition([
                'is_admin' => true,
                'has_permission' => false,
                'ip_allowed' => true,
            ]))->toBeFalse();
        });

        test('handles time-based condition example', function (): void {
            $condition = function (array $context): bool {
                $hour = (int) ($context['hour'] ?? Date::now()->format('H'));

                return $hour >= 9 && $hour <= 17; // Business hours
            };

            $rule = new ConditionalPolicyRule(
                subject: 'user:123',
                resource: 'documents',
                action: 'read',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->evaluateCondition(['hour' => 10]))->toBeTrue();
            expect($rule->evaluateCondition(['hour' => 18]))->toBeFalse();
        });

        test('handles ownership-based condition example', function (): void {
            $condition = fn (array $context): bool => ($context['resource_owner_id'] ?? null) === ($context['subject_id'] ?? null);

            $rule = new ConditionalPolicyRule(
                subject: 'user:*',
                resource: 'documents',
                action: 'edit',
                effect: Effect::Allow,
                condition: $condition,
            );

            expect($rule->evaluateCondition([
                'subject_id' => 123,
                'resource_owner_id' => 123,
            ]))->toBeTrue();

            expect($rule->evaluateCondition([
                'subject_id' => 123,
                'resource_owner_id' => 456,
            ]))->toBeFalse();
        });
    });
});
