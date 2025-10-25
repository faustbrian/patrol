<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PolicyEvaluator with RBAC', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new RbacRuleMatcher(),
            new EffectResolver(),
        );
    });

    describe('Happy Paths', function (): void {
        test('allows editor to edit documents', function (): void {
            $policy = new Policy([
                new PolicyRule('editor', 'document:*', 'edit', Effect::Allow),
            ]);

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('allows admin with domain-scoped permissions', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'document:*', 'delete', Effect::Allow),
            ]);

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => [
                    'tenant-1' => ['admin'],
                ],
            ]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('allows access to public resource roles', function (): void {
            $policy = new Policy([
                new PolicyRule('viewer', 'public', 'read', Effect::Allow),
            ]);

            $subject = subject('user-1', ['roles' => ['viewer']]);
            $resource = resource('doc-1', 'document', ['roles' => ['public']]);
            $action = patrol_action('read');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });
    });

    describe('Sad Paths', function (): void {
        test('denies when user lacks required role', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'document:*', 'delete', Effect::Allow),
            ]);

            $subject = subject('user-1', ['roles' => ['editor', 'viewer']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('prevents cross-tenant access', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'document:*', 'delete', Effect::Allow),
            ]);

            $subject = subject('user-1', [
                'domain' => 'tenant-1',
                'domain_roles' => [
                    'tenant-2' => ['admin'], // Admin in different tenant
                ],
            ]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles complex role hierarchy with deny-override', function (): void {
            $policy = new Policy([
                new PolicyRule('editor', 'document:*', 'edit', Effect::Allow, new Priority(1)),
                new PolicyRule('viewer', 'document:*', 'edit', Effect::Deny, new Priority(50)),
            ]);

            $subject = subject('user-1', ['roles' => ['editor', 'viewer']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Deny);
        });

        test('combines ACL and RBAC rules', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('editor', 'document:*', 'edit', Effect::Allow, new Priority(2)),
            ]);

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            expect($result)->toBe(Effect::Allow);
        });

        test('user-specific deny overrides role permission (revoke scenario)', function (): void {
            $policy = new Policy([
                // Role grants delete permission
                new PolicyRule('editor', 'document:*', 'delete', Effect::Allow, new Priority(1)),
                // But this specific user has delete revoked/forbidden
                new PolicyRule('user-1', 'document:*', 'delete', Effect::Deny, new Priority(100)),
            ]);

            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);

            // User-specific deny should override role permission
            expect($result)->toBe(Effect::Deny);
        });

        test('user gets role permission plus direct permission on top', function (): void {
            $policy = new Policy([
                // Role grants edit
                new PolicyRule('viewer', 'document:*', 'read', Effect::Allow, new Priority(1)),
                // Direct user permission for publish (not in viewer role)
                new PolicyRule('user-1', 'document-123', 'publish', Effect::Allow, new Priority(10)),
            ]);

            $subject = subject('user-1', ['roles' => ['viewer']]);

            // Can read from role
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);
            expect($result)->toBe(Effect::Allow);

            // Can also publish specific doc (direct permission)
            $resource = resource('document-123', 'document');
            $action = patrol_action('publish');
            $result = $this->evaluator->evaluate($policy, $subject, $resource, $action);
            expect($result)->toBe(Effect::Allow);
        });
    });
});
