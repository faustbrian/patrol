<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Delegation;

/**
 * Repository contract for managing delegation persistence and retrieval.
 *
 * Implementations handle storage operations for delegations, including creation,
 * lookup, listing, and cleanup of delegation records. The repository pattern
 * enables flexible storage strategies (database, cache, external service) while
 * maintaining consistent delegation management across the application.
 *
 * Implementations should:
 * - Ensure atomic operations for delegation state transitions
 * - Optimize queries for active delegation lookups (hot path)
 * - Support efficient cleanup of expired/revoked delegations
 * - Consider caching strategies for frequently accessed delegations
 *
 * @author Brian Faust <brian@cline.sh>
 * @see Delegation For the delegation value object structure
 * @see DelegationManager For business logic using this repository
 */
interface DelegationRepositoryInterface
{
    /**
     * Persist a new delegation to storage.
     *
     * Creates a new delegation record with all associated metadata. The delegation
     * should be immediately available for retrieval via findById() and included in
     * findActiveForDelegate() queries if its status is Active.
     *
     * @param Delegation $delegation The complete delegation to persist
     */
    public function create(Delegation $delegation): void;

    /**
     * Retrieve a delegation by its unique identifier.
     *
     * Loads a delegation record from storage regardless of its status (Active,
     * Expired, or Revoked). Returns null if no delegation exists with the given
     * identifier. Used for delegation management operations like revocation.
     *
     * @param  string          $id The unique delegation identifier
     * @return null|Delegation The delegation if found, null otherwise
     */
    public function findById(string $id): ?Delegation;

    /**
     * Retrieve all active delegations for a delegate subject.
     *
     * Returns delegations where the specified subject is the delegate (receiver)
     * and the delegation is currently Active and not expired. This is the critical
     * query for authorization checks and should be optimized with appropriate
     * indexes and caching strategies.
     *
     * The returned delegations should be filtered to only include:
     * - status = Active
     * - expiresAt is null OR expiresAt > now()
     * - delegateId matches the provided identifier
     *
     * @param  string            $delegateId The subject identifier receiving delegated permissions
     * @return array<Delegation> Array of active delegations (may be empty)
     */
    public function findActiveForDelegate(string $delegateId): array;

    /**
     * Mark a delegation as revoked.
     *
     * Updates the delegation's status to Revoked and sets revocation metadata
     * (timestamp, revoking user if applicable). The delegation should immediately
     * stop appearing in findActiveForDelegate() results.
     *
     * Implementations may also:
     * - Record who performed the revocation for audit trails
     * - Invalidate related caches
     * - Log the revocation event
     *
     * @param string $id The unique identifier of the delegation to revoke
     */
    public function revoke(string $id): void;

    /**
     * Remove expired and old revoked delegations from storage.
     *
     * Performs housekeeping by deleting delegation records that are no longer
     * needed for active authorization or audit trail purposes. Should remove:
     * - Expired delegations older than the retention period
     * - Revoked delegations older than the retention period
     *
     * This operation is typically run periodically via scheduled jobs or commands
     * to prevent unbounded growth of delegation tables.
     *
     * @return int The number of delegation records removed from storage
     */
    public function cleanup(): int;
}
