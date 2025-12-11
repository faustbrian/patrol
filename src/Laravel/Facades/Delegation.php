<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Facades;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Override;
use Patrol\Core\ValueObjects\Delegation as DelegationValue;

/**
 * Laravel facade for delegation operations.
 *
 * Provides static access to delegation management methods. This facade
 * wraps the Delegation static class to integrate with Laravel's facade
 * system for improved IDE support and consistent API with other Laravel facades.
 *
 * ```php
 * use Patrol\Laravel\Facades\Delegation;
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
 * @method static Collection<int, DelegationValue> active(object $user)
 * @method static bool                             canDelegate(object $user, array<int, string> $resources, array<int, string> $actions)
 * @method static DelegationValue                  grant(object $delegator, object $delegate, array<int, string> $resources, array<int, string> $actions, ?DateTimeImmutable $expiresAt = null, bool $transitive = false, array<string, mixed> $metadata = [])
 * @method static void                             revoke(string $delegationId)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see \Patrol\Laravel\Delegation
 */
final class Delegation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key
     */
    #[Override()]
    protected static function getFacadeAccessor(): string
    {
        return \Patrol\Laravel\Delegation::class;
    }
}
