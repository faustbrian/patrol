<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

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
}
