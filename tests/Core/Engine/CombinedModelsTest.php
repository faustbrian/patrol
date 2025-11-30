<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AbacRuleMatcher;
use Patrol\Core\Engine\AttributeResolver;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('Combined Authorization Models', function (): void {
    test('ACL, RBAC, and ABAC rules work together seamlessly', function (): void {
        $policy = new Policy([
            // RBAC: Editors can edit documents
            new PolicyRule('editor', 'document:*', 'edit', Effect::Allow, new Priority(1)),
            // ABAC: Can only edit if not archived
            new PolicyRule('resource.status != archived', 'document:*', 'edit', Effect::Allow, new Priority(10)),
            // ACL: Specific admin user can do anything (highest priority)
            new PolicyRule('admin-1', '*', '*', Effect::Allow, new Priority(100)),
        ]);

        $evaluator = new PolicyEvaluator(
            new AbacRuleMatcher(
                new AttributeResolver(),
            ),
            new EffectResolver(),
        );

        // Scenario 1: Regular user with editor role can edit active documents
        $editor = subject('user-1', ['roles' => ['editor']]);
        $activeDoc = resource('doc-1', 'document', ['status' => 'active']);

        $result = $evaluator->evaluate($policy, $editor, $activeDoc, patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Scenario 2: Editor cannot edit archived documents (ABAC denies)
        $archivedDoc = resource('doc-2', 'document', ['status' => 'archived']);

        $result = $evaluator->evaluate($policy, $editor, $archivedDoc, patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);

        // Scenario 3: Admin can edit even archived documents (ACL with highest priority)
        $admin = subject('admin-1');

        $result = $evaluator->evaluate($policy, $admin, $archivedDoc, patrol_action('edit'));
        expect($result)->toBe(Effect::Allow);

        // Scenario 4: Admin can perform any action on any resource
        $result = $evaluator->evaluate($policy, $admin, $activeDoc, patrol_action('delete'));
        expect($result)->toBe(Effect::Allow);
    });

    test('combining ABAC and ACL with mixed allow and deny effects', function (): void {
        $policy = new Policy([
            // ABAC: Owner can edit their documents
            new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow, new Priority(1)),
            // ABAC: Anyone can read non-archived documents
            new PolicyRule('resource.status != archived', 'document:*', 'read', Effect::Allow, new Priority(10)),
            // ACL: Specific user is blocked from all actions on sensitive documents
            new PolicyRule('user-banned', 'document-sensitive', '*', Effect::Deny, new Priority(100)),
        ]);

        $evaluator = new PolicyEvaluator(
            new AbacRuleMatcher(
                new AttributeResolver(),
            ),
            new EffectResolver(),
        );

        // Anyone can read active documents (ABAC rule)
        $user = subject('user-1');
        $activeDoc = resource('doc-1', 'document', ['owner' => 'user-2', 'status' => 'active']);

        expect($evaluator->evaluate($policy, $user, $activeDoc, patrol_action('read')))
            ->toBe(Effect::Allow);

        // Owner can edit their own document (ABAC rule)
        $owner = subject('user-2');
        $ownDoc = resource('doc-2', 'document', ['owner' => 'user-2', 'status' => 'active']);

        expect($evaluator->evaluate($policy, $owner, $ownDoc, patrol_action('edit')))
            ->toBe(Effect::Allow);

        // Banned user cannot access sensitive documents even if they own it (ACL deny overrides)
        $bannedUser = subject('user-banned');
        $sensitiveDoc = resource('document-sensitive', 'document', ['owner' => 'user-banned', 'status' => 'active']);

        expect($evaluator->evaluate($policy, $bannedUser, $sensitiveDoc, patrol_action('read')))
            ->toBe(Effect::Deny);

        expect($evaluator->evaluate($policy, $bannedUser, $sensitiveDoc, patrol_action('edit')))
            ->toBe(Effect::Deny);
    });
});
