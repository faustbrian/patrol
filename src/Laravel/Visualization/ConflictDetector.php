<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Visualization;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;

use function array_key_exists;
use function array_map;
use function count;
use function sprintf;
use function usort;

/**
 * Static analyzer for detecting conflicts and issues in authorization policy rules.
 *
 * Performs comprehensive policy analysis to identify potential problems that could
 * lead to unexpected authorization behavior or maintenance difficulties. Detects
 * three categories of issues: allow/deny conflicts (same subject-resource-action
 * with contradictory effects), priority collisions (multiple rules at same priority),
 * and unreachable rules (shadowed by higher-priority rules).
 *
 * Analysis results help policy authors identify and resolve ambiguities before
 * deploying policies to production, preventing authorization bugs and security
 * misconfigurations.
 *
 * ```php
 * $conflicts = ConflictDetector::detect($policy);
 *
 * if (!empty($conflicts['allow_deny_conflicts'])) {
 *     // Handle conflicting allow/deny rules
 *     foreach ($conflicts['allow_deny_conflicts'] as $conflict) {
 *         echo "Conflict: {$conflict['subject']} -> {$conflict['action']} -> {$conflict['resource']}\n";
 *     }
 * }
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class ConflictDetector
{
    /**
     * Detect all types of conflicts in a policy.
     *
     * Performs comprehensive policy analysis to identify three categories of issues:
     * allow/deny conflicts, priority collisions, and unreachable rules. Returns
     * structured data that can be used for policy validation, CI/CD checks, or
     * administrative dashboards. Useful for ensuring policy correctness before
     * deployment and identifying potential authorization bugs.
     *
     * @param  Policy                           $policy The policy to analyze for conflicts and issues
     * @return array<string, array<int, mixed>> Associative array with three keys: 'allow_deny_conflicts'
     *                                          (array of conflicting allow/deny rule groups), 'priority_collisions'
     *                                          (array of rules sharing the same priority), and 'unreachable_rules'
     *                                          (array of rules shadowed by higher-priority deny rules)
     */
    public static function detect(Policy $policy): array
    {
        return [
            'allow_deny_conflicts' => self::detectAllowDenyConflicts($policy),
            'priority_collisions' => self::detectPriorityCollisions($policy),
            'unreachable_rules' => self::detectUnreachableRules($policy),
        ];
    }

    /**
     * Detect allow/deny conflicts for the same subject-resource-action combination.
     *
     * Identifies policy rules where the same subject-resource-action tuple has both
     * Allow and Deny effects defined. This indicates ambiguous authorization behavior
     * that relies on priority resolution, which can be error-prone and difficult to
     * maintain. While the policy engine will handle these correctly based on priority,
     * they often indicate policy design issues that should be resolved for clarity
     * and maintainability.
     *
     * @param  Policy                           $policy The policy to analyze for allow/deny conflicts
     * @return array<int, array<string, mixed>> List of conflicts, each containing 'subject', 'resource',
     *                                          'action' (strings), and 'rules' (array of conflicting
     *                                          rules with 'effect' and 'priority' fields for debugging)
     */
    private static function detectAllowDenyConflicts(Policy $policy): array
    {
        $conflicts = [];
        $rulesByKey = [];

        foreach ($policy->rules as $rule) {
            $key = sprintf('%s|%s|%s', $rule->subject, $rule->resource, $rule->action);

            if (!array_key_exists($key, $rulesByKey)) {
                $rulesByKey[$key] = [];
            }

            $rulesByKey[$key][] = $rule;
        }

        foreach ($rulesByKey as $rules) {
            if (count($rules) < 2) {
                continue;
            }

            $hasAllow = false;
            $hasDeny = false;

            foreach ($rules as $rule) {
                if ($rule->effect === Effect::Allow) {
                    $hasAllow = true;
                }

                if ($rule->effect !== Effect::Deny) {
                    continue;
                }

                $hasDeny = true;
            }

            if (!$hasAllow || !$hasDeny) {
                continue;
            }

            $firstRule = $rules[0];
            $conflicts[] = [
                'subject' => $firstRule->subject,
                'resource' => $firstRule->resource,
                'action' => $firstRule->action,
                'rules' => array_map(
                    fn ($r): array => [
                        'effect' => $r->effect,
                        'priority' => $r->priority->value,
                    ],
                    $rules,
                ),
            ];
        }

        return $conflicts;
    }

    /**
     * Detect rules with identical priority values.
     *
     * Identifies groups of policy rules that share the same priority value, which
     * can lead to non-deterministic evaluation order when rules conflict. While not
     * always problematic, priority collisions make policy behavior harder to predict
     * and can cause subtle bugs when rules are added or modified. Best practice is
     * to use unique priorities for rules that might interact or conflict.
     *
     * @param  Policy                           $policy The policy to analyze for priority collisions
     * @return array<int, array<string, mixed>> List of priority levels with collisions. Each entry
     *                                          contains 'priority' (int), 'count' (number of rules),
     *                                          and 'rules' (array of rule details with subject, resource,
     *                                          action, and effect fields)
     */
    private static function detectPriorityCollisions(Policy $policy): array
    {
        $collisions = [];
        $rulesByPriority = [];

        foreach ($policy->rules as $rule) {
            $priority = $rule->priority->value;

            if (!array_key_exists($priority, $rulesByPriority)) {
                $rulesByPriority[$priority] = [];
            }

            $rulesByPriority[$priority][] = $rule;
        }

        foreach ($rulesByPriority as $priority => $rules) {
            if (count($rules) <= 1) {
                continue;
            }

            $collisions[] = [
                'priority' => $priority,
                'count' => count($rules),
                'rules' => array_map(
                    fn ($r): array => [
                        'subject' => $r->subject,
                        'resource' => $r->resource,
                        'action' => $r->action,
                        'effect' => $r->effect,
                    ],
                    $rules,
                ),
            ];
        }

        return $collisions;
    }

    /**
     * Detect potentially unreachable policy rules.
     *
     * Identifies Allow rules that are shadowed by higher-priority Deny rules for the
     * same subject-resource-action combination. These rules can never take effect
     * because the higher-priority deny will always be evaluated first, making them
     * dead code. This often indicates policy authoring errors or outdated rules that
     * should be removed. Unreachable rules create maintenance burden and can mislead
     * policy reviewers about actual authorization behavior.
     *
     * @param  Policy                           $policy The policy to analyze for unreachable rules
     * @return array<int, array<string, mixed>> List of unreachable rules. Each entry contains 'subject',
     *                                          'resource', 'action' (strings), 'priority' (int), and
     *                                          'reason' (descriptive string explaining why the rule is
     *                                          unreachable, typically "Shadowed by higher-priority deny rule")
     */
    private static function detectUnreachableRules(Policy $policy): array
    {
        $unreachable = [];
        $rules = $policy->rules;

        // Sort by priority descending (highest first)
        usort($rules, fn ($a, $b): int => $b->priority->value <=> $a->priority->value);

        $deniedKeys = [];

        foreach ($rules as $rule) {
            $key = sprintf('%s|%s|%s', $rule->subject, $rule->resource, $rule->action);

            if ($rule->effect === Effect::Deny) {
                $deniedKeys[$key] = $rule->priority->value;
            } elseif (array_key_exists($key, $deniedKeys) && $deniedKeys[$key] > $rule->priority->value) {
                // This allow rule is shadowed by a higher-priority deny
                $unreachable[] = [
                    'subject' => $rule->subject,
                    'resource' => $rule->resource,
                    'action' => $rule->action,
                    'priority' => $rule->priority->value,
                    'reason' => 'Shadowed by higher-priority deny rule',
                ];
            }
        }

        return $unreachable;
    }
}
