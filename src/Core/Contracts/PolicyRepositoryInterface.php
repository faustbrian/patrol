<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * Retrieves authorization policies for subject-resource combinations.
 *
 * Implementations are responsible for loading policy rules from storage (database,
 * file system, cache, or external policy service) that govern access decisions for
 * a specific subject attempting to interact with a resource. The repository pattern
 * allows flexible policy storage strategies while maintaining a consistent interface.
 *
 * @see Policy For the policy structure and rule definitions
 * @see PolicyEvaluator For how retrieved policies are evaluated
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface PolicyRepositoryInterface
{
    /**
     * Retrieve the authorization policy applicable to a subject-resource pair.
     *
     * Loads and returns all policy rules that should be evaluated when determining
     * whether the subject can perform actions on the resource. Implementations should
     * optimize query performance and consider caching strategies for frequently accessed
     * policies to minimize latency in authorization checks.
     *
     * @param  Subject  $subject  The subject (user, role, group) requesting access
     * @param  resource $resource The resource being accessed or acted upon
     * @return Policy   The policy containing all applicable authorization rules
     */
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy;

    /**
     * Retrieve policies for multiple resources with single subject.
     *
     * Optimized batch operation that loads policies for all resources in a single
     * query to avoid N+1 problems. Significantly improves performance for list
     * filtering and pagination.
     *
     * @param  Subject               $subject   The subject requesting access
     * @param  array<resource>       $resources Resources to check
     * @return array<string, Policy> Map of resource ID to policy
     */
    public function getPoliciesForBatch(Subject $subject, array $resources): array;

    /**
     * Persist a policy to storage.
     *
     * Saves all policy rules to the underlying storage mechanism (database, file system, etc.).
     * Implementations determine how to handle existing data (overwrite, merge, append based on
     * storage type and configuration).
     *
     * @param Policy $policy The policy containing rules to persist
     */
    public function save(Policy $policy): void;

    /**
     * Persist multiple policies to storage atomically.
     *
     * Saves all policies in a single transaction when supported by the underlying storage.
     * This provides better performance and atomicity compared to saving policies individually.
     * If the storage doesn't support transactions, implementations should still ensure all-or-nothing
     * semantics where possible.
     *
     * @param array<Policy> $policies The policies to persist
     */
    public function saveMany(array $policies): void;

    /**
     * Delete multiple policies by their rule identifiers.
     *
     * Removes all policy rules matching the provided identifiers in a single operation.
     * This is more efficient than deleting policies individually and provides atomicity
     * when supported by the storage mechanism.
     *
     * @param array<string> $ruleIds The rule identifiers to delete
     */
    public function deleteMany(array $ruleIds): void;

    /**
     * Soft delete a policy rule by its identifier.
     *
     * Marks the policy rule as deleted without permanently removing it from storage.
     * This allows for audit trails and data recovery. The behavior depends on the
     * storage implementation and configuration.
     *
     * @param string $ruleId The rule identifier to soft delete
     */
    public function softDelete(string $ruleId): void;

    /**
     * Restore a soft-deleted policy rule.
     *
     * Recovers a previously soft-deleted policy rule, making it active again.
     * Only applicable when soft deletes are supported by the storage implementation.
     *
     * @param string $ruleId The rule identifier to restore
     */
    public function restore(string $ruleId): void;

    /**
     * Permanently delete a policy rule, bypassing soft deletes.
     *
     * Removes the policy rule from storage even if soft deletes are enabled.
     * This action cannot be undone.
     *
     * @param string $ruleId The rule identifier to permanently delete
     */
    public function forceDelete(string $ruleId): void;

    /**
     * Retrieve all soft-deleted policy rules.
     *
     * Returns only policies that have been soft deleted. Requires soft delete
     * support in the storage implementation.
     *
     * @return Policy Policy containing all soft-deleted rules
     */
    public function getTrashed(): Policy;

    /**
     * Retrieve all policies including soft-deleted ones.
     *
     * Returns both active and soft-deleted policies. Useful for administrative
     * interfaces and audit purposes.
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   Policy containing all rules regardless of deletion status
     */
    public function getWithTrashed(Subject $subject, Resource $resource): Policy;
}
