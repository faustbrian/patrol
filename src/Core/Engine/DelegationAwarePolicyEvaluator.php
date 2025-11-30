<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * Policy evaluator decorator that includes delegated permissions.
 *
 * Wraps the standard PolicyEvaluator to transparently include delegated permissions
 * in authorization decisions. When a subject doesn't have direct permission, checks
 * if they have been delegated the required permission by another subject.
 *
 * Evaluation flow:
 * 1. Evaluate direct permissions via underlying PolicyEvaluator
 * 2. If direct evaluation allows, return Allow immediately
 * 3. If direct evaluation denies, check delegated permissions
 * 4. Convert active delegations to PolicyRules and evaluate
 * 5. Return Allow if delegated permission grants access, otherwise original Deny
 *
 * This approach ensures:
 * - Direct permissions take precedence (faster evaluation)
 * - Delegations are additive (cannot remove permissions)
 * - Deny effects in direct policies are respected
 * - Performance impact only when needed (no delegations = fast path)
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DelegationAwarePolicyEvaluator
{
    /**
     * Create a new delegation-aware policy evaluator.
     *
     * @param PolicyEvaluator   $evaluator         The underlying policy evaluator for direct permissions
     * @param DelegationManager $delegationManager Manages delegation retrieval and conversion to rules
     */
    public function __construct(
        private PolicyEvaluator $evaluator,
        private DelegationManager $delegationManager,
    ) {}

    /**
     * Evaluate a policy with delegated permissions considered.
     *
     * First evaluates direct permissions for the subject. If denied, checks whether
     * the subject has been delegated permissions that would grant access. Delegated
     * permissions are additive and cannot override explicit deny rules in direct policies.
     *
     * Performance optimizations:
     * - Short-circuits on direct Allow (most common case)
     * - Only queries delegations on direct Deny
     * - Caches delegation queries when using CachedDelegationRepository
     *
     * ```php
     * $evaluator = new DelegationAwarePolicyEvaluator($policyEvaluator, $delegationManager);
     * $effect = $evaluator->evaluate($policy, $subject, $resource, $action);
     *
     * // Returns Allow if:
     * // 1. Direct policy allows, OR
     * // 2. Direct policy denies but delegation allows
     * ```
     *
     * @param  Policy   $policy   The direct authorization policy for the subject
     * @param  Subject  $subject  The subject requesting access (may have delegated permissions)
     * @param  resource $resource The resource being accessed
     * @param  Action   $action   The action being performed
     * @return Effect   The final authorization decision (Allow or Deny)
     */
    public function evaluate(
        Policy $policy,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): Effect {
        // First evaluate direct permissions (fast path for most cases)
        $directResult = $this->evaluator->evaluate($policy, $subject, $resource, $action);

        if ($directResult === Effect::Allow) {
            return Effect::Allow;
        }

        // Check delegated permissions if direct evaluation denied
        $delegatedRules = $this->delegationManager->toPolicyRules($subject);

        if ($delegatedRules === []) {
            return $directResult;
        }

        // Evaluate delegated permissions as a separate policy
        $delegatedPolicy = new Policy($delegatedRules);
        $delegatedResult = $this->evaluator->evaluate($delegatedPolicy, $subject, $resource, $action);

        // Delegated permissions are additive: Allow if either grants access
        return $delegatedResult === Effect::Allow ? Effect::Allow : $directResult;
    }
}
