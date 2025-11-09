<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;

use function str_starts_with;

/**
 * Implements hierarchical resource-based policy inheritance for nested resource structures.
 *
 * Enables permission inheritance from parent resources to child resources using path-based
 * hierarchies, similar to filesystem permissions. Rules defined on parent resources are
 * automatically propagated to child resources, reducing policy duplication and enabling
 * organizational hierarchies like folder structures, departmental trees, or resource groups.
 *
 * Inheritance patterns:
 * - Parent rule "folder:123" inherits to "folder:123/document:456"
 * - Original rules preserved alongside inherited rules
 * - Wildcard and null resources excluded from inheritance
 * - Enables hierarchical permission management for complex resource trees
 *
 * @see Policy For the policy structure containing rules
 * @see PolicyRule For individual authorization rules
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyInheritance
{
    /**
     * Expand a policy by adding inherited rules from parent resources.
     *
     * Processes each policy rule to identify parent-child resource relationships using
     * path-based hierarchies. When a rule's resource is a parent of the target resource,
     * creates an inherited rule targeting the child resource while preserving all other
     * rule properties (subject, action, effect, priority). The expanded policy contains
     * both original and inherited rules for complete authorization coverage.
     *
     * ```php
     * $inheritance = new PolicyInheritance();
     * $resource = new Resource('folder:123/document:456', 'document');
     * $expandedPolicy = $inheritance->expandInheritedRules($policy, $resource);
     * // Now includes rules from parent "folder:123" applied to child resource
     * ```
     *
     * @param  Policy   $policy   The base policy containing rules to expand with inheritance
     * @param  resource $resource The target resource whose parent rules should be inherited
     * @return Policy   A new policy containing both original and inherited rules
     */
    public function expandInheritedRules(Policy $policy, Resource $resource): Policy
    {
        $expandedRules = [];

        foreach ($policy->rules as $rule) {
            // Preserve the original rule in the expanded policy
            $expandedRules[] = $rule;

            // Create inherited rule if this rule's resource is a parent path
            if ($this->hasParentPath($rule->resource, $resource->id)) {
                $expandedRules[] = $this->inheritRule($rule, $resource->id);
            }
        }

        return new Policy($expandedRules);
    }

    /**
     * Determine if a rule resource is a parent path of the target resource.
     *
     * Implements path-based hierarchy detection using slash-delimited resource identifiers.
     * A resource is considered a parent if the child resource ID starts with the parent
     * resource ID followed by a slash separator, similar to filesystem path hierarchies.
     * Wildcard and null resources are excluded from inheritance to avoid over-propagation.
     *
     * Examples:
     * - "folder:123" is parent of "folder:123/document:456" → true
     * - "folder:123" is parent of "folder:456/document:789" → false
     * - "*" is parent of any resource → false (excluded from inheritance)
     *
     * @param  null|string $ruleResource   The resource ID from the policy rule
     * @param  string      $actualResource The target resource ID being accessed
     * @return bool        True if ruleResource is a parent path of actualResource
     */
    private function hasParentPath(?string $ruleResource, string $actualResource): bool
    {
        // Exclude wildcard and null resources from inheritance
        if ($ruleResource === null || $ruleResource === '*') {
            return false;
        }

        // Check if actualResource starts with ruleResource followed by path separator
        return str_starts_with($actualResource, $ruleResource.'/');
    }

    /**
     * Create an inherited rule by cloning a parent rule with a new resource target.
     *
     * Constructs a new policy rule preserving all attributes from the parent rule
     * (subject, action, effect, priority, domain) while replacing the resource with
     * the child resource identifier. This enables permission inheritance without
     * modifying the original rule or creating unintended side effects.
     *
     * @param  PolicyRule $rule          The parent rule to inherit from
     * @param  string     $childResource The child resource ID to target with the inherited rule
     * @return PolicyRule A new rule identical to the parent but targeting the child resource
     */
    private function inheritRule(PolicyRule $rule, string $childResource): PolicyRule
    {
        return new PolicyRule(
            subject: $rule->subject,
            resource: $childResource,
            action: $rule->action,
            effect: $rule->effect,
            priority: $rule->priority,
            domain: $rule->domain,
        );
    }
}
