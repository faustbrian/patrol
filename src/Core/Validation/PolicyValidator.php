<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Validation;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;

use const SORT_REGULAR;

use function array_key_exists;
use function array_unique;
use function count;
use function sprintf;
use function usort;

/**
 * Validates policy consistency and identifies potential authorization conflicts.
 *
 * Analyzes authorization policies to detect configuration issues like inconsistent priorities,
 * conflicting effects, and misconfigured deny-override behavior. Provides a fluent validation
 * interface that accumulates errors for comprehensive feedback, enabling policy administrators
 * to identify and fix multiple issues before deployment.
 *
 * Validation checks:
 * - Priority consistency: Same priority with different effects warns of conflicts
 * - Effect conflicts: Allow/Deny overlap with incorrect priority ordering
 * - Cycle detection: Reserved for future policy inheritance/delegation features
 *
 * @author Brian Faust <brian@cline.sh>
 * @see Policy For the policy structure being validated
 * @see EffectResolver For the deny-override resolution logic
 */
final class PolicyValidator
{
    /**
     * Accumulated validation errors found during policy analysis.
     *
     * @var array<int, string>
     */
    private array $errors = [];

    /**
     * The policy being validated.
     */
    private ?Policy $policy = null;

    /**
     * Create a new validator instance and begin validation.
     *
     * Provides a fluent static entry point for policy validation, enabling chained
     * validation checks and error accumulation across multiple validation rules.
     *
     * ```php
     * $validator = PolicyValidator::validate($policy)
     *     ->ensureConsistentPriorities()
     *     ->checkForConflicts();
     *
     * if ($validator->hasErrors()) {
     *     foreach ($validator->getErrors() as $error) {
     *         echo $error . "\n";
     *     }
     * }
     * ```
     *
     * @param  Policy $policy The policy to validate for consistency and conflicts
     * @return self   A validator instance ready for chained validation method calls
     */
    public static function validate(Policy $policy): self
    {
        return new self()->validatePolicy($policy);
    }

    /**
     * Ensure the policy contains no circular dependencies.
     *
     * Reserved for future policy inheritance and delegation features where policies
     * can reference other policies. Currently returns immediately as the flat policy
     * structure prevents cycles by design. When policy inheritance is implemented,
     * this method will detect circular references that could cause infinite loops.
     *
     * @return self Returns the validator instance for method chaining
     */
    public function ensureNoCycles(): self
    {
        // Cycles are not possible in current flat policy structure
        // This would be relevant for policy inheritance/delegation
        return $this;
    }

    /**
     * Verify that rules with the same priority have consistent effects.
     *
     * Groups rules by subject, resource, and action, then checks for priority conflicts
     * where the same priority value is assigned to multiple rules with different effects
     * (Allow vs Deny). Such conflicts indicate configuration errors that could lead to
     * unpredictable authorization behavior depending on rule evaluation order.
     *
     * Error conditions:
     * - Same subject/resource/action combination
     * - Same priority value
     * - Different effects (Allow vs Deny)
     *
     * @return self Returns the validator instance for method chaining
     */
    public function ensureConsistentPriorities(): self
    {
        if (!$this->policy instanceof Policy) {
            return $this;
        }

        $rules = $this->policy->rules;

        // Group rules by subject:resource:action key for priority analysis
        $priorityGroups = [];

        foreach ($rules as $rule) {
            $key = sprintf('%s:%s:%s', $rule->subject, $rule->resource ?? 'null', $rule->action);
            $priority = $rule->priority->value;

            if (!array_key_exists($key, $priorityGroups)) {
                $priorityGroups[$key] = [];
            }

            if (!array_key_exists($priority, $priorityGroups[$key])) {
                $priorityGroups[$key][$priority] = [];
            }

            $priorityGroups[$key][$priority][] = $rule->effect;
        }

        // Check each priority group for conflicting effects
        foreach ($priorityGroups as $key => $priorities) {
            foreach ($priorities as $priority => $effects) {
                $uniqueEffects = array_unique($effects, SORT_REGULAR);

                if (count($uniqueEffects) <= 1) {
                    continue;
                }

                $this->errors[] = sprintf(
                    'Inconsistent priority: Rules for %s with priority %d have conflicting effects',
                    $key,
                    $priority,
                );
            }
        }

        return $this;
    }

    /**
     * Detect misconfigured deny-override conflicts in rule groups.
     *
     * Identifies situations where rules with the same subject/resource/action have both
     * Allow and Deny effects, but the highest priority rule is not Deny. This violates
     * the expected deny-override semantics and indicates a policy misconfiguration that
     * could allow unauthorized access. The validator only warns when priorities suggest
     * the deny won't properly override the allow, as Allow/Deny overlap is valid when
     * properly prioritized.
     *
     * Warning conditions:
     * - Multiple rules for the same subject/resource/action
     * - Both Allow and Deny effects present
     * - Highest priority rule is Allow (should be Deny for proper deny-override)
     *
     * @return self Returns the validator instance for method chaining
     */
    public function checkForConflicts(): self
    {
        if (!$this->policy instanceof Policy) {
            return $this;
        }

        $rules = $this->policy->rules;

        // Group rules by subject:resource:action for conflict analysis
        $ruleGroups = [];

        foreach ($rules as $rule) {
            $key = sprintf('%s:%s:%s', $rule->subject, $rule->resource ?? 'null', $rule->action);

            if (!array_key_exists($key, $ruleGroups)) {
                $ruleGroups[$key] = [];
            }

            $ruleGroups[$key][] = $rule;
        }

        // Analyze each group for deny-override misconfigurations
        foreach ($ruleGroups as $key => $groupRules) {
            $hasAllow = false;
            $hasDeny = false;

            foreach ($groupRules as $rule) {
                if ($rule->effect === Effect::Allow) {
                    $hasAllow = true;
                }

                if ($rule->effect !== Effect::Deny) {
                    continue;
                }

                $hasDeny = true;
            }

            // Allow/Deny overlap is valid with proper deny-override priority
            if (!$hasAllow || !$hasDeny || count($groupRules) <= 1) {
                continue;
            }

            // Sort by priority to find highest priority rule
            $sortedRules = $groupRules;
            usort($sortedRules, fn ($a, $b): int => $b->priority->value <=> $a->priority->value);

            $highestPriority = $sortedRules[0];

            // Warn if highest priority is not Deny (violates deny-override)
            if ($highestPriority->effect === Effect::Deny) {
                continue;
            }

            $this->errors[] = sprintf(
                'Potential conflict: Rules for %s have both Allow and Deny, but highest priority is not Deny',
                $key,
            );
        }

        return $this;
    }

    /**
     * Retrieve all accumulated validation errors.
     *
     * Returns the complete list of error messages collected during validation checks,
     * enabling comprehensive feedback to policy administrators about all detected issues.
     *
     * @return array<int, string> Array of human-readable error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Determine if any validation errors were found.
     *
     * Provides a simple boolean check for validation failure, useful for conditional
     * logic and error handling workflows.
     *
     * @return bool True if one or more validation errors exist
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Determine if the policy passed all validation checks.
     *
     * Inverse of hasErrors(), providing semantic clarity for positive validation outcomes.
     *
     * @return bool True if no validation errors exist
     */
    public function isValid(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * Initialize the validator with a policy to validate.
     *
     * Sets the policy to be validated and returns the validator instance for method
     * chaining. This internal method supports the fluent validation interface.
     *
     * @param  Policy $policy The policy to validate
     * @return self   Returns this validator instance for chaining
     */
    private function validatePolicy(Policy $policy): self
    {
        $this->policy = $policy;

        return $this;
    }
}
