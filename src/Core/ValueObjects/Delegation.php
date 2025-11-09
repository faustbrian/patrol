<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Immutable value object representing a temporary permission delegation.
 *
 * Encapsulates the complete state of a delegation where one subject (delegator)
 * temporarily grants a subset of their permissions to another subject (delegate).
 * Includes expiration management, transitivity controls, lifecycle status tracking,
 * and audit metadata for comprehensive delegation governance.
 *
 * Delegation lifecycle:
 * 1. Created with Active status
 * 2. Evaluated during authorization if Active and not expired
 * 3. Transitions to Expired when expiration time reached
 * 4. Can be Revoked early by delegator or administrator
 *
 * Use cases:
 * - Vacation coverage: Manager delegates approval rights to assistant for two weeks
 * - Task handoff: Developer delegates code review permissions for specific PR
 * - Temporary escalation: Support grants admin access for emergency maintenance
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Delegation
{
    /**
     * Create a new immutable delegation.
     *
     * @param string                 $id           Unique identifier for this delegation (UUID recommended)
     * @param string                 $delegatorId  Subject identifier of the user granting permissions
     *                                             (e.g., 'user:123'). Must possess the permissions being
     *                                             delegated at the time of delegation creation.
     * @param string                 $delegateId   Subject identifier of the user receiving permissions
     *                                             (e.g., 'user:456'). Receives temporary access according
     *                                             to the delegation scope and constraints.
     * @param DelegationScope        $scope        defines the exact permissions being delegated, including
     *                                             resource patterns, allowed actions, and optional domain
     *                                             restrictions for multi-tenant scenarios
     * @param DateTimeImmutable      $createdAt    Timestamp when this delegation was created. Used for
     *                                             audit trails and determining delegation age for cleanup.
     * @param null|DateTimeImmutable $expiresAt    Optional expiration timestamp. If null, delegation does
     *                                             not expire automatically and must be explicitly revoked.
     *                                             Should be validated against maximum duration policies.
     * @param bool                   $isTransitive Whether the delegate can further delegate these permissions
     *                                             to other subjects (default: false). Transitive delegations
     *                                             enable delegation chains but increase security risk.
     * @param DelegationState        $status       Current lifecycle status (Active, Expired, or Revoked).
     *                                             Determines whether this delegation is evaluated during
     *                                             authorization checks.
     * @param array<string, mixed>   $metadata     Optional metadata for business context and audit trails
     *                                             (e.g., ['reason' => 'Vacation coverage', 'project' => 'X']).
     *                                             Stored but not evaluated during authorization.
     */
    public function __construct(
        public string $id,
        public string $delegatorId,
        public string $delegateId,
        public DelegationScope $scope,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
        public bool $isTransitive = false,
        public DelegationState $status = DelegationState::Active,
        public array $metadata = [],
    ) {}

    /**
     * Check if this delegation has passed its expiration time.
     *
     * Compares the current time against the delegation's expiration timestamp
     * to determine if it should be considered expired. Delegations without an
     * expiration timestamp never expire through this check.
     *
     * Note: This method only checks the timestamp; it does not update the
     * delegation's status. Status transitions are handled by the delegation
     * manager or cleanup processes.
     *
     * @return bool True if expiration timestamp is set and has passed
     */
    public function isExpired(): bool
    {
        if (!$this->expiresAt instanceof DateTimeImmutable) {
            return false;
        }

        return $this->expiresAt < CarbonImmutable::now();
    }

    /**
     * Check if this delegation is currently active and usable.
     *
     * A delegation is active if its status is Active and it hasn't expired.
     * Only active delegations should be considered during authorization checks.
     * This combines status and expiration checks for convenience.
     *
     * @return bool True if delegation is Active and not expired
     */
    public function isActive(): bool
    {
        return $this->status === DelegationState::Active && !$this->isExpired();
    }

    /**
     * Check if this delegation can be transitively delegated.
     *
     * Determines whether the current delegate can further delegate these
     * permissions to other subjects. Transitive delegations enable chains
     * (A→B→C) but require careful security consideration to prevent
     * uncontrolled permission propagation.
     *
     * @return bool True if this delegation permits transitive delegation
     */
    public function canTransit(): bool
    {
        return $this->isTransitive;
    }
}
