<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Override;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Patrol as PatrolService;

/**
 * Laravel facade for the Patrol authorization system.
 *
 * Provides static access to Patrol's resolver registration methods and
 * current context retrieval. This facade wraps the Patrol static class
 * to integrate with Laravel's facade system for improved IDE support
 * and consistent API with other Laravel facades.
 *
 * ```php
 * use Patrol\Laravel\Facades\Patrol;
 *
 * // In a service provider:
 * Patrol::resolveSubject(fn () => auth()->user());
 * Patrol::resolveTenant(fn () => tenant());
 * Patrol::resolveResource(fn ($id) => Post::find($id));
 *
 * // In application code:
 * $subject = Patrol::currentSubject();
 * $tenant = Patrol::currentTenant();
 * ```
 *
 * @method static null|Subject  currentSubject()                                                                       Get the current subject from the resolver
 * @method static null|Domain   currentTenant()                                                                        Get the current tenant from the resolver
 * @method static void          reset()                                                                                Reset all registered resolvers
 * @method static void          resolveResource(Closure(mixed): (null|array<string, mixed>|object|Resource) $resolver) Register a closure that resolves resources by ID
 * @method static null|Resource resolveResourceById(mixed $identifier)                                                 Resolve a resource by identifier
 * @method static void          resolveSubject(Closure(): (null|array<string, mixed>|object|Subject) $resolver)        Register a closure that resolves the current subject
 * @method static void          resolveTenant(Closure(): (null|array<string, mixed>|Domain|object) $resolver)          Register a closure that resolves the current tenant
 *
 * @author Brian Faust <brian@cline.sh>
 * @see PatrolService
 */
final class Patrol extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key
     */
    #[Override()]
    protected static function getFacadeAccessor(): string
    {
        return self::class;
    }
}
