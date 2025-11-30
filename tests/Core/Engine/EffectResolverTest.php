<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('EffectResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new EffectResolver();
    });

    describe('Happy Paths', function (): void {
        test('returns allow when only allow rules exist', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ];

            expect($this->resolver->resolve($rules))->toBe(Effect::Allow);
        });

        test('returns deny when only deny rules exist', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
            ];

            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });

        test('applies deny-override when both allow and deny exist', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(2)),
            ];

            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });

        test('respects priority ordering with deny-override', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(100)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(50)),
            ];

            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });

        test('returns allow when higher priority allow beats lower priority deny', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(100)),
            ];

            // Despite allow having higher priority, deny-override applies
            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });
    });

    describe('Sad Paths', function (): void {
        test('returns deny when no rules provided', function (): void {
            expect($this->resolver->resolve([]))->toBe(Effect::Deny);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles multiple rules with same priority', function (): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(50)),
                new PolicyRule('user-1', 'doc-2', 'read', Effect::Deny, new Priority(50)),
            ];

            // Deny-override still applies even with same priority
            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });

        test('handles many rules efficiently', function (): void {
            $rules = [];

            for ($i = 0; $i < 100; ++$i) {
                $rules[] = new PolicyRule('user-1', 'doc-'.$i, 'read', Effect::Allow, new Priority($i));
            }

            // Add one deny at the end
            $rules[] = new PolicyRule('user-1', 'doc-sensitive', 'read', Effect::Deny, new Priority(1));

            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        });

        test('deny always overrides allow regardless of priority', function (int $allowPriority, int $denyPriority): void {
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority($allowPriority)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority($denyPriority)),
            ];

            expect($this->resolver->resolve($rules))->toBe(Effect::Deny);
        })->with([
            'allow higher' => [100, 1],
            'deny higher' => [1, 100],
            'equal priority' => [50, 50],
        ]);
    });
});
