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

describe('Guest/Anonymous User Access', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new RbacRuleMatcher(),
            new EffectResolver(),
        );
    });

    test('anonymous user has limited access to public resources', function (): void {
        $policy = new Policy([
            // Public role can read public resources
            new PolicyRule('public', 'article:*', 'read', Effect::Allow),
            // But cannot edit
            new PolicyRule('public', 'article:*', 'edit', Effect::Deny),
        ]);

        // Guest user with public role
        $guest = subject('guest', ['roles' => ['public']]);
        $publicArticle = resource('article-1', 'article');

        // Can read public resources
        $result = $this->evaluator->evaluate($policy, $guest, $publicArticle, patrol_action('read'));
        expect($result)->toBe(Effect::Allow);

        // Cannot edit public resources
        $result = $this->evaluator->evaluate($policy, $guest, $publicArticle, patrol_action('edit'));
        expect($result)->toBe(Effect::Deny);

        // Cannot delete (no permission)
        $result = $this->evaluator->evaluate($policy, $guest, $publicArticle, patrol_action('delete'));
        expect($result)->toBe(Effect::Deny);
    });

    test('guest user cannot access protected resources', function (): void {
        $policy = new Policy([
            // Public resources are accessible
            new PolicyRule('public', 'article:*', 'read', Effect::Allow),
            // Members can access documents
            new PolicyRule('member', 'document:*', 'read', Effect::Allow),
        ]);

        // Guest with only public role
        $guest = subject('guest', ['roles' => ['public']]);

        // Can access public articles
        $publicArticle = resource('article-1', 'article');
        expect($this->evaluator->evaluate($policy, $guest, $publicArticle, patrol_action('read')))
            ->toBe(Effect::Allow);

        // Cannot access member documents (lacks member role)
        $memberDoc = resource('doc-1', 'document');
        expect($this->evaluator->evaluate($policy, $guest, $memberDoc, patrol_action('read')))
            ->toBe(Effect::Deny);
    });

    test('unauthenticated user has no access without public permission', function (): void {
        $policy = new Policy([
            // Only authenticated users can access
            new PolicyRule('member', 'document:*', 'read', Effect::Allow),
        ]);

        // Completely unauthenticated user (no roles)
        $unauthenticated = subject('anonymous');

        $document = resource('doc-1', 'document');

        // No access without member role
        expect($this->evaluator->evaluate($policy, $unauthenticated, $document, patrol_action('read')))
            ->toBe(Effect::Deny);
    });

    test('resource-specific public access via resource roles', function (): void {
        $policy = new Policy([
            // Public role can read any document
            new PolicyRule('public', 'document:*', 'read', Effect::Allow),
        ]);

        // Guest with public role
        $guestWithPublicRole = subject('guest', ['roles' => ['public']]);

        // Guest without public role
        $guestWithoutRole = subject('guest-anonymous');

        $document = resource('doc-1', 'document');

        // Guest WITH public role can read
        expect($this->evaluator->evaluate($policy, $guestWithPublicRole, $document, patrol_action('read')))
            ->toBe(Effect::Allow);

        // Guest WITHOUT public role cannot read
        expect($this->evaluator->evaluate($policy, $guestWithoutRole, $document, patrol_action('read')))
            ->toBe(Effect::Deny);
    });
});
