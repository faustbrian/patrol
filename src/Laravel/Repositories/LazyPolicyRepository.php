<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

use Illuminate\Database\Query\Builder;
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
use stdClass;

use function assert;
use function constant;
use function in_array;
use function is_int;
use function is_string;

/**
 * Laravel database-backed policy repository with lazy loading and chunked processing.
 *
 * Implements efficient policy retrieval from a database table by lazily loading
 * policy rules in configurable chunks to prevent memory exhaustion when dealing
 * with large policy sets. Supports filtering by subject type, resource type, and
 * domain context while maintaining high priority ordering.
 *
 * Performance characteristics:
 * - Uses chunked processing to limit memory usage (default: 100 records per chunk)
 * - Builds optimized queries with proper WHERE clause combinations
 * - Orders results by priority DESC to ensure correct policy precedence
 * - Supports wildcard matching for subject and resource patterns
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class LazyPolicyRepository implements PolicyRepositoryInterface
{
    /**
     * Create a new lazy-loading policy repository instance.
     *
     * @param string $table      Database table name where policy rules are stored. Must contain
     *                           columns: subject, resource, action, effect, priority, domain.
     *                           Defaults to 'patrol_policies' for standard installations.
     * @param string $connection Laravel database connection name to use for queries. Allows
     *                           policies to be stored in a separate database from application
     *                           data. Defaults to 'default' connection.
     * @param int    $chunkSize  Number of policy records to process per database chunk operation.
     *                           Larger values improve query performance but increase memory usage.
     *                           Defaults to 100 records per chunk for balanced performance.
     */
    public function __construct(
        private string $table = 'patrol_policies',
        private string $connection = 'default',
        private int $chunkSize = 100,
    ) {}

    /**
     * Retrieve all policy rules applicable to a specific subject and resource.
     *
     * Fetches policy rules from the database that match the given subject and resource,
     * including wildcard rules. Processes results in chunks to optimize memory usage
     * for large policy sets. Rules are automatically ordered by priority (descending)
     * to ensure correct policy evaluation precedence.
     *
     * @param  Subject  $subject  The subject entity (user, role, group) requesting access.
     *                            Matches against exact subject ID or wildcard '*' patterns.
     * @param  resource $resource The target resource being accessed. Matches against exact
     *                            resource ID, wildcard '*' patterns, or NULL resource values.
     * @return Policy   Immutable policy object containing all applicable rules for evaluation
     */
    #[Override()]
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $query = $this->buildBaseQuery($subject, $resource);

        // Load only relevant policies based on subject type and resource type
        $rules = [];

        $query->chunk($this->chunkSize, function ($chunk) use (&$rules): void {
            /** @var stdClass $rule */
            foreach ($chunk as $rule) {
                $subjectId = $rule->subject;
                assert(is_string($subjectId));

                $resourceId = $rule->resource;
                assert($resourceId === null || is_string($resourceId));

                $action = $rule->action;
                assert(is_string($action));

                $effectName = $rule->effect;
                assert(is_string($effectName));
                $effectConstant = Effect::class.'::'.$effectName;
                $effect = constant($effectConstant);
                assert($effect instanceof Effect);

                $priorityValue = $rule->priority;
                assert(is_int($priorityValue));

                $domainId = $rule->domain ?? null;
                assert($domainId === null || is_string($domainId));

                $rules[] = new PolicyRule(
                    subject: $subjectId,
                    resource: $resourceId,
                    action: $action,
                    effect: $effect,
                    priority: new Priority($priorityValue),
                    domain: $domainId !== null ? new Domain($domainId) : null,
                );
            }
        });

        return new Policy($rules);
    }

    /**
     * Retrieve policy rules filtered by subject type prefix.
     *
     * Extends the base policy query to filter rules matching a specific subject type
     * pattern (e.g., "user:", "role:", "group:"). Useful for retrieving policies
     * that apply to an entire category of subjects while still including wildcard
     * rules that apply to all subjects.
     *
     * @param  string   $subjectType The subject type prefix to filter by (e.g., "user", "role").
     *                               Will be matched using LIKE pattern "{$subjectType}:%" to find
     *                               all subjects of this type, plus wildcard '*' subjects.
     * @param  Subject  $subject     The subject context for base query filtering
     * @param  resource $resource    The resource context for base query filtering
     * @return Policy   Policy object containing rules for the specified subject type and wildcards
     */
    public function getPoliciesForSubjectType(string $subjectType, Subject $subject, Resource $resource): Policy
    {
        $query = $this->buildBaseQuery($subject, $resource)
            ->where(function (Builder $q) use ($subjectType): void {
                $q->where('subject', 'like', $subjectType.':%')
                    ->orWhere('subject', '*');
            });

        $rules = $this->executeQuery($query);

        return new Policy($rules);
    }

    /**
     * Retrieve policy rules filtered by resource type prefix.
     *
     * Extends the base policy query to filter rules matching a specific resource type
     * pattern (e.g., "Post:", "Document:", "File:"). Includes rules with wildcard
     * resources ('*') and NULL resources to capture global policies that apply
     * regardless of resource type.
     *
     * @param  string   $resourceType The resource type prefix to filter by (e.g., "Post", "Document").
     *                                Matched using LIKE pattern "{$resourceType}:%" to find all
     *                                resources of this type, plus wildcards and NULL resources.
     * @param  Subject  $subject      The subject context for base query filtering
     * @param  resource $resource     The resource context for base query filtering
     * @return Policy   Policy object containing rules for the specified resource type and wildcards
     */
    public function getPoliciesForResourceType(string $resourceType, Subject $subject, Resource $resource): Policy
    {
        $query = $this->buildBaseQuery($subject, $resource)
            ->where(function (Builder $q) use ($resourceType): void {
                $q->where('resource', 'like', $resourceType.':%')
                    ->orWhere('resource', '*')
                    ->orWhereNull('resource');
            });

        $rules = $this->executeQuery($query);

        return new Policy($rules);
    }

    /**
     * Retrieve policy rules filtered by domain context.
     *
     * Filters policy rules based on domain-specific context, enabling multi-tenant
     * or context-aware authorization. When a domain is specified, returns rules
     * for that domain plus domain-agnostic rules (NULL domain). When no domain is
     * specified, returns only domain-agnostic rules.
     *
     * @param  null|string $domain   The domain context identifier (e.g., "tenant-123", "org-456").
     *                               When provided, matches exact domain or NULL domains. When NULL,
     *                               returns only domain-agnostic rules.
     * @param  Subject     $subject  The subject context for base query filtering
     * @param  resource    $resource The resource context for base query filtering
     * @return Policy      Policy object containing rules for the specified domain context
     */
    public function getPoliciesForDomain(?string $domain, Subject $subject, Resource $resource): Policy
    {
        $query = $this->buildBaseQuery($subject, $resource);

        if (!in_array($domain, [null, '', '0'], true)) {
            $query->where(function (Builder $q) use ($domain): void {
                $q->where('domain', $domain)
                    ->orWhereNull('domain');
            });
        } else {
            $query->whereNull('domain');
        }

        $rules = $this->executeQuery($query);

        return new Policy($rules);
    }

    /**
     * Save policy to database.
     *
     * Persists all policy rules to database table. Each rule becomes a separate row.
     *
     * @param Policy $policy The policy containing rules to save
     */
    #[Override()]
    public function save(Policy $policy): void
    {
        $data = [];

        foreach ($policy->rules as $rule) {
            $data[] = [
                'subject' => $rule->subject,
                'resource' => $rule->resource,
                'action' => $rule->action,
                'effect' => $rule->effect->value,
                'priority' => $rule->priority->value,
                'domain' => $rule->domain?->id,
            ];
        }

        DB::connection($this->connection)->table($this->table)->insert($data);
    }

    /**
     * Persist multiple policies atomically in a single transaction.
     *
     * Efficiently saves all policy rules using a bulk insert operation.
     * Provides better performance than individual save() calls.
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
                ];
            }
        }

        if ($records !== []) {
            DB::connection($this->connection)->table($this->table)->insert($records);
        }
    }

    /**
     * Delete multiple policy rules by their identifiers atomically.
     *
     * Removes all policy rules matching the provided IDs in a single operation.
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
            ->table($this->table)
            ->whereIn('id', $ruleIds)
            ->delete();
    }

    /**
     * Soft delete a policy rule by its identifier.
     *
     * Marks the policy rule as deleted using soft delete functionality.
     *
     * @param string $ruleId The rule identifier to soft delete
     */
    #[Override()]
    public function softDelete(string $ruleId): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->where('id', $ruleId)
            ->update(['deleted_at' => DB::raw('CURRENT_TIMESTAMP')]);
    }

    /**
     * Restore a soft-deleted policy rule.
     *
     * Recovers a previously soft-deleted policy rule.
     *
     * @param string $ruleId The rule identifier to restore
     */
    #[Override()]
    public function restore(string $ruleId): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->where('id', $ruleId)
            ->update(['deleted_at' => null]);
    }

    /**
     * Permanently delete a policy rule, bypassing soft deletes.
     *
     * Removes the policy rule from the database permanently.
     *
     * @param string $ruleId The rule identifier to permanently delete
     */
    #[Override()]
    public function forceDelete(string $ruleId): void
    {
        DB::connection($this->connection)
            ->table($this->table)
            ->where('id', $ruleId)
            ->delete();
    }

    /**
     * Retrieve all soft-deleted policy rules.
     *
     * Returns only policies that have been soft deleted.
     *
     * @return Policy Policy containing all soft-deleted rules
     */
    #[Override()]
    public function getTrashed(): Policy
    {
        $query = DB::connection($this->connection)
            ->table($this->table)
            ->whereNotNull('deleted_at')
            ->orderBy('priority', 'desc');

        $rules = $this->executeQuery($query);

        return new Policy($rules);
    }

    /**
     * Retrieve all policies including soft-deleted ones.
     *
     * Returns both active and soft-deleted policies for the given subject and resource.
     *
     * @param  Subject  $subject  The subject requesting access
     * @param  resource $resource The resource being accessed
     * @return Policy   Policy containing all rules regardless of deletion status
     */
    #[Override()]
    public function getWithTrashed(Subject $subject, Resource $resource): Policy
    {
        $query = DB::connection($this->connection)
            ->table($this->table)
            ->where(function (Builder $query) use ($subject): void {
                $query->where('subject', $subject->id)
                    ->orWhere('subject', '*');
            })
            ->where(function (Builder $query) use ($resource): void {
                $query->where('resource', $resource->id)
                    ->orWhere('resource', '*')
                    ->orWhereNull('resource');
            })
            ->orderBy('priority', 'desc');

        $rules = $this->executeQuery($query);

        return new Policy($rules);
    }

    /**
     * Retrieve policies for multiple resources in single query.
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

        $query = DB::connection($this->connection)
            ->table($this->table)
            ->where(function (Builder $query) use ($subject): void {
                $query->where('subject', $subject->id)
                    ->orWhere('subject', '*');
            })
            ->where(function (Builder $query) use ($resourceIds): void {
                $query->whereIn('resource', $resourceIds)
                    ->orWhere('resource', '*')
                    ->orWhereNull('resource');
            })
            ->orderBy('priority', 'desc');

        $grouped = [];

        $query->chunk($this->chunkSize, function ($chunk) use (&$grouped, $resourceIds): void {
            /** @var stdClass $rule */
            foreach ($chunk as $rule) {
                $subjectId = $rule->subject;
                assert(is_string($subjectId));

                $resourceId = $rule->resource;
                assert($resourceId === null || is_string($resourceId));

                $action = $rule->action;
                assert(is_string($action));

                $effectName = $rule->effect;
                assert(is_string($effectName));
                $effectConstant = Effect::class.'::'.$effectName;
                $effect = constant($effectConstant);
                assert($effect instanceof Effect);

                $priorityValue = $rule->priority;
                assert(is_int($priorityValue));

                $domainId = $rule->domain ?? null;
                assert($domainId === null || is_string($domainId));

                $policyRule = new PolicyRule(
                    subject: $subjectId,
                    resource: $resourceId,
                    action: $action,
                    effect: $effect,
                    priority: new Priority($priorityValue),
                    domain: $domainId !== null ? new Domain($domainId) : null,
                );

                // Wildcard/null resources apply to all requested resources
                if ($resourceId === '*' || $resourceId === null) {
                    foreach ($resourceIds as $id) {
                        $grouped[$id][] = $policyRule;
                    }
                } else {
                    $grouped[$resourceId][] = $policyRule;
                }
            }
        });

        // Convert to Policy objects
        $result = [];

        foreach ($resourceIds as $id) {
            $result[$id] = new Policy($grouped[$id] ?? []);
        }

        return $result;
    }

    /**
     * Build the base query for policy retrieval with subject and resource filtering.
     *
     * Constructs a database query that matches policies for the given subject and resource,
     * including wildcard patterns. The query automatically includes priority ordering to
     * ensure rules are processed in correct precedence order during policy evaluation.
     *
     * @param  Subject  $subject  The subject to match against policy rules. Matches exact
     *                            subject ID or wildcard '*' patterns.
     * @param  resource $resource The resource to match against policy rules. Matches exact
     *                            resource ID, wildcard '*' patterns, or NULL resources.
     * @return Builder  Query builder instance with filtering and ordering applied
     */
    private function buildBaseQuery(Subject $subject, Resource $resource): Builder
    {
        return DB::connection($this->connection)
            ->table($this->table)
            ->where(function (Builder $query) use ($subject): void {
                $query->where('subject', $subject->id)
                    ->orWhere('subject', '*');
            })
            ->where(function (Builder $query) use ($resource): void {
                $query->where('resource', $resource->id)
                    ->orWhere('resource', '*')
                    ->orWhereNull('resource');
            })
            ->orderBy('priority', 'desc');
    }

    /**
     * Execute the policy query and transform database records into PolicyRule objects.
     *
     * Processes query results in chunks to optimize memory usage, transforming each
     * database record into a properly typed PolicyRule value object. Handles effect
     * enum conversion and nullable domain values during transformation.
     *
     * @param  Builder                $query The prepared query builder instance
     *                                       containing filtering and ordering logic
     * @return array<int, PolicyRule> Array of PolicyRule objects constructed from database records
     */
    private function executeQuery(Builder $query): array
    {
        $rules = [];

        $query->chunk($this->chunkSize, function ($chunk) use (&$rules): void {
            /** @var stdClass $rule */
            foreach ($chunk as $rule) {
                $subjectId = $rule->subject;
                assert(is_string($subjectId));

                $resourceId = $rule->resource;
                assert($resourceId === null || is_string($resourceId));

                $action = $rule->action;
                assert(is_string($action));

                $effectName = $rule->effect;
                assert(is_string($effectName));
                $effectConstant = Effect::class.'::'.$effectName;
                $effect = constant($effectConstant);
                assert($effect instanceof Effect);

                $priorityValue = $rule->priority;
                assert(is_int($priorityValue));

                $domainId = $rule->domain ?? null;
                assert($domainId === null || is_string($domainId));

                $rules[] = new PolicyRule(
                    subject: $subjectId,
                    resource: $resourceId,
                    action: $action,
                    effect: $effect,
                    priority: new Priority($priorityValue),
                    domain: $domainId !== null ? new Domain($domainId) : null,
                );
            }
        });

        return $rules;
    }
}
