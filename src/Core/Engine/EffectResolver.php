<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

use function usort;

/**
 * Resolves the final authorization decision from multiple matching policy rules.
 *
 * Implements a deny-override conflict resolution strategy where explicit denials take
 * precedence over allows, ensuring security-first authorization decisions. Rules are
 * evaluated in priority order to enable fine-grained control over which rules take
 * precedence when multiple rules match an authorization request.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see Effect For the effect enumeration (Allow/Deny)
 * @see PolicyEvaluator For the policy evaluation workflow
 */
final class EffectResolver
{
    /**
     * Resolve the final authorization effect from matching policy rules.
     *
     * Implements a four-step resolution algorithm: sort rules by priority (highest first),
     * return Deny if any rule explicitly denies, return Allow if any rule explicitly allows,
     * otherwise default to Deny for security. The deny-override strategy ensures that
     * explicit denials cannot be overridden by allows, preventing privilege escalation.
     *
     * ```php
     * $resolver = new EffectResolver();
     * $effect = $resolver->resolve($matchingRules);
     * // Returns Effect::Deny if any rule denies, Effect::Allow if allowed
     * ```
     *
     * @param  array<PolicyRule> $matchingRules The policy rules that matched the authorization request
     * @return Effect            The final authorization decision (Allow or Deny)
     */
    public function resolve(array $matchingRules): Effect
    {
        // No matching rules defaults to deny for security
        if ($matchingRules === []) {
            return Effect::Deny;
        }

        // Sort rules by priority in descending order (highest priority evaluated first)
        $sorted = $matchingRules;
        usort($sorted, fn ($a, $b): int => $b->priority->value <=> $a->priority->value);

        // Deny-override: first explicit deny wins immediately (security-first)
        foreach ($sorted as $rule) {
            if ($rule->effect === Effect::Deny) {
                return Effect::Deny;
            }
        }

        // No denies found - all rules must be Allow (only other Effect enum case)
        // Since Effect enum only has Allow/Deny, if no Deny exists, all must be Allow
        return Effect::Allow;
    }
}
