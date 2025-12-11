<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Testing\Factories;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

/**
 * Factory for creating test policies with minimal boilerplate.
 *
 * Provides fluent static methods for constructing policies in test suites without
 * verbose rule building code. Automatically generates cartesian products when arrays
 * are provided, enabling concise multi-rule policy creation. Designed for readable,
 * maintainable test fixtures that clearly express authorization intent.
 *
 * Key features:
 * - Cartesian product generation from array inputs
 * - Support for both allow and deny policies
 * - Empty policy creation for default-deny testing
 * - Policy composition via merge()
 * - Consistent priority handling
 *
 * The factory excels at creating test fixtures for:
 * - Unit tests validating authorization logic
 * - Integration tests with complex permission scenarios
 * - Simulation and "what-if" scenario testing
 * - Policy comparison and diff testing
 * - Performance benchmarking with large rule sets
 *
 * ```php
 * // Single rule
 * $policy = PolicyFactory::allow('admin', 'document:*', 'delete');
 *
 * // Multiple rules via cartesian product (2 × 1 × 2 = 4 rules)
 * $policy = PolicyFactory::allow(
 *     ['admin', 'owner'],
 *     'document:*',
 *     ['read', 'write']
 * );
 *
 * // Combine policies
 * $policy = PolicyFactory::merge(
 *     PolicyFactory::allow('admin', '*', '*'),
 *     PolicyFactory::deny('guest', 'document:*', 'delete')
 * );
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see Policy For the policy structure containing rules
 */
final class PolicyFactory
{
    /**
     * Create a policy granting access for subject-resource-action combinations.
     *
     * Accepts strings or arrays for any parameter. When arrays are provided, generates
     * Allow rules for all combinations using cartesian product. For example, passing
     * ['admin', 'owner'] for subject, 'doc:*' for resource, and ['read', 'write'] for
     * action creates 4 rules: admin can read/write, and owner can read/write doc:*.
     *
     * The cartesian product approach enables concise multi-rule policy creation without
     * repetitive rule construction code, making tests more readable and maintainable.
     *
     * ```php
     * // Single rule
     * PolicyFactory::allow('admin', 'document:*', 'delete');
     *
     * // Multiple subjects, one resource, multiple actions (4 rules)
     * PolicyFactory::allow(['admin', 'editor'], 'post:*', ['edit', 'publish']);
     *
     * // All resources and actions for admin (1 wildcard rule)
     * PolicyFactory::allow('admin', '*', '*');
     * ```
     *
     * @param  array<string>|string $subject  Subject identifier(s) to grant access. Can be user IDs ("user:123"),
     *                                        role names ("admin", "editor"), or wildcards ("*"). When an array is
     *                                        provided, creates rules for all subjects in combination with resources
     *                                        and actions.
     * @param  array<string>|string $resource Resource identifier(s) to grant access to. Can be specific resources
     *                                        ("document:456"), patterns ("document:*"), or wildcards ("*"). When an
     *                                        array is provided, creates rules for all resources in combination with
     *                                        subjects and actions.
     * @param  array<string>|string $action   Action(s) to allow. Can be operation names ("read", "write", "delete"),
     *                                        HTTP methods ("GET", "POST"), or wildcards ("*"). When an array is
     *                                        provided, creates rules for all actions in combination with subjects
     *                                        and resources.
     * @param  int                  $priority Rule priority for conflict resolution (default: 1). Higher priorities
     *                                        are evaluated first. Use higher values for exception rules that should
     *                                        override general policies.
     * @return Policy               Immutable policy containing Allow rules for all subject-resource-action combinations.
     *                              The policy can be used directly in tests or merged with other policies via merge().
     */
    public static function allow(
        string|array $subject,
        string|array $resource,
        string|array $action,
        int $priority = 1,
    ): Policy {
        $subjects = (array) $subject;
        $resources = (array) $resource;
        $actions = (array) $action;

        $rules = [];

        foreach ($subjects as $s) {
            foreach ($resources as $r) {
                foreach ($actions as $a) {
                    $rules[] = new PolicyRule(
                        subject: $s,
                        resource: $r,
                        action: $a,
                        effect: Effect::Allow,
                        priority: new Priority($priority),
                    );
                }
            }
        }

        return new Policy($rules);
    }

    /**
     * Create a policy denying access for subject-resource-action combinations.
     *
     * Accepts strings or arrays for any parameter. When arrays are provided, generates
     * Deny rules for all combinations using cartesian product. Useful for testing explicit
     * deny scenarios, permission restrictions, and deny-override conflict resolution.
     *
     * Deny rules are typically used to:
     * - Block specific subjects from sensitive resources
     * - Override allow rules with higher priority denies
     * - Implement security boundaries and access restrictions
     * - Test deny-override conflict resolution logic
     *
     * ```php
     * // Block guest from deleting any document
     * PolicyFactory::deny('guest', 'document:*', 'delete');
     *
     * // Block multiple users from sensitive resources (4 rules)
     * PolicyFactory::deny(['user:123', 'user:456'], 'secret:*', ['read', 'write']);
     *
     * // High-priority deny to override allow rules
     * PolicyFactory::deny('contractor', 'payroll:*', '*', 100);
     * ```
     *
     * @param  array<string>|string $subject  Subject identifier(s) to deny access. Can be user IDs, role names,
     *                                        or wildcards. When an array is provided, creates deny rules for all
     *                                        subjects in combination with resources and actions.
     * @param  array<string>|string $resource Resource identifier(s) to deny access to. Can be specific resources,
     *                                        patterns, or wildcards. When an array is provided, creates deny rules
     *                                        for all resources in combination with subjects and actions.
     * @param  array<string>|string $action   Action(s) to deny. Can be operation names, HTTP methods, or wildcards.
     *                                        When an array is provided, creates deny rules for all actions in
     *                                        combination with subjects and resources.
     * @param  int                  $priority Rule priority for conflict resolution (default: 1). Deny rules often use
     *                                        higher priorities to override allow rules, implementing deny-override
     *                                        semantics for security-critical access control.
     * @return Policy               Immutable policy containing Deny rules for all subject-resource-action combinations.
     *                              These rules explicitly block access and typically take precedence in evaluation.
     */
    public static function deny(
        string|array $subject,
        string|array $resource,
        string|array $action,
        int $priority = 1,
    ): Policy {
        $subjects = (array) $subject;
        $resources = (array) $resource;
        $actions = (array) $action;

        $rules = [];

        foreach ($subjects as $s) {
            foreach ($resources as $r) {
                foreach ($actions as $a) {
                    $rules[] = new PolicyRule(
                        subject: $s,
                        resource: $r,
                        action: $a,
                        effect: Effect::Deny,
                        priority: new Priority($priority),
                    );
                }
            }
        }

        return new Policy($rules);
    }

    /**
     * Create an empty policy with no rules for default-deny testing.
     *
     * Returns a policy with an empty rule set, useful for testing default-deny behavior
     * where authorization should fail in the absence of explicit allow rules. Also serves
     * as a starting point for incremental policy building in tests where rules are added
     * programmatically.
     *
     * Common use cases:
     * - Testing default-deny authorization semantics
     * - Verifying fail-safe behavior without matching rules
     * - Baseline for incremental policy construction
     * - Neutral element in policy merging operations
     *
     * ```php
     * $policy = PolicyFactory::empty();
     * // Authorization checks against this policy always return Deny
     *
     * // Use as starting point for programmatic policy building
     * $policy = PolicyFactory::empty();
     * foreach ($roles as $role) {
     *     $policy = PolicyFactory::merge($policy, PolicyFactory::allow($role, '*', 'read'));
     * }
     * ```
     *
     * @return Policy Immutable policy with no rules. All authorization checks against this policy
     *                will evaluate to Deny effect, demonstrating default-deny security behavior.
     */
    public static function empty(): Policy
    {
        return new Policy([]);
    }

    /**
     * Merge multiple policies into a single combined policy.
     *
     * Combines all rules from the provided policies into one policy, preserving each
     * rule's original priority and effect. This enables composition of complex test
     * scenarios from simpler policy building blocks, following the composite pattern.
     *
     * The merge operation:
     * - Preserves all rules from all input policies
     * - Maintains original priority values (no re-prioritization)
     * - Concatenates rule arrays in the order policies are provided
     * - Creates a new policy without modifying inputs (immutable)
     *
     * Use this for:
     * - Combining allow and deny policies for conflict resolution testing
     * - Building layered permission structures (base + role-specific + user-specific)
     * - Composing test fixtures from reusable policy fragments
     * - Creating complex authorization scenarios from simple building blocks
     *
     * ```php
     * // Combine base permissions with role-specific overrides
     * $policy = PolicyFactory::merge(
     *     PolicyFactory::allow('*', 'public:*', 'read'),          // Everyone can read public
     *     PolicyFactory::allow('admin', '*', '*'),                 // Admin can do everything
     *     PolicyFactory::deny('suspended', '*', '*', 100)          // High-priority deny
     * );
     *
     * // Build incrementally
     * $policies = [
     *     PolicyFactory::allow('editor', 'document:*', ['read', 'write']),
     *     PolicyFactory::allow('viewer', 'document:*', 'read'),
     * ];
     * $combined = PolicyFactory::merge(...$policies);
     * ```
     *
     * @param  Policy ...$policies Variable number of policies to merge. Can be empty (returns empty policy),
     *                             single policy (returns copy), or multiple policies (concatenates all rules).
     *                             The order affects rule evaluation when priorities are equal.
     * @return Policy Immutable policy containing all rules from all input policies. The resulting policy
     *                can be used directly in tests or merged further with additional policies.
     */
    public static function merge(Policy ...$policies): Policy
    {
        $allRules = [];

        foreach ($policies as $policy) {
            $allRules = [...$allRules, ...$policy->rules];
        }

        return new Policy($allRules);
    }
}
