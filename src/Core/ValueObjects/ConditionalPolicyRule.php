<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use Closure;

/**
 * Immutable value object representing a policy rule with conditional evaluation logic.
 *
 * Extends basic policy rule functionality with runtime condition evaluation, enabling
 * dynamic authorization decisions based on contextual information. Allows rules to be
 * activated or deactivated based on request attributes, time of day, resource state,
 * or custom business logic.
 *
 * Conditional patterns:
 * - Time-based: condition checks current time against business hours
 * - Attribute-based: condition evaluates user attributes or resource properties
 * - State-based: condition checks resource state or workflow status
 * - Ownership: condition verifies subject owns the resource
 *
 * The condition is evaluated at runtime with access to full request context, providing
 * flexibility beyond static policy rules for complex authorization scenarios.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ConditionalPolicyRule
{
    /**
     * Create a new immutable conditional policy rule.
     *
     * @param string                                   $subject   The subject pattern this rule applies to (e.g., user ID, role name,
     *                                                            or "*" for all subjects). Can reference specific users ("user:123"),
     *                                                            roles ("admin", "editor"), or wildcards for universal application.
     *                                                            Matched against the requesting subject during authorization.
     * @param null|string                              $resource  The resource pattern this rule governs (e.g., "documents:*",
     *                                                            "document:123", "/api/users/:id"). Can be null for type-based
     *                                                            permissions, "*" for all resources, or specific resource identifiers.
     *                                                            Supports wildcards and parameterized patterns for flexible matching.
     * @param string                                   $action    The action pattern this rule permits or denies (e.g., "read", "write",
     *                                                            "*", "GET", "POST"). Can be a simple operation name, HTTP method, or
     *                                                            wildcard for all actions. Matched against the requested action during
     *                                                            evaluation.
     * @param Effect                                   $effect    The authorization decision when this rule matches and condition passes
     *                                                            (Allow or Deny). Determines whether access is granted or blocked when
     *                                                            the condition evaluates to true. Deny typically takes precedence over
     *                                                            Allow in conflict resolution.
     * @param Priority                                 $priority  The rule priority for conflict resolution when multiple rules match.
     *                                                            Higher priority values are evaluated first. Defaults to priority 1
     *                                                            for standard rules. Use higher priorities (e.g., 100) for overrides
     *                                                            and exceptions that should take precedence.
     * @param null|Domain                              $domain    Optional domain scope restricting this rule to a specific tenant or
     *                                                            organizational boundary. When null, the rule applies globally across
     *                                                            all domains. When set, only applies within the specified domain.
     * @param null|Closure(array<string, mixed>): bool $condition Optional evaluation function receiving request context and returning
     *                                                            boolean. Signature: `function(array $context): bool`. When null, the
     *                                                            rule always applies (unconditional). When provided, rule only applies
     *                                                            if condition returns true. Context typically includes subject attributes,
     *                                                            resource properties, request metadata, and environmental factors.
     */
    public function __construct(
        public string $subject,
        public ?string $resource,
        public string $action,
        public Effect $effect,
        public Priority $priority = new Priority(1),
        public ?Domain $domain = null,
        public ?Closure $condition = null,
    ) {}

    /**
     * Evaluate the condition against the provided context.
     *
     * Executes the conditional logic if present, or returns true for unconditional rules.
     * This method determines whether the rule should be applied based on runtime context,
     * enabling dynamic authorization decisions that go beyond static pattern matching.
     *
     * @param  array<string, mixed> $context Request and environmental context for condition evaluation.
     *                                       May include subject attributes, resource properties, request
     *                                       metadata, time information, or custom business logic data.
     *                                       The structure depends on the specific condition implementation.
     * @return bool                 True if the condition passes (or no condition exists), false otherwise. When true,
     *                              the rule's effect (Allow/Deny) should be applied to the authorization decision.
     */
    public function evaluateCondition(array $context): bool
    {
        if (!$this->condition instanceof Closure) {
            return true;
        }

        return ($this->condition)($context);
    }

    /**
     * Convert this conditional rule to a standard policy rule.
     *
     * Strips the condition logic and creates an unconditional PolicyRule with the same
     * subject, resource, action, effect, priority, and domain. Useful for policy persistence,
     * caching evaluated rules, or converting dynamic rules to static rules after condition
     * evaluation.
     *
     * @return PolicyRule Immutable policy rule without conditional evaluation logic
     */
    public function toPolicyRule(): PolicyRule
    {
        return new PolicyRule(
            subject: $this->subject,
            resource: $this->resource,
            action: $this->action,
            effect: $this->effect,
            priority: $this->priority,
            domain: $this->domain,
        );
    }
}
