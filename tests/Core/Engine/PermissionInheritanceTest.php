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

describe('Permission Inheritance & Granular Assignment', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new RbacRuleMatcher(),
            new EffectResolver(),
        );
    });

    test('multiple roles with mixed permissions - deny wins', function (): void {
        $policy = new Policy([
            new PolicyRule('editor', 'document:*', 'delete', Effect::Allow, new Priority(1)),
            new PolicyRule('auditor', 'document:*', 'delete', Effect::Deny, new Priority(10)),
            new PolicyRule('viewer', 'document:*', 'read', Effect::Allow, new Priority(1)),
        ]);

        $subject = subject('user-1', ['roles' => ['editor', 'auditor', 'viewer']]);
        $resource = resource('doc-1', 'document');

        // Should deny delete (auditor role denies)
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('delete'));
        expect($result)->toBe(Effect::Deny);

        // Should allow read (viewer role allows)
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('read'));
        expect($result)->toBe(Effect::Allow);
    });

    test('permission layering: role allow, user allow, specific resource deny', function (): void {
        $policy = new Policy([
            // Role: can edit all documents
            new PolicyRule('editor', 'document:*', 'edit', Effect::Allow, new Priority(1)),
            // User: can also publish all documents (on top of role)
            new PolicyRule('user-1', 'document:*', 'publish', Effect::Allow, new Priority(10)),
            // But: cannot edit THIS specific sensitive document
            new PolicyRule('user-1', 'document-sensitive', 'edit', Effect::Deny, new Priority(100)),
        ]);

        $subject = subject('user-1', ['roles' => ['editor']]);

        // Can edit normal docs (from role)
        $result = $this->evaluator->evaluate($policy, $subject, resource('doc-1', 'document'), patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Can publish (direct permission)
        $result = $this->evaluator->evaluate($policy, $subject, resource('doc-1', 'document'), patrol_action('publish'));
        expect($result)->toBe(Effect::Allow);

        // Cannot edit sensitive doc (specific deny)
        $result = $this->evaluator->evaluate($policy, $subject, resource('document-sensitive', 'document'), patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);
    });

    test('wildcard action with specific action denied', function (): void {
        $policy = new Policy([
            // Can do anything on documents
            new PolicyRule('user-1', 'document:*', '*', Effect::Allow, new Priority(1)),
            // Except delete
            new PolicyRule('user-1', 'document:*', 'delete', Effect::Deny, new Priority(100)),
        ]);

        $subject = subject('user-1');
        $resource = resource('doc-1', 'document');

        // Can read, edit, publish, etc.
        expect($this->evaluator->evaluate($policy, $subject, $resource, patrol_action('read')))->toBe(Effect::Allow);
        expect($this->evaluator->evaluate($policy, $subject, $resource, patrol_action('edit')))->toBe(Effect::Allow);
        expect($this->evaluator->evaluate($policy, $subject, $resource, patrol_action('publish')))->toBe(Effect::Allow);

        // But not delete
        expect($this->evaluator->evaluate($policy, $subject, $resource, patrol_action('delete')))->toBe(Effect::Deny);
    });

    test('resource type-specific permissions', function (): void {
        $policy = new Policy([
            new PolicyRule('user-1', 'document:*', 'edit', Effect::Allow),
            new PolicyRule('user-1', 'comment:*', 'delete', Effect::Allow),
            new PolicyRule('user-1', 'document:*', 'delete', Effect::Deny),
        ]);

        $subject = subject('user-1');

        // Can edit documents
        $result = $this->evaluator->evaluate($policy, $subject, resource('doc-1', 'document'), patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Cannot delete documents (explicit deny)
        $result = $this->evaluator->evaluate($policy, $subject, resource('doc-1', 'document'), patrol_action('delete'));
        expect($result)->toBe(Effect::Deny);

        // Can delete comments
        $result = $this->evaluator->evaluate($policy, $subject, resource('comment-1', 'comment'), patrol_action('delete'));
        expect($result)->toBe(Effect::Allow);

        // Cannot edit comments (no permission)
        $result = $this->evaluator->evaluate($policy, $subject, resource('comment-1', 'comment'), patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);
    });

    test('user with no roles has only direct permissions', function (): void {
        $policy = new Policy([
            // Role-based permissions
            new PolicyRule('editor', 'document:*', 'edit', Effect::Allow),
            // Direct user permission
            new PolicyRule('user-1', 'document-123', 'read', Effect::Allow),
        ]);

        $subject = subject('user-1'); // No roles

        // Cannot edit (no editor role)
        $result = $this->evaluator->evaluate($policy, $subject, resource('doc-1', 'document'), patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);

        // Can read specific doc (direct permission)
        $result = $this->evaluator->evaluate($policy, $subject, resource('document-123', 'document'), patrol_action('read'));
        expect($result)->toBe(Effect::Allow);
    });

    test('same priority with allow and deny - deny wins', function (): void {
        $policy = new Policy([
            new PolicyRule('user-1', 'document:*', 'edit', Effect::Allow, new Priority(50)),
            new PolicyRule('user-1', 'document:*', 'edit', Effect::Deny, new Priority(50)),
        ]);

        $subject = subject('user-1');
        $resource = resource('doc-1', 'document');

        // Deny should win (deny-override)
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);
    });
});
