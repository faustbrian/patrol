<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RestfulRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;

describe('RESTful Permission Scenarios', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new RestfulRuleMatcher(
                new AclRuleMatcher(),
            ),
            new EffectResolver(),
        );
    });

    test('user can GET but not POST to same endpoint', function (): void {
        $policy = new Policy([
            new PolicyRule('user-1', '/api/documents', 'GET', Effect::Allow),
            new PolicyRule('user-1', '/api/documents', 'POST', Effect::Deny),
        ]);

        $subject = subject('user-1');
        $resource = resource('/api/documents', 'api');

        // Can GET
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('GET'));
        expect($result)->toBe(Effect::Allow);

        // Cannot POST
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('POST'));
        expect($result)->toBe(Effect::Deny);
    });

    test('path parameter matches specific resource ID', function (): void {
        $policy = new Policy([
            new PolicyRule('user-1', '/api/documents/:id', 'GET', Effect::Allow),
        ]);

        $subject = subject('user-1');

        // Should match any ID
        $result = $this->evaluator->evaluate($policy, $subject, resource('/api/documents/123', 'api'), patrol_action('GET'));
        expect($result)->toBe(Effect::Allow);

        $result = $this->evaluator->evaluate($policy, $subject, resource('/api/documents/456', 'api'), patrol_action('GET'));
        expect($result)->toBe(Effect::Allow);

        // Should not match different path
        $result = $this->evaluator->evaluate($policy, $subject, resource('/api/users/123', 'api'), patrol_action('GET'));
        expect($result)->toBe(Effect::Deny);
    });

    test('wildcard path with method restrictions', function (): void {
        $policy = new Policy([
            new PolicyRule('user-1', '/api/public/*', 'GET', Effect::Allow),
            new PolicyRule('user-1', '/api/admin/*', '*', Effect::Deny),
        ]);

        $subject = subject('user-1');

        // Can GET public resources
        $result = $this->evaluator->evaluate($policy, $subject, resource('/api/public/data', 'api'), patrol_action('GET'));
        expect($result)->toBe(Effect::Allow);

        // Cannot access admin routes
        $result = $this->evaluator->evaluate($policy, $subject, resource('/api/admin/users', 'api'), patrol_action('GET'));
        expect($result)->toBe(Effect::Deny);
    });
});
