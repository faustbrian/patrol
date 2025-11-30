<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

describe('RbacRuleMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new RbacRuleMatcher();
    });

    describe('Happy Paths', function (): void {
        test('matches when subject has required role', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor', 'viewer']]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches with resource roles', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'public',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document', ['roles' => ['public', 'archived']]);
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches with domain-scoped roles', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => [
                    'tenant-1' => ['admin', 'editor'],
                    'tenant-2' => ['viewer'],
                ],
            ]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches with wildcard resource', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: '*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['admin']]);
            $resource = resource('any-resource', 'any-type');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches with type wildcard', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'document:*',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-123', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('falls back to direct subject ID match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['viewer']]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects when subject lacks required role', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor', 'viewer']]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when subject has no roles', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1'); // No roles
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when resource lacks required role', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'private',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document', ['roles' => ['public']]);
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when action does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('prevents cross-domain role access', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => [
                    'tenant-2' => ['admin'], // Admin in different domain
                ],
            ]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty resource roles', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'public',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document', ['roles' => []]);
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles null resource for type-based permissions', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: null,
                action: 'create-article',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('any-id', 'any-type');
            $action = patrol_action('create-article');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles multiple overlapping roles', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['viewer', 'editor', 'admin']]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles case-sensitive role names', function (): void {
            $rule = new PolicyRule(
                subject: 'Editor',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]); // Lowercase
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles non-array subject roles attribute', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => 'editor']); // String instead of array
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles non-array domain_roles attribute', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => 'admin', // String instead of array
            ]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles non-array domain roles value', function (): void {
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => [
                    'tenant-1' => 'admin', // String instead of array
                ],
            ]);
            $resource = resource('document-1', 'document');
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles non-array resource roles attribute', function (): void {
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'public',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document', ['roles' => 'public']); // String instead of array
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });
});
