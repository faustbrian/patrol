<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * Determines whether a policy rule matches an authorization request.
 *
 * Implementations define the matching logic for different authorization models
 * (ACL, ABAC, etc.), evaluating whether a rule applies to a given subject-resource-action
 * combination. The strategy pattern allows the authorization system to support multiple
 * access control paradigms with model-specific matching semantics.
 *
 * @see AbacRuleMatcher For attribute-based access control matching
 * @see AclRuleMatcher For access control list matching
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface RuleMatcherInterface
{
    /**
     * Determine if a policy rule matches the authorization request.
     *
     * Evaluates whether the rule's subject, resource, and action constraints match
     * the provided authorization context. The matching logic varies by implementation:
     * ACL matchers use identity-based matching, while ABAC matchers evaluate attribute
     * conditions and relationships between subjects and resources.
     *
     * @param  PolicyRule $rule     The policy rule to evaluate for matching
     * @param  Subject    $subject  The subject (user, role, group) requesting access
     * @param  resource   $resource The resource being accessed or acted upon
     * @param  Action     $action   The action being performed on the resource
     * @return bool       True if the rule matches and should be considered, false otherwise
     */
    public function matches(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): bool;
}
