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
     * Persist a policy to storage.
     *
     * Saves all policy rules to the underlying storage mechanism (database, file system, etc.).
     * Implementations determine how to handle existing data (overwrite, merge, append based on
     * storage type and configuration).
     *
     * @param Policy $policy The policy containing rules to persist
     */
    public function save(Policy $policy): void;
}
