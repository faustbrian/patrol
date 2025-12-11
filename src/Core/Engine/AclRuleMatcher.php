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

use function str_contains;
use function str_replace;

/**
 * Matches policy rules using Access Control List (ACL) semantics.
 *
 * Implements identity-based rule matching for traditional ACL authorization models.
 * Unlike ABAC, this matcher uses direct subject-resource-action equality checks with
 * support for wildcards and superuser privileges, making it suitable for straightforward
 * permission systems that don't require attribute-based conditions.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see RuleMatcherInterface For the rule matcher contract
 * @see AbacRuleMatcher For attribute-based matching with dynamic conditions
 */
final class AclRuleMatcher implements RuleMatcherInterface
{
    /**
     * Determine if a policy rule matches using ACL semantics.
     *
     * Evaluates subject, resource, and action constraints using identity-based matching
     * with wildcard and superuser support. All three components must match for the rule
     * to be considered applicable to the authorization request.
     *
     * @param  PolicyRule $rule     The policy rule to evaluate for matching
     * @param  Subject    $subject  The subject requesting access
     * @param  resource   $resource The resource being accessed
     * @param  Action     $action   The action being performed
     * @return bool       True if all rule constraints match the authorization context
     */
    #[Override()]
    public function matches(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): bool {
        return $this->matchesSubject($rule, $subject)
            && $this->matchesResource($rule, $resource)
            && $this->matchesAction($rule, $action);
    }

    /**
     * Evaluate if the rule's subject constraint matches the authorization subject.
     *
     * Implements two matching strategies: superuser wildcard grants universal access when
     * the subject has the superuser attribute set to true, and direct subject ID matching
     * for standard identity-based permissions. This provides a simple privilege escalation
     * mechanism for administrative accounts.
     *
     * @param  PolicyRule $rule    The rule containing subject constraints to evaluate
     * @param  Subject    $subject The subject requesting access
     * @return bool       True if the subject constraint matches
     */
    private function matchesSubject(PolicyRule $rule, Subject $subject): bool
    {
        // Superuser wildcard grants access when subject has superuser privilege
        if ($rule->subject === '*' && (($subject->attributes['superuser'] ?? false) === true)) {
            return true;
        }

        // Direct subject ID matching for identity-based permissions
        return $rule->subject === $subject->id;
    }

    /**
     * Evaluate if the rule's resource constraint matches the target resource.
     *
     * Implements four matching strategies: null resources for type-based permissions
     * that don't target specific instances, wildcards for unrestricted resource access,
     * exact ID matching for instance-specific permissions, and type wildcards using
     * "type:*" notation to grant access to all resources of a particular type.
     *
     * @param  PolicyRule $rule     The rule containing resource constraints to evaluate
     * @param  resource   $resource The resource being accessed
     * @return bool       True if the resource constraint matches
     */
    private function matchesResource(PolicyRule $rule, Resource $resource): bool
    {
        // Null resource allows action without targeting a specific resource instance
        if ($rule->resource === null) {
            return true;
        }

        // Wildcard grants access to all resources regardless of type or identity
        if ($rule->resource === '*') {
            return true;
        }

        // Exact resource ID matching for instance-specific permissions
        if ($rule->resource === $resource->id) {
            return true;
        }

        // Type wildcard matches all resources of a specific type (e.g., "document:*")
        if (str_contains($rule->resource, ':*')) {
            $ruleType = str_replace(':*', '', $rule->resource);

            return $resource->type === $ruleType;
        }

        return false;
    }

    /**
     * Evaluate if the rule's action constraint matches the requested action.
     *
     * Uses simple equality matching with wildcard support. The wildcard "*" grants
     * permission for all actions, while specific action names require exact case-sensitive
     * matching to ensure precise authorization control over different operation types.
     *
     * @param  PolicyRule $rule   The rule containing action constraints to evaluate
     * @param  Action     $action The action being performed on the resource
     * @return bool       True if the action constraint matches
     */
    private function matchesAction(PolicyRule $rule, Action $action): bool
    {
        // Wildcard allows all actions on the resource
        if ($rule->action === '*') {
            return true;
        }

        // Exact action name matching (case-sensitive)
        return $rule->action === $action->name;
    }
}
