<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use function count;

/**
 * Immutable value object representing differences between two policy versions.
 *
 * Captures the complete delta between policy versions by categorizing rules as added,
 * removed, or unchanged. Enables safe policy updates with clear visibility into impact,
 * supports audit trail generation for compliance, and facilitates policy review workflows.
 *
 * The diff categorizes rules based on their signature (subject, resource, action):
 * - Added: Rules present in new policy but not in old policy
 * - Removed: Rules present in old policy but not in new policy
 * - Unchanged: Rules with matching signatures in both versions
 *
 * Note: Rules with the same signature but different effect or priority are categorized
 * as "unchanged" since the signature defines rule identity. This design choice treats
 * effect/priority changes as modifications rather than add/remove operations.
 *
 * Common use cases:
 * - Pre-deployment policy review and approval workflows
 * - Audit trail generation for compliance documentation
 * - Impact analysis before applying policy updates
 * - Rollback decision support by comparing versions
 * - Policy migration validation
 *
 * ```php
 * $diff = new PolicyDiff($old, $new, $added, $removed, $unchanged);
 *
 * if (!$diff->isEmpty()) {
 *     echo "Changes: {$diff->getChangeCount()}\n";
 *     echo "Added: " . count($diff->addedRules) . "\n";
 *     echo "Removed: " . count($diff->removedRules) . "\n";
 * }
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see PolicyComparator For generating diffs from policy pairs
 */
final readonly class PolicyDiff
{
    /**
     * Create a new immutable policy diff.
     *
     * @param Policy            $oldPolicy      The original policy version serving as the comparison baseline.
     *                                          This represents the "before" state in the diff and is used to
     *                                          identify removed rules and calculate the unchanged rule set.
     * @param Policy            $newPolicy      The new policy version being compared against the baseline. This
     *                                          represents the "after" state and is used to identify added rules
     *                                          and the resulting policy if changes are applied.
     * @param array<PolicyRule> $addedRules     Rules present in the new policy but not in the old policy, identified
     *                                          by signature matching. These rules will be created if the new policy
     *                                          is deployed, expanding the authorization permissions or restrictions.
     * @param array<PolicyRule> $removedRules   Rules present in the old policy but not in the new policy. These rules
     *                                          will be deleted if the new policy is deployed, potentially removing
     *                                          existing permissions and affecting current authorization decisions.
     * @param array<PolicyRule> $unchangedRules Rules with matching signatures in both policies. These rules persist
     *                                          through the policy update and do not affect authorization behavior when
     *                                          the new policy is deployed (though effect or priority may differ).
     */
    public function __construct(
        public Policy $oldPolicy,
        public Policy $newPolicy,
        public array $addedRules,
        public array $removedRules,
        public array $unchangedRules,
    ) {}

    /**
     * Check if the policies are structurally identical.
     *
     * Returns true when there are no additions or removals, indicating the policies
     * contain the exact same rule signatures. An empty diff means deploying the new
     * policy would have no structural impact on authorization decisions.
     *
     * Note: Rules with matching signatures but different effects or priorities are
     * not considered changes, so this method may return true even if some rules
     * have modified attributes. Use this for structural comparison only.
     *
     * @return bool true if policies have identical rule signatures (no structural changes),
     *              false if rules were added or removed between versions
     */
    public function isEmpty(): bool
    {
        return $this->addedRules === [] && $this->removedRules === [];
    }

    /**
     * Get the total number of structural changes between policies.
     *
     * Counts both additions and removals to provide a simple metric of policy
     * modification scope. This value indicates how many authorization rules would
     * be affected by deploying the new policy. A higher change count suggests more
     * significant impact and may warrant additional review before deployment.
     *
     * Use this for:
     * - Risk assessment (high change count = higher risk)
     * - Approval threshold triggers (auto-approve small changes, review large ones)
     * - Deployment notifications (alert on significant policy changes)
     * - Metrics and monitoring (track policy evolution over time)
     *
     * @return int Total count of added and removed rules. Returns 0 when isEmpty() is true,
     *             indicating no structural changes between policy versions.
     */
    public function getChangeCount(): int
    {
        return count($this->addedRules) + count($this->removedRules);
    }
}
