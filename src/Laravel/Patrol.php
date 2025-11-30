<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel;

use Closure;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_key_exists;
use function assert;
use function class_basename;
use function get_object_vars;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;

/**
 * Laravel facade for configuring and accessing Patrol authorization components.
 *
 * This static facade provides a convenient API for registering custom resolvers
 * that bridge Laravel application context (authenticated users, route models, etc.)
 * with Patrol's authorization primitives. It acts as the integration layer between
 * Laravel's service container and Patrol's core authorization engine.
 *
 * Typical setup in a service provider:
 * ```php
 * Patrol::resolveSubject(fn () => auth()->user());
 * Patrol::resolveTenant(fn () => tenant());
 * Patrol::resolveResource(fn ($id) => app(ResourceRepository::class)->find($id));
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Patrol
{
    /**
     * Closure that resolves the current subject (user/actor) from the request context.
     *
     * @var null|Closure(): (null|array<string, mixed>|object|Subject)
     */
    private static ?Closure $subjectResolver = null;

    /**
     * Closure that resolves the current tenant/domain for multi-tenant applications.
     *
     * @var null|Closure(): (null|array<string, mixed>|Domain|object)
     */
    private static ?Closure $tenantResolver = null;

    /**
     * Closure that resolves resources by identifier for policy evaluation.
     *
     * @var null|Closure(mixed): (null|array<string, mixed>|object|resource)
     */
    private static ?Closure $resourceResolver = null;

    /**
     * Register a closure that resolves the current subject from the request context.
     *
     * The resolver should return the authenticated user or actor making the request.
     * The return value will be automatically converted to a Subject value object
     * if it's not already one.
     *
     * @param Closure(): (null|array<string, mixed>|object|Subject) $resolver Closure that returns the current subject,
     *                                                                        typically the authenticated user from Laravel's
     *                                                                        auth system. Can return a Subject, Eloquent model,
     *                                                                        array, or null for unauthenticated requests.
     */
    public static function resolveSubject(Closure $resolver): void
    {
        self::$subjectResolver = $resolver;
    }

    /**
     * Register a closure that resolves the current tenant/domain.
     *
     * Used for multi-tenant applications where authorization policies can be
     * scoped to specific tenants or organizational domains. The resolver should
     * return the current tenant context.
     *
     * @param Closure(): (null|array<string, mixed>|Domain|object) $resolver Closure that returns the current tenant/domain,
     *                                                                       typically from a multi-tenancy package or session.
     *                                                                       Can return a Domain, Eloquent model, array, or null.
     */
    public static function resolveTenant(Closure $resolver): void
    {
        self::$tenantResolver = $resolver;
    }

    /**
     * Register a closure that resolves resources by identifier.
     *
     * Used to load full resource objects (with attributes) when only an identifier
     * is provided in middleware parameters or policy evaluation. Enables attribute-based
     * policies that need access to resource properties.
     *
     * @param Closure(mixed): (null|array<string, mixed>|object|resource) $resolver Closure that accepts a resource identifier
     *                                                                              (string, int, or model instance) and returns
     *                                                                              the corresponding resource object with attributes.
     *                                                                              Can return a Resource, Eloquent model, array, or null.
     */
    public static function resolveResource(Closure $resolver): void
    {
        self::$resourceResolver = $resolver;
    }

    /**
     * Resolve and return the current subject from the configured resolver.
     *
     * Invokes the registered subject resolver to get the current actor (user)
     * and converts it to a Subject value object if necessary. Handles Eloquent
     * models, arrays, and existing Subject instances.
     *
     * @return null|Subject the current subject value object, or null if no resolver is
     *                      configured or the resolver returns null (unauthenticated request)
     */
    public static function currentSubject(): ?Subject
    {
        if (!self::$subjectResolver instanceof Closure) {
            return null;
        }

        $resolved = (self::$subjectResolver)();

        if ($resolved === null) {
            return null;
        }

        // Convert to Subject value object
        if ($resolved instanceof Subject) {
            return $resolved;
        }

        // Convert Eloquent model or array to Subject
        $id = 'anonymous';

        if (is_object($resolved) && property_exists($resolved, 'id')) {
            $idValue = $resolved->id;
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        } elseif (is_array($resolved) && array_key_exists('id', $resolved)) {
            $idValue = $resolved['id'];
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        }

        return new Subject(
            id: $id,
            attributes: self::extractAttributes($resolved),
        );
    }

    /**
     * Resolve and return the current tenant/domain from the configured resolver.
     *
     * Invokes the registered tenant resolver to get the current multi-tenant
     * context and converts it to a Domain value object if necessary.
     *
     * @return null|Domain the current domain/tenant value object, or null if no resolver
     *                     is configured or the resolver returns null
     */
    public static function currentTenant(): ?Domain
    {
        if (!self::$tenantResolver instanceof Closure) {
            return null;
        }

        $resolved = (self::$tenantResolver)();

        if ($resolved === null) {
            return null;
        }

        if ($resolved instanceof Domain) {
            return $resolved;
        }

        $id = 'default';

        if (is_object($resolved) && property_exists($resolved, 'id')) {
            $idValue = $resolved->id;
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        } elseif (is_array($resolved) && array_key_exists('id', $resolved)) {
            $idValue = $resolved['id'];
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        }

        return new Domain(
            id: $id,
            attributes: self::extractAttributes($resolved),
        );
    }

    /**
     * Resolve a resource by its identifier using the configured resource resolver.
     *
     * Invokes the registered resource resolver to load a resource object from
     * an identifier (string, int, or model instance). The resolver can fetch
     * the resource from a database, API, or other data source.
     *
     * @param  mixed         $identifier The resource identifier to resolve. Can be a string ID,
     *                                   integer primary key, or an existing model instance.
     * @return null|resource the resolved resource value object with attributes, or null
     *                       if no resolver is configured or the resource cannot be found
     */
    public static function resolveResourceById(mixed $identifier): ?Resource
    {
        if (!self::$resourceResolver instanceof Closure) {
            return null;
        }

        $resolved = (self::$resourceResolver)($identifier);

        if ($resolved === null) {
            return null;
        }

        if ($resolved instanceof Resource) {
            return $resolved;
        }

        $id = is_string($identifier) ? $identifier : 'unknown';

        if (is_object($resolved) && property_exists($resolved, 'id')) {
            $idValue = $resolved->id;
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        } elseif (is_array($resolved) && array_key_exists('id', $resolved)) {
            $idValue = $resolved['id'];
            assert(is_string($idValue) || is_int($idValue));
            $id = (string) $idValue;
        }

        return new Resource(
            id: $id,
            type: self::extractType($resolved),
            attributes: self::extractAttributes($resolved),
        );
    }

    /**
     * Reset all registered resolvers to their initial state.
     *
     * Clears all configured resolvers, useful for testing scenarios where you
     * need to ensure a clean state between test cases or when you want to
     * reconfigure the Patrol integration.
     */
    public static function reset(): void
    {
        self::$subjectResolver = null;
        self::$tenantResolver = null;
        self::$resourceResolver = null;
    }

    /**
     * Extract attributes from an object or array into a normalized array.
     *
     * Attempts to convert various data structures (Eloquent models, arrays,
     * plain objects) into a flat associative array suitable for storage in
     * value objects and policy evaluation.
     *
     * @param  mixed                $source The source data to extract attributes from. Can be an
     *                                      array, Eloquent model with toArray() method, or object
     *                                      with public properties.
     * @return array<string, mixed> normalized associative array of attributes, or empty
     *                              array if extraction is not possible
     */
    private static function extractAttributes(mixed $source): array
    {
        if (is_array($source)) {
            /** @var array<string, mixed> $source */
            return $source;
        }

        if (is_object($source)) {
            // Try toArray() method (Eloquent models)
            if (method_exists($source, 'toArray')) {
                $result = $source->toArray();
                assert(is_array($result), 'toArray() must return an array');

                /** @var array<string, mixed> $result */
                return $result;
            }

            // Try public properties
            /** @var array<string, mixed> */
            return get_object_vars($source);
        }

        return [];
    }

    /**
     * Extract a type identifier from an object or array.
     *
     * Determines a human-readable type name for a resource, preferring the
     * class basename for objects (e.g., "User" for App\Models\User) or a
     * "type" key for arrays.
     *
     * @param  mixed  $source The source data to extract type from. Can be an object
     *                        (class basename will be used) or array (looks for "type" key).
     * @return string the type identifier, or "unknown" if type cannot be determined
     */
    private static function extractType(mixed $source): string
    {
        if (is_object($source)) {
            return class_basename($source);
        }

        if (is_array($source) && array_key_exists('type', $source) && is_string($source['type'])) {
            return $source['type'];
        }

        return 'unknown';
    }
}
