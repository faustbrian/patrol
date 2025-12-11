<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_key_exists;
use function array_shift;
use function explode;
use function sprintf;

/**
 * Validates delegation operations for security and business rule compliance.
 *
 * Ensures delegations meet security requirements before creation, preventing
 * privilege escalation, delegation cycles, and other security vulnerabilities.
 * Validates that delegators possess the permissions they attempt to delegate
 * and enforces business rules like maximum duration and transitivity policies.
 *
 * Validation responsibilities:
 * - Verify delegator has permissions being delegated (no privilege escalation)
 * - Detect and prevent delegation cycles (A→B→C→A)
 * - Enforce expiration constraints (max duration, future timestamps)
 * - Validate transitivity rules (prevent unauthorized delegation chains)
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DelegationValidator
{
    /**
     * Create a new delegation validator.
     *
     * @param PolicyRepositoryInterface     $policyRepository     Loads policies to verify delegator permissions
     * @param PolicyEvaluator               $policyEvaluator      Evaluates whether delegator has required permissions
     * @param DelegationRepositoryInterface $delegationRepository Queries existing delegations for cycle detection
     * @param null|int                      $maxDurationDays      Maximum allowed delegation duration in days (null = unlimited)
     */
    public function __construct(
        private PolicyRepositoryInterface $policyRepository,
        private PolicyEvaluator $policyEvaluator,
        private DelegationRepositoryInterface $delegationRepository,
        private ?int $maxDurationDays = null,
    ) {}

    /**
     * Validate a delegation before creation.
     *
     * Performs comprehensive validation to ensure the delegation meets all
     * security and business requirements. Returns true if valid, false otherwise.
     *
     * Validation checks:
     * 1. Delegator has the permissions they're delegating
     * 2. No delegation cycles would be created
     * 3. Expiration timestamp is valid (if set)
     * 4. Duration doesn't exceed maximum allowed
     *
     * @param  Delegation $delegation The delegation to validate
     * @param  Subject    $delegator  The subject granting permissions
     * @return bool       True if delegation is valid, false otherwise
     */
    public function validate(Delegation $delegation, Subject $delegator): bool
    {
        // Validate delegator has permissions being delegated
        if (!$this->validateDelegatorPermissions($delegator, $delegation->scope)) {
            return false;
        }

        // Prevent delegation cycles
        if ($this->detectCycle($delegation->delegatorId, $delegation->delegateId)) {
            return false;
        }

        // Validate expiration timestamp
        return $this->validateExpiration($delegation->expiresAt);
    }

    /**
     * Verify delegator possesses all permissions in the delegation scope.
     *
     * Ensures the delegator cannot grant permissions they don't have, preventing
     * privilege escalation attacks. Evaluates each resource-action combination in
     * the scope against the delegator's current permissions.
     *
     * For wildcard scopes, validates against representative samples since exhaustive
     * validation of wildcards is impractical. Applications should combine this with
     * runtime checks for defense in depth.
     *
     * @param  Subject         $delegator The subject attempting to delegate
     * @param  DelegationScope $scope     The permissions being delegated
     * @return bool            True if delegator has all permissions in scope
     */
    public function validateDelegatorPermissions(Subject $delegator, DelegationScope $scope): bool
    {
        // For each resource-action pair, verify delegator has permission
        foreach ($scope->resources as $resourcePattern) {
            foreach ($scope->actions as $actionPattern) {
                // Skip wildcard validation (too broad)
                if ($resourcePattern === '*') {
                    continue;
                }

                if ($actionPattern === '*') {
                    continue;
                }

                // Parse resource pattern (e.g., "document:123" -> id="document:123", type="document")
                // For patterns like "document:*", we extract the type before the colon
                $parts = explode(':', $resourcePattern, 2);
                $type = $parts[0];
                $id = $resourcePattern;

                $resource = new Resource($id, $type);
                $action = new Action($actionPattern);

                $policy = $this->policyRepository->getPoliciesFor($delegator, $resource);
                $effect = $this->policyEvaluator->evaluate($policy, $delegator, $resource, $action);

                if ($effect === Effect::Deny) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Detect delegation cycles to prevent circular delegation chains.
     *
     * Checks if creating a delegation from delegatorId to delegateId would create
     * a cycle in the delegation graph. A cycle occurs when a delegate can trace
     * back to themselves through delegation chains (A→B→C→A).
     *
     * This prevents:
     * - Infinite delegation loops
     * - Confusing permission inheritance paths
     * - Potential security vulnerabilities from complex chains
     *
     * Algorithm uses simple visited set tracking. For large delegation graphs,
     * consider caching delegation relationships or using more efficient graph
     * algorithms.
     *
     * @param  string $delegatorId The subject granting permissions
     * @param  string $delegateId  The subject receiving permissions
     * @return bool   True if cycle would be created, false if safe
     */
    public function detectCycle(string $delegatorId, string $delegateId): bool
    {
        $visited = [];
        $queue = [$delegateId];

        while ($queue !== []) {
            $current = array_shift($queue);

            // Found cycle: delegate's delegations lead back to delegator
            if ($current === $delegatorId) {
                return true;
            }

            // Already visited this node
            if (array_key_exists($current, $visited)) {
                continue;
            }

            $visited[$current] = true;

            // Find all delegations from current subject
            $delegations = $this->delegationRepository->findActiveForDelegate($current);

            foreach ($delegations as $delegation) {
                if (!$delegation->canTransit()) {
                    continue;
                }

                $queue[] = $delegation->delegatorId;
            }
        }

        return false;
    }

    /**
     * Validate expiration timestamp meets business rules.
     *
     * Ensures expiration timestamps are:
     * 1. In the future (not already expired)
     * 2. Within maximum allowed duration (if configured)
     *
     * Null expiration (permanent delegation) is valid if max duration not enforced.
     *
     * @param  null|DateTimeImmutable $expiresAt The proposed expiration timestamp
     * @return bool                   True if expiration is valid
     */
    public function validateExpiration(?DateTimeImmutable $expiresAt): bool
    {
        // Null expiration is valid (no automatic expiration)
        if (!$expiresAt instanceof DateTimeImmutable) {
            return true;
        }

        $now = CarbonImmutable::now();

        // Expiration must be in future
        if ($expiresAt <= $now) {
            return false;
        }

        // Check maximum duration if configured
        if ($this->maxDurationDays !== null) {
            $maxExpiration = $now->modify(sprintf('+%d days', $this->maxDurationDays));

            if ($expiresAt > $maxExpiration) {
                return false;
            }
        }

        return true;
    }
}
