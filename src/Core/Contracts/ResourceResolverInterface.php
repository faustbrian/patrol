<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Resource;

/**
 * Resolves resource identifiers into Resource value objects.
 *
 * Implementations convert various resource identifier formats (strings, objects,
 * arrays, models) into standardized Resource value objects that the authorization
 * engine can process. This abstraction allows the authorization system to work with
 * diverse resource representations while maintaining type safety and consistency.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see Resource For the resource value object structure
 */
interface ResourceResolverInterface
{
    /**
     * Convert a resource identifier into a Resource value object.
     *
     * Accepts flexible input formats such as string identifiers, Eloquent models,
     * domain objects, or custom resource representations, and transforms them into
     * the standardized Resource structure expected by the authorization engine. The
     * transformation should extract resource type, ID, and any relevant attributes.
     *
     * @param  mixed    $identifier The resource identifier to resolve (string, object, array, etc.)
     * @return resource The resolved Resource value object with type, ID, and attributes
     */
    public function resolve(mixed $identifier): Resource;
}
