<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

describe('AclRuleMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new AclRuleMatcher();
    });

    describe('Happy Paths', function (): void {
        test('matches rule when subject, resource, and action all match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches wildcard resource for any resource of that type', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document:*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-123', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('supports superuser that matches all resources and actions', function (): void {
            $rule = new PolicyRule(
                subject: '*',
                resource: '*',
                action: '*',
                effect: Effect::Allow,
            );

            $subject = subject('admin-1', ['superuser' => true]);
            $resource = resource('any-resource', 'any-type');
            $action = patrol_action('any-action');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches null resource for type-based permissions', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: null,
                action: 'write-article',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('any-resource', 'any-type');
            $action = patrol_action('write-article');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches wildcard action for any action', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: '*',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects access when subject does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-2');
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects access when action does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('write');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects access when resource does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-2', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects superuser wildcard when user is not superuser', function (): void {
            $rule = new PolicyRule(
                subject: '*',
                resource: '*',
                action: '*',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['superuser' => false]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when type wildcard does not match resource type', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'article:*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty subject attributes gracefully', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', []);
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles unicode characters in resource identifiers', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-文档',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-文档', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles case sensitivity in action names', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'Read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles missing superuser attribute as false', function (): void {
            $rule = new PolicyRule(
                subject: '*',
                resource: '*',
                action: '*',
                effect: Effect::Allow,
            );

            $subject = subject('user-1'); // No superuser attribute
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('matches various action types', function (string $actionName): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: $actionName,
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action($actionName);

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        })->with([
            'read' => ['read'],
            'write' => ['write'],
            'delete' => ['delete'],
            'update' => ['update'],
        ]);
    });
});
