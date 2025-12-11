<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyDiff;
use Patrol\Core\ValueObjects\PolicyRule;

use function array_key_exists;
use function sprintf;

/**
 * Compares policy versions to identify changes for auditing and impact analysis.
 *
 * Analyzes two policy versions to generate a comprehensive diff showing which rules
 * were added, removed, or remained unchanged. This enables safe policy updates with
 * clear visibility into the impact of changes before deployment. The comparator uses
 * rule signatures (subject, resource, action) to match rules between versions, ignoring
 * differences in effect and priority when determining rule identity.
 *
 * Use cases:
 * - Pre-deployment policy review and approval workflows
 * - Audit trail generation for compliance requirements
 * - Impact analysis before applying policy changes
 * - Rollback decision support by comparing current vs previous
 * - Policy migration validation when upgrading authorization systems
 *
 * Signature matching strategy:
 * - Rules match if subject, resource, and action are identical
 * - Effect and priority differences do not create separate rules
 * - Domain scope is not considered in signature (treated as metadata)
 * - Null resources match as wildcards in signature generation
 *
 * ```php
 * $comparator = new PolicyComparator();
 * $diff = $comparator->compare($currentPolicy, $proposedPolicy);
 *
 * if (!$diff->isEmpty()) {
 *     echo "Adding {count($diff->addedRules)} rules\n";
 *     echo "Removing {count($diff->removedRules)} rules\n";
 *     // Review changes before applying...
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @see PolicyDiff For the resulting diff structure with categorized changes
 */
final class PolicyComparator
{
    /**
     * Compare two policies and generate a diff showing all changes.
     *
     * Identifies rules that were added, removed, or remained unchanged between
     * policy versions by matching rule signatures. The comparison ignores effect
     * and priority changes, treating them as modifications to the same rule rather
     * than different rules. This provides a clear picture of structural policy changes.
     *
     * Algorithm:
     * 1. Index both policies by rule signature for O(1) lookups
     * 2. Iterate through new policy to find added and unchanged rules
     * 3. Iterate through old policy to find removed rules
     * 4. Wrap results in PolicyDiff value object for consumption
     *
     * Performance: O(N + M) where N = old rule count, M = new rule count
     *
     * @param  Policy     $oldPolicy The original policy version serving as the comparison baseline.
     *                               Typically represents the currently deployed policy or a previous
     *                               snapshot used for rollback analysis.
     * @param  Policy     $newPolicy The new policy version to compare against the baseline. Usually
     *                               represents proposed changes, migration output, or the current
     *                               state when comparing against historical versions.
     * @return PolicyDiff Immutable diff object containing categorized rule changes. Includes references
     *                    to both original policies and arrays of added, removed, and unchanged rules.
     *                    The diff provides helper methods like isEmpty() and getChangeCount() for
     *                    quick impact assessment.
     */
    public function compare(Policy $oldPolicy, Policy $newPolicy): PolicyDiff
    {
        $oldRules = $this->indexRulesBySignature($oldPolicy->rules);
        $newRules = $this->indexRulesBySignature($newPolicy->rules);

        $addedRules = [];
        $removedRules = [];
        $unchangedRules = [];

        // Find added and unchanged rules
        foreach ($newRules as $signature => $rule) {
            if (array_key_exists($signature, $oldRules)) {
                $unchangedRules[] = $rule;
            } else {
                $addedRules[] = $rule;
            }
        }

        // Find removed rules
        foreach ($oldRules as $signature => $rule) {
            if (array_key_exists($signature, $newRules)) {
                continue;
            }

            $removedRules[] = $rule;
        }

        return new PolicyDiff(
            oldPolicy: $oldPolicy,
            newPolicy: $newPolicy,
            addedRules: $addedRules,
            removedRules: $removedRules,
            unchangedRules: $unchangedRules,
        );
    }

    /**
     * Index rules by their signature for efficient comparison lookups.
     *
     * Creates a hash map of rule signatures to rules, enabling O(1) lookups
     * when comparing policies. The signature is based on subject, resource,
     * and action which uniquely identify a rule's purpose. If multiple rules
     * share the same signature (unusual but possible), later rules overwrite
     * earlier ones in the index.
     *
     * @param  array<PolicyRule>         $rules Array of policy rules to index by signature
     * @return array<string, PolicyRule> Map of signature strings to their corresponding rules.
     *                                   Signatures follow format "subject:resource:action" with
     *                                   wildcard "*" for null resources.
     */
    private function indexRulesBySignature(array $rules): array
    {
        $indexed = [];

        foreach ($rules as $rule) {
            $signature = $this->getRuleSignature($rule);
            $indexed[$signature] = $rule;
        }

        return $indexed;
    }

    /**
     * Generate a unique signature string for a rule based on its scope.
     *
     * Combines subject, resource, and action into a single string that
     * uniquely identifies the rule's authorization scope. Effect and priority
     * are intentionally excluded as they represent rule configuration rather
     * than rule identity. This allows detecting when a rule's scope remains
     * the same but its effect or priority changes.
     *
     * Format: "subject:resource:action"
     * - Null resources represented as "*" wildcard
     * - No normalization of whitespace or casing (exact matching)
     *
     * @param  PolicyRule $rule Rule to generate signature for
     * @return string     Unique signature string identifying the rule's scope. Example:
     *                    "role:admin:document:*:write" or "user:123:*:read"
     */
    private function getRuleSignature(PolicyRule $rule): string
    {
        return sprintf(
            '%s:%s:%s',
            $rule->subject,
            $rule->resource ?? '*',
            $rule->action,
        );
    }
}
