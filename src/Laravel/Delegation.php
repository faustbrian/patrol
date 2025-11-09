<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\ValueObjects\Delegation as DelegationValue;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\Subject;

use function app;
use function collect;

/**
 * Facade for delegation operations in Laravel applications.
 *
 * Provides a convenient static API for creating, revoking, and querying
 * delegations. Wraps the DelegationManager with Laravel-friendly method
 * signatures and return types.
 *
 * ```php
 * use Patrol\Laravel\Delegation;
 *
 * // Grant delegation
 * Delegation::grant(
 *     delegator: $manager,
 *     delegate: $assistant,
 *     resources: ['document:*'],
 *     actions: ['read', 'edit'],
 *     expiresAt: now()->addDays(7)
 * );
 *
 * // Revoke delegation
 * Delegation::revoke($delegationId);
 *
 * // List active delegations
 * $delegations = Delegation::active($user);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Delegation
{
    /**
     * Grant permissions from one user to another.
     *
     * Creates a new delegation after validating that the delegator has
     * the permissions being delegated. Returns the created delegation
     * value object for reference.
     *
     * @param object                 $delegator  The user granting permissions (must have id property)
     * @param object                 $delegate   The user receiving permissions (must have id property)
     * @param array<string>          $resources  Resource patterns being delegated
     * @param array<string>          $actions    Action patterns being delegated
     * @param null|DateTimeImmutable $expiresAt  Optional expiration timestamp
     * @param bool                   $transitive Allow delegate to re-delegate
     * @param array<string, mixed>   $metadata   Optional metadata for context
     *
     * @phpstan-param object{id: string} $delegator
     * @phpstan-param object{id: string} $delegate
     *
     * @return DelegationValue The created delegation
     */
    public static function grant(
        object $delegator,
        object $delegate,
        array $resources,
        array $actions,
        ?DateTimeImmutable $expiresAt = null,
        bool $transitive = false,
        array $metadata = [],
    ): DelegationValue {
        $manager = app(DelegationManager::class);

        return $manager->delegate(
            delegator: new Subject($delegator->id),
            delegate: new Subject($delegate->id),
            scope: new DelegationScope($resources, $actions),
            expiresAt: $expiresAt,
            transitive: $transitive,
            metadata: $metadata,
        );
    }

    /**
     * Revoke a delegation by its ID.
     *
     * Marks the delegation as revoked, immediately removing it from
     * active authorization checks.
     *
     * @param string $delegationId The unique delegation identifier
     */
    public static function revoke(string $delegationId): void
    {
        $manager = app(DelegationManager::class);
        $manager->revoke($delegationId);
    }

    /**
     * Get all active delegations for a user.
     *
     * Returns a collection of delegations where the user is the delegate
     * (receiver of permissions), filtered to only active, non-expired delegations.
     *
     * @param object $user The user to query (must have id property)
     *
     * @phpstan-param object{id: string} $user
     *
     * @return Collection<int, DelegationValue> Active delegations for this user
     */
    public static function active(object $user): Collection
    {
        $manager = app(DelegationManager::class);
        $delegations = $manager->findActiveDelegations(
            new Subject($user->id),
        );

        return collect($delegations)->values();
    }

    /**
     * Check if a user can delegate the specified permissions.
     *
     * Useful for UI controls and pre-flight validation before
     * attempting to create a delegation.
     *
     * @param object        $user      The user who would delegate
     * @param array<string> $resources Resource patterns to delegate
     * @param array<string> $actions   Action patterns to delegate
     *
     * @phpstan-param object{id: string} $user
     *
     * @return bool True if user can delegate these permissions
     */
    public static function canDelegate(object $user, array $resources, array $actions): bool
    {
        $manager = app(DelegationManager::class);

        return $manager->canDelegate(
            delegator: new Subject($user->id),
            scope: new DelegationScope($resources, $actions),
        );
    }
}
