<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Override;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function str_contains;
use function str_replace;

/**
 * RBAC (Role-Based Access Control) rule matching engine.
 *
 * Implements hierarchical role matching for access control decisions by evaluating
 * subject roles, resource roles, and actions against policy rules. Supports global
 * roles, domain-scoped roles, wildcard matching, and type-based permissions for
 * flexible RBAC implementations.
 *
 * Matching hierarchy:
 * 1. Subject: Direct ID match → Global roles → Domain-scoped roles
 * 2. Resource: Null (type-based) → Wildcard → Direct ID → Type wildcard → Resource roles
 * 3. Action: Wildcard → Exact match
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RbacRuleMatcher implements RuleMatcherInterface
{
    /**
     * Determine if a policy rule matches the given authorization request.
     *
     * Evaluates all three components (subject, resource, action) in sequence,
     * short-circuiting on first mismatch for optimal performance. All conditions
     * must match for the rule to be considered applicable.
     *
     * @param  PolicyRule $rule     The policy rule to evaluate against the authorization request
     * @param  Subject    $subject  The subject (user/service) requesting access with role attributes
     * @param  resource   $resource The target resource being accessed with optional role metadata
     * @param  Action     $action   The operation being performed on the resource
     * @return bool       True if the rule matches all components of the authorization request
     */
    #[Override()]
    public function matches(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): bool {
        return $this->matchesSubjectRole($rule, $subject)
            && $this->matchesResourceRole($rule, $resource)
            && $this->matchesAction($rule, $action);
    }

    /**
     * Match the rule's subject against the requesting subject's roles.
     *
     * Implements a three-tier matching strategy to support flexible RBAC models:
     * 1. Direct ID match for ACL-style fallback compatibility
     * 2. Global role matching from the subject's primary role set
     * 3. Domain-scoped role matching for multi-tenant/organizational hierarchies
     *
     * @param  PolicyRule $rule    The policy rule containing the subject pattern to match
     * @param  Subject    $subject The requesting subject with roles in attributes['roles']
     *                             and optional domain_roles in attributes['domain_roles'][$domain]
     * @return bool       True if the subject matches via direct ID, global role, or domain role
     */
    private function matchesSubjectRole(PolicyRule $rule, Subject $subject): bool
    {
        // Direct subject ID match (ACL fallback)
        if ($rule->subject === $subject->id) {
            return true;
        }

        // Check global roles first
        $subjectRoles = $subject->attributes['roles'] ?? [];

        if (!is_array($subjectRoles)) {
            $subjectRoles = [];
        }

        if (in_array($rule->subject, $subjectRoles, true)) {
            return true;
        }

        // Domain-scoped role matching
        if (array_key_exists('domain', $subject->attributes) && is_string($subject->attributes['domain'])) {
            $domain = $subject->attributes['domain'];
            $domainRolesData = $subject->attributes['domain_roles'] ?? [];

            if (!is_array($domainRolesData)) {
                return false;
            }

            $domainRoles = $domainRolesData[$domain] ?? [];

            if (!is_array($domainRoles)) {
                $domainRoles = [];
            }

            if (in_array($rule->subject, $domainRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match the rule's resource pattern against the target resource.
     *
     * Supports multiple matching strategies ordered by specificity:
     * - Null resource: Matches all resources (type-based permissions)
     * - Wildcard (*): Universal resource match
     * - Direct ID: Exact resource identifier match
     * - Type wildcard (type:*): Matches all resources of a specific type
     * - Role-based: Matches resources with assigned roles in attributes['roles']
     *
     * @param  PolicyRule $rule     The policy rule containing the resource pattern to match
     * @param  resource   $resource The target resource with optional type, ID, and role attributes
     * @return bool       True if the resource matches any of the supported patterns
     */
    private function matchesResourceRole(PolicyRule $rule, Resource $resource): bool
    {
        // Null resource (type-based permissions)
        if ($rule->resource === null) {
            return true;
        }

        // Wildcard
        if ($rule->resource === '*') {
            return true;
        }

        // Direct resource ID match
        if ($rule->resource === $resource->id) {
            return true;
        }

        // Type wildcard
        if (str_contains($rule->resource, ':*')) {
            $ruleType = str_replace(':*', '', $rule->resource);

            return $resource->type === $ruleType;
        }

        // Resource role matching
        $resourceRoles = $resource->attributes['roles'] ?? [];

        if (!is_array($resourceRoles)) {
            $resourceRoles = [];
        }

        return in_array($rule->resource, $resourceRoles, true);
    }

    /**
     * Match the rule's action against the requested action.
     *
     * Provides simple action matching with wildcard support for broad permissions.
     * The wildcard (*) allows a rule to grant access to all actions on a resource.
     *
     * @param  PolicyRule $rule   The policy rule containing the action pattern to match
     * @param  Action     $action The requested action to perform on the resource
     * @return bool       True if the action matches via wildcard or exact name match
     */
    private function matchesAction(PolicyRule $rule, Action $action): bool
    {
        if ($rule->action === '*') {
            return true;
        }

        return $rule->action === $action->name;
    }
}
