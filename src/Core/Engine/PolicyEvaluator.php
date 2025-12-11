<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * Evaluates authorization policies to determine access decisions.
 *
 * Coordinates the policy evaluation workflow by filtering applicable rules using the
 * rule matcher, then resolving conflicts to produce a final Allow or Deny decision.
 * This orchestrator separates the concerns of rule matching (ACL vs ABAC) from effect
 * resolution, enabling flexible authorization models with consistent decision logic.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see RuleMatcherInterface For rule matching strategies
 * @see EffectResolver For conflict resolution between matching rules
 * @see Policy For the policy structure containing rules
 */
final readonly class PolicyEvaluator
{
    /**
     * Create a new policy evaluator.
     *
     * @param RuleMatcherInterface $ruleMatcher    Determines which policy rules match the authorization
     *                                             request based on subject, resource, and action constraints.
     *                                             The matcher implementation (ACL or ABAC) defines the
     *                                             authorization model semantics used for rule evaluation.
     * @param EffectResolver       $effectResolver resolves the final authorization decision when multiple
     *                                             rules match, implementing deny-override conflict resolution
     *                                             to ensure security-first decisions that prevent privilege
     *                                             escalation through conflicting rule effects
     */
    public function __construct(
        private RuleMatcherInterface $ruleMatcher,
        private EffectResolver $effectResolver,
    ) {}

    /**
     * Evaluate a policy to determine the authorization decision.
     *
     * Filters the policy rules to identify those matching the authorization request using
     * the configured rule matcher, then resolves any conflicts between matching rules to
     * produce a final Allow or Deny effect. The evaluation process is stateless and can
     * be performed concurrently for multiple authorization requests.
     *
     * ```php
     * $evaluator = new PolicyEvaluator($ruleMatcher, $effectResolver);
     * $effect = $evaluator->evaluate($policy, $subject, $resource, $action);
     * // Returns Effect::Allow or Effect::Deny
     * ```
     *
     * @param  Policy   $policy   The policy containing rules to evaluate
     * @param  Subject  $subject  The subject requesting access
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
        $matchingRules = [];

        // Filter policy rules to find those matching the authorization context
        foreach ($policy->rules as $rule) {
            if (!$this->ruleMatcher->matches($rule, $subject, $resource, $action)) {
                continue;
            }

            $matchingRules[] = $rule;
        }

        // Resolve conflicts between matching rules to determine final effect
        return $this->effectResolver->resolve($matchingRules);
    }
}
