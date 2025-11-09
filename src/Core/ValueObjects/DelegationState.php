<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Enumeration of possible delegation lifecycle states.
 *
 * Tracks the current state of a delegation from creation through expiration
 * or revocation, enabling proper lifecycle management and query optimization.
 * Each state represents a distinct phase in the delegation's lifetime and
 * determines whether the delegation should be considered during authorization.
 *
 * State transitions:
 * - Active → Expired (automatic when expiration timestamp reached)
 * - Active → Revoked (manual revocation by delegator or administrator)
 * - Expired/Revoked are terminal states (no further transitions)
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum DelegationState: string
{
    /**
     * Delegation is currently active and should be evaluated.
     *
     * The delegation has been created and has not yet expired or been revoked.
     * Active delegations are included in authorization checks and grant their
     * associated permissions to the delegate subject.
     */
    case Active = 'active';

    /**
     * Delegation has reached its expiration timestamp.
     *
     * The delegation's expiration time has passed and it is no longer valid
     * for granting permissions. Expired delegations are excluded from authorization
     * checks but may be retained for audit trail purposes according to the
     * configured retention policy.
     */
    case Expired = 'expired';

    /**
     * Delegation has been manually revoked before expiration.
     *
     * The delegator or an administrator has explicitly revoked this delegation,
     * terminating it before its natural expiration. Revoked delegations are
     * excluded from authorization checks and stored for audit purposes.
     */
    case Revoked = 'revoked';
}
