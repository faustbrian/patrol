<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a single authorization rule within a policy.
 *
 * Defines a complete access control rule specifying which subject (user, role, or group)
 * can perform what action on which resource, with what effect (allow or deny), and at
 * what priority for conflict resolution. Forms the atomic unit of policy-based
 * authorization decisions.
 *
 * Rule patterns:
 * - Role-based: subject="admin", resource="documents:*", action="*", effect=Allow
 * - Resource-specific: subject="user:123", resource="document:456", action="read", effect=Allow
 * - Deny rule: subject="*", resource="secrets", action="*", effect=Deny, priority=100
 * - RESTful: subject="api-client", resource="/api/users/*", action="GET", effect=Allow
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyRule
{
    /**
     * Create a new immutable policy rule.
     *
     * @param string      $subject  The subject pattern this rule applies to (e.g., user ID, role name,
     *                              or "*" for all subjects). Can reference specific users ("user:123"),
     *                              roles ("admin", "editor"), or wildcards for universal application.
     *                              Matched against the requesting subject during authorization.
     * @param null|string $resource The resource pattern this rule governs (e.g., "documents:*",
     *                              "document:123", "/api/users/:id"). Can be null for type-based
     *                              permissions, "*" for all resources, or specific resource identifiers.
     *                              Supports wildcards and parameterized patterns for flexible matching.
     * @param string      $action   The action pattern this rule permits or denies (e.g., "read", "write",
     *                              "*", "GET", "POST"). Can be a simple operation name, HTTP method, or
     *                              wildcard for all actions. Matched against the requested action during
     *                              evaluation.
     * @param Effect      $effect   The authorization decision when this rule matches (Allow or Deny).
     *                              Determines whether access is granted or blocked. Deny typically takes
     *                              precedence over Allow in conflict resolution.
     * @param Priority    $priority The rule priority for conflict resolution when multiple rules match.
     *                              Higher priority values are evaluated first. Defaults to priority 1
     *                              for standard rules. Use higher priorities (e.g., 100) for overrides
     *                              and exceptions that should take precedence.
     * @param null|Domain $domain   Optional domain scope restricting this rule to a specific tenant,
     *                              organization, or security boundary. When null, the rule applies
     *                              globally across all domains. When set, the rule only matches
     *                              authorization requests within the specified domain context.
     */
    public function __construct(
        public string $subject,
        public ?string $resource,
        public string $action,
        public Effect $effect,
        public Priority $priority = new Priority(1),
        public ?Domain $domain = null,
    ) {}
}
