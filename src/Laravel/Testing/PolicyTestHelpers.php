<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Testing;

use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

use function in_array;

/**
 * Test helper methods for building policy objects and rules in tests.
 *
 * Provides convenient factory methods for creating Policy and PolicyRule objects
 * with sensible defaults, reducing boilerplate in test files. Supports creating
 * allow/deny rules, wildcard rules, and superuser rules with minimal configuration.
 *
 * ```php
 * use Patrol\Laravel\Testing\PolicyTestHelpers;
 *
 * class PolicyTest extends TestCase
 * {
 *     use PolicyTestHelpers;
 *
 *     public function test_basic_access_control(): void
 *     {
 *         $policy = $this->createPolicy([
 *             $this->createAllowRule('user:123', 'post:456', 'read'),
 *             $this->createDenyRule('user:123', 'post:456', 'delete', priority: 2),
 *         ]);
 *     }
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-ignore trait.unused
 */
trait PolicyTestHelpers
{
    /**
     * Create a Policy object from an array of policy rules.
     *
     * Factory method for building Policy objects in tests with minimal boilerplate.
     * Accepts an array of PolicyRule objects and returns an immutable Policy instance.
     *
     * @param  array<int, PolicyRule> $rules Array of PolicyRule objects to include in the policy.
     *                                       Can be empty to create an empty policy (denies all by default).
     * @return Policy                 Immutable Policy object containing the specified rules for evaluation
     */
    protected function createPolicy(array $rules): Policy
    {
        return new Policy($rules);
    }

    /**
     * Create a policy rule with Allow effect.
     *
     * Factory method for creating allow rules with sensible defaults. Useful for
     * quickly building test policies that grant specific permissions to subjects.
     *
     * @param  string      $subject  The subject identifier (e.g., "user:123", "role:admin")
     * @param  null|string $resource The resource identifier (e.g., "post:456"). NULL matches any resource.
     * @param  string      $action   The action identifier (e.g., "read", "write", "delete")
     * @param  int         $priority Rule priority for conflict resolution. Higher values take precedence.
     *                               Defaults to 1 for standard priority.
     * @param  null|string $domain   Optional domain context for multi-tenant policies (e.g., "tenant:123").
     *                               NULL creates domain-agnostic rules.
     * @return PolicyRule  Immutable PolicyRule object with Allow effect
     */
    protected function createAllowRule(
        string $subject,
        ?string $resource,
        string $action,
        int $priority = 1,
        ?string $domain = null,
    ): PolicyRule {
        return new PolicyRule(
            subject: $subject,
            resource: $resource,
            action: $action,
            effect: Effect::Allow,
            priority: new Priority($priority),
            domain: in_array($domain, [null, '', '0'], true) ? null : new Domain($domain),
        );
    }

    /**
     * Create a policy rule with Deny effect.
     *
     * Factory method for creating deny rules with sensible defaults. Useful for
     * building test policies that explicitly restrict access. Deny rules typically
     * have higher priority than allow rules to enforce security boundaries.
     *
     * @param  string      $subject  The subject identifier (e.g., "user:123", "role:guest")
     * @param  null|string $resource The resource identifier (e.g., "post:456"). NULL matches any resource.
     * @param  string      $action   The action identifier (e.g., "read", "write", "delete")
     * @param  int         $priority Rule priority for conflict resolution. Higher values take precedence.
     *                               Defaults to 1 for standard priority.
     * @param  null|string $domain   Optional domain context for multi-tenant policies (e.g., "tenant:123").
     *                               NULL creates domain-agnostic rules.
     * @return PolicyRule  Immutable PolicyRule object with Deny effect
     */
    protected function createDenyRule(
        string $subject,
        ?string $resource,
        string $action,
        int $priority = 1,
        ?string $domain = null,
    ): PolicyRule {
        return new PolicyRule(
            subject: $subject,
            resource: $resource,
            action: $action,
            effect: Effect::Deny,
            priority: new Priority($priority),
            domain: in_array($domain, [null, '', '0'], true) ? null : new Domain($domain),
        );
    }

    /**
     * Create a wildcard policy rule that applies to all subjects and resources.
     *
     * Factory method for creating rules that apply globally, regardless of subject
     * or resource. Useful for testing default policies or establishing baseline
     * permissions across the entire system.
     *
     * @param  string     $action   The action identifier this rule applies to (e.g., "read", "list")
     * @param  Effect     $effect   The effect of the rule (Allow or Deny). Defaults to Allow for
     *                              permissive baseline policies.
     * @param  int        $priority Rule priority for conflict resolution. Higher values take precedence.
     *                              Defaults to 1 for standard priority.
     * @return PolicyRule Immutable PolicyRule with wildcard subject '*' and resource '*'
     */
    protected function createWildcardRule(
        string $action,
        Effect $effect = Effect::Allow,
        int $priority = 1,
    ): PolicyRule {
        return new PolicyRule(
            subject: '*',
            resource: '*',
            action: $action,
            effect: $effect,
            priority: new Priority($priority),
        );
    }

    /**
     * Create a superuser policy rule granting all permissions to a subject.
     *
     * Factory method for creating rules that grant unrestricted access to all resources
     * and actions for a specific subject. Commonly used for testing administrator or
     * superuser scenarios. Uses high priority by default to override other rules.
     *
     * @param  string     $subject  The subject identifier to grant superuser access (e.g., "user:admin",
     *                              "role:superadmin"). This subject will be allowed all actions on all resources.
     * @param  int        $priority Rule priority for conflict resolution. Defaults to 100 (high priority)
     *                              to ensure superuser rules take precedence over standard permissions.
     * @return PolicyRule Immutable PolicyRule with wildcard resource and action, allowing everything
     */
    protected function createSuperuserRule(string $subject, int $priority = 100): PolicyRule
    {
        return new PolicyRule(
            subject: $subject,
            resource: '*',
            action: '*',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );
    }
}
