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

describe('Multi-Tenant Permission Scenarios', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new PolicyEvaluator(
            new RbacRuleMatcher(),
            new EffectResolver(),
        );
    });

    test('user has different permissions in different tenants', function (): void {
        $policy = new Policy([
            new PolicyRule('admin', 'document:*', 'delete', Effect::Allow),
            new PolicyRule('viewer', 'document:*', 'read', Effect::Allow),
        ]);

        // User is admin in tenant-1
        $adminInTenant1 = subject('user-1', [
            'domain' => 'tenant-1',
            'domain_roles' => ['tenant-1' => ['admin']],
        ]);

        // User is viewer in tenant-2
        $viewerInTenant2 = subject('user-1', [
            'domain' => 'tenant-2',
            'domain_roles' => ['tenant-2' => ['viewer']],
        ]);

        $resource = resource('doc-1', 'document');

        // Can delete in tenant-1 (admin)
        $result = $this->evaluator->evaluate($policy, $adminInTenant1, $resource, patrol_action('delete'));
        expect($result)->toBe(Effect::Allow);

        // Cannot delete in tenant-2 (only viewer)
        $result = $this->evaluator->evaluate($policy, $viewerInTenant2, $resource, patrol_action('delete'));
        expect($result)->toBe(Effect::Deny);

        // Can read in tenant-2 (viewer)
        $result = $this->evaluator->evaluate($policy, $viewerInTenant2, $resource, patrol_action('read'));
        expect($result)->toBe(Effect::Allow);
    });

    test('user cannot access resources in different tenant even with same role', function (): void {
        $policy = new Policy([
            new PolicyRule('admin', 'document:*', '*', Effect::Allow),
        ]);

        // User is admin in tenant-1
        $subject = subject('user-1', [
            'domain' => 'tenant-1',
            'domain_roles' => [
                'tenant-1' => ['admin'],
                'tenant-2' => [], // No roles in tenant-2
            ],
        ]);

        $resource = resource('doc-1', 'document');

        // Admin in tenant-1 can access
        $result = $this->evaluator->evaluate($policy, $subject, $resource, patrol_action('read'));
        expect($result)->toBe(Effect::Allow);
    });
});
