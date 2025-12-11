<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Override;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Models\Policy as PolicyModel;

use function is_int;
use function is_string;
use function now;

/**
 * Database-backed repository for loading authorization policies.
 *
 * Retrieves applicable policy rules from a database table based on subject and
 * resource identifiers. Supports wildcard matching for flexible policy definitions
 * and orders results by priority for correct effect resolution.
 *
 * The repository handles the translation from database rows to domain value objects,
 * enabling policy storage in relational databases while maintaining clean domain logic.
 *
 * ```php
 * $repository = new DatabasePolicyRepository(
 *     table: 'custom_policies',
 *     connection: 'tenant_db'
 * );
 *
 * $policy = $repository->getPoliciesFor($subject, $resource);
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DatabasePolicyRepository implements PolicyRepositoryInterface
{
    /**
     * Create a new database policy repository instance.
     *
     * @param string $connection The Laravel database connection name to use. Allows policies to be
     *                           stored in a separate database or use tenant-specific connections
     *                           for multi-tenant applications.
     */
    public function __construct(
        private string $connection = 'default',
    ) {}

    /**
     * Retrieve all applicable policies for a subject-resource pair.
     *
     * Queries the database for policy rules matching the subject and resource,
     * including wildcard matches (e.g., '*' for any subject/resource). Rules are
     * ordered by priority (descending) to ensure correct effect resolution.
     *
     * The query supports:
     * - Exact subject matching (e.g., 'user:123')
     * - Wildcard subject matching (e.g., '*' for any user)
     * - Exact resource matching (e.g., 'document:456')
     * - Wildcard resource matching (e.g., '*' for any resource)
     * - Null resource matching (global rules)
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   a policy object containing all applicable rules ordered by priority
     */
    #[Override()]
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $query = PolicyModel::on($this->connection);

        $query->where(function ($q) use ($subject): void {
            $q->where('subject', $subject->id)
                ->orWhere('subject', '*');
        });

        $query->where(function ($q) use ($resource): void {
            $q->where('resource', $resource->id)
                ->orWhere('resource', '*')
                ->orWhereNull('resource');
        });

        $query->orderBy('priority', 'desc');

        $policies = $query->get();

        $policyRules = [];

        foreach ($policies as $policy) {
            $subject = $policy->getAttribute('subject');
            $resource = $policy->getAttribute('resource');
            $action = $policy->getAttribute('action');
            $effect = $policy->getAttribute('effect');
            $priority = $policy->getAttribute('priority');
            $domain = $policy->getAttribute('domain');

            // @codeCoverageIgnoreStart
            if (!is_string($subject)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if (!is_string($action)) {
                continue;
            }

            $policyRules[] = new PolicyRule(
                subject: $subject,
                resource: is_string($resource) ? $resource : null,
                action: $action,
                effect: $effect instanceof Effect ? $effect : Effect::Deny,
                priority: new Priority(is_int($priority) ? $priority : 1),
                domain: is_string($domain) ? new Domain($domain) : null,
            );
        }

        return new Policy($policyRules);
    }

    /**
     * Persist policy to database.
     *
     * Saves all policy rules to the database table. Each rule becomes a separate row.
     * Existing policies are not deleted - this appends new rules.
     *
     * @param Policy $policy The policy containing rules to save
     */
    #[Override()]
    public function save(Policy $policy): void
    {
        foreach ($policy->rules as $rule) {
            PolicyModel::on($this->connection)->create([
                'subject' => $rule->subject,
                'resource' => $rule->resource,
                'action' => $rule->action,
                'effect' => $rule->effect,
                'priority' => $rule->priority->value,
                'domain' => $rule->domain?->id,
            ]);
        }
    }

    /**
     * Persist multiple policies atomically in a single transaction.
     *
     * Efficiently saves all policy rules using a bulk insert operation wrapped in a transaction.
     * This provides significant performance improvements over individual save() calls and ensures
     * atomicity - either all policies are saved or none are.
     *
     * @param array<Policy> $policies The policies to persist
     */
    #[Override()]
    public function saveMany(array $policies): void
    {
        if ($policies === []) {
            return;
        }

        $records = [];

        foreach ($policies as $policy) {
            foreach ($policy->rules as $rule) {
                $records[] = [
                    'subject' => $rule->subject,
                    'resource' => $rule->resource,
                    'action' => $rule->action,
                    'effect' => $rule->effect->value,
                    'priority' => $rule->priority->value,
                    'domain' => $rule->domain?->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($records === []) {
            return;
        }

        PolicyModel::on($this->connection)->insert($records);
    }

    /**
     * Delete multiple policy rules by their identifiers atomically.
     *
     * Removes all policy rules matching the provided IDs in a single database operation.
     * This is more efficient than deleting rules individually and ensures atomicity.
     *
     * @param array<string> $ruleIds The rule identifiers to delete
     */
    #[Override()]
    public function deleteMany(array $ruleIds): void
    {
        if ($ruleIds === []) {
            return;
        }

        DB::connection($this->connection)
            ->table('patrol_policies')
            ->whereIn('id', $ruleIds)
            ->delete();
    }

    /**
     * Soft delete a policy rule by its identifier.
     *
     * Marks the policy rule as deleted using Laravel's soft delete functionality.
     * The rule remains in the database but is excluded from normal queries.
     *
     * @param string $ruleId The rule identifier to soft delete
     */
    #[Override()]
    public function softDelete(string $ruleId): void
    {
        PolicyModel::on($this->connection)->findOrFail($ruleId)->delete();
    }

    /**
     * Restore a soft-deleted policy rule.
     *
     * Recovers a previously soft-deleted policy rule by clearing its deleted_at timestamp.
     *
     * @param string $ruleId The rule identifier to restore
     */
    #[Override()]
    public function restore(string $ruleId): void
    {
        /** @var PolicyModel $policy */
        $policy = PolicyModel::on($this->connection)->withTrashed()->findOrFail($ruleId); // @phpstan-ignore-line method.notFound
        $policy->restore();
    }

    /**
     * Permanently delete a policy rule, bypassing soft deletes.
     *
     * Removes the policy rule from the database permanently, even if soft deletes
     * are enabled. This action cannot be undone.
     *
     * @param string $ruleId The rule identifier to permanently delete
     */
    #[Override()]
    public function forceDelete(string $ruleId): void
    {
        /** @var PolicyModel $policy */
        $policy = PolicyModel::on($this->connection)->withTrashed()->findOrFail($ruleId); // @phpstan-ignore-line method.notFound
        $policy->forceDelete();
    }

    /**
     * Retrieve all soft-deleted policy rules.
     *
     * Returns only policies that have been soft deleted. Useful for administrative
     * interfaces showing deleted policies for potential recovery.
     *
     * @return Policy Policy containing all soft-deleted rules
     */
    #[Override()]
    public function getTrashed(): Policy
    {
        /** @var Collection<int, PolicyModel> $policies */
        $policies = PolicyModel::on($this->connection)->onlyTrashed()->orderBy('priority', 'desc')->get(); // @phpstan-ignore-line method.notFound

        $policyRules = [];

        foreach ($policies as $policy) {
            $subject = $policy->getAttribute('subject');
            $resource = $policy->getAttribute('resource');
            $action = $policy->getAttribute('action');
            $effect = $policy->getAttribute('effect');
            $priority = $policy->getAttribute('priority');
            $domain = $policy->getAttribute('domain');

            // @codeCoverageIgnoreStart
            if (!is_string($subject)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if (!is_string($action)) {
                continue;
            }

            $policyRules[] = new PolicyRule(
                subject: $subject,
                resource: is_string($resource) ? $resource : null,
                action: $action,
                effect: $effect instanceof Effect ? $effect : Effect::Deny,
                priority: new Priority(is_int($priority) ? $priority : 1),
                domain: is_string($domain) ? new Domain($domain) : null,
            );
        }

        return new Policy($policyRules);
    }

    /**
     * Retrieve all policies including soft-deleted ones.
     *
     * Returns both active and soft-deleted policies for the given subject and resource.
     * Useful for administrative interfaces and audit purposes.
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   Policy containing all rules regardless of deletion status
     */
    #[Override()]
    public function getWithTrashed(Subject $subject, Resource $resource): Policy
    {
        /** @var Builder<PolicyModel> $query */
        $query = PolicyModel::on($this->connection)->withTrashed(); // @phpstan-ignore-line method.notFound

        $query->where(function ($q) use ($subject): void {
            $q->where('subject', $subject->id)
                ->orWhere('subject', '*');
        });

        $query->where(function ($q) use ($resource): void {
            $q->where('resource', $resource->id)
                ->orWhere('resource', '*')
                ->orWhereNull('resource');
        });

        $query->orderBy('priority', 'desc');

        /** @var Collection<int, PolicyModel> $policies */
        $policies = $query->get();

        $policyRules = [];

        foreach ($policies as $policy) {
            $subject = $policy->getAttribute('subject');
            $resource = $policy->getAttribute('resource');
            $action = $policy->getAttribute('action');
            $effect = $policy->getAttribute('effect');
            $priority = $policy->getAttribute('priority');
            $domain = $policy->getAttribute('domain');

            // @codeCoverageIgnoreStart
            if (!is_string($subject)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if (!is_string($action)) {
                continue;
            }

            $policyRules[] = new PolicyRule(
                subject: $subject,
                resource: is_string($resource) ? $resource : null,
                action: $action,
                effect: $effect instanceof Effect ? $effect : Effect::Deny,
                priority: new Priority(is_int($priority) ? $priority : 1),
                domain: is_string($domain) ? new Domain($domain) : null,
            );
        }

        return new Policy($policyRules);
    }

    /**
     * Retrieve policies for multiple resources in single query.
     *
     * Optimized batch operation that loads policies for all resources at once,
     * avoiding N+1 query problems. Significantly improves performance for list
     * filtering and pagination.
     *
     * @param  Subject               $subject   The subject requesting access
     * @param  array<resource>       $resources Resources to check
     * @return array<string, Policy> Map of resource ID to policy
     */
    #[Override()]
    public function getPoliciesForBatch(Subject $subject, array $resources): array
    {
        if ($resources === []) {
            return [];
        }

        $resourceIds = [];

        foreach ($resources as $resource) {
            $resourceIds[] = $resource->id;
        }

        $query = PolicyModel::on($this->connection);

        $query->where(function ($q) use ($subject): void {
            $q->where('subject', $subject->id)
                ->orWhere('subject', '*');
        });

        $query->where(function ($q) use ($resourceIds): void {
            $q->whereIn('resource', $resourceIds)
                ->orWhere('resource', '*')
                ->orWhereNull('resource');
        });

        $query->orderBy('priority', 'desc');

        $policies = $query->get();

        // Group policies by resource ID
        $grouped = [];

        foreach ($policies as $policy) {
            $resourceId = $policy->getAttribute('resource');
            $subjectId = $policy->getAttribute('subject');
            $action = $policy->getAttribute('action');
            $effect = $policy->getAttribute('effect');
            $priority = $policy->getAttribute('priority');
            $domain = $policy->getAttribute('domain');

            // @codeCoverageIgnoreStart
            if (!is_string($subjectId)) {
                continue;
            }

            // @codeCoverageIgnoreEnd

            if (!is_string($action)) {
                continue;
            }

            $rule = new PolicyRule(
                subject: $subjectId,
                resource: is_string($resourceId) ? $resourceId : null,
                action: $action,
                effect: $effect instanceof Effect ? $effect : Effect::Deny,
                priority: new Priority(is_int($priority) ? $priority : 1),
                domain: is_string($domain) ? new Domain($domain) : null,
            );

            // Wildcard/null resources apply to all requested resources
            if ($resourceId === '*' || $resourceId === null) {
                foreach ($resourceIds as $id) {
                    $grouped[$id][] = $rule;
                }
            } elseif (is_string($resourceId)) {
                $grouped[$resourceId][] = $rule;
            }
        }

        // Convert to Policy objects
        $result = [];

        foreach ($resourceIds as $id) {
            $result[$id] = new Policy($grouped[$id] ?? []);
        }

        return $result;
    }
}
