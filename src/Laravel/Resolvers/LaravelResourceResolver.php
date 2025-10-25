<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Resolvers;

use Override;
use Patrol\Core\Contracts\ResourceResolverInterface;
use Patrol\Core\ValueObjects\Resource;

use function assert;
use function class_basename;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;

/**
 * Laravel-specific implementation of resource resolution for policy evaluation.
 *
 * Converts Laravel Eloquent models or identifiers into Resource value objects
 * that can be used in policy evaluation. Automatically extracts model attributes
 * to enable attribute-based access control (ABAC) policies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LaravelResourceResolver implements ResourceResolverInterface
{
    /**
     * Resolve a resource identifier into a Resource value object.
     *
     * Converts various resource identifier formats into standardized Resource objects
     * for policy evaluation. Supports Eloquent models (extracting attributes for ABAC)
     * and scalar identifiers (for simple ID-based policies). Model attributes enable
     * fine-grained attribute-based access control by making all model data available
     * to policy conditions.
     *
     * ```php
     * // Resolve an Eloquent model with full attributes
     * $post = Post::find(1);
     * $resource = $resolver->resolve($post);
     * // Result: Resource(id: "1", type: "Post", attributes: [...])
     *
     * // Resolve a scalar identifier
     * $resource = $resolver->resolve('post-123');
     * // Result: Resource(id: "post-123", type: "unknown", attributes: [])
     * ```
     *
     * @param  mixed    $identifier The resource to resolve. Accepts Eloquent model instances
     *                              (extracts ID from model->id, type from class name, and full
     *                              attributes via toArray()) or scalar values (used directly
     *                              as ID with type "unknown" and no attributes).
     * @return resource Immutable Resource value object containing ID, type classification,
     *                  and attributes for attribute-based access control policies
     */
    #[Override()]
    public function resolve(mixed $identifier): Resource
    {
        // Convert Eloquent models to Resource with full attributes
        if (is_object($identifier) && method_exists($identifier, 'toArray')) {
            assert(property_exists($identifier, 'id'));
            $id = $identifier->id;
            assert(is_string($id) || is_int($id));

            /** @var array<string, mixed> $attributes */
            $attributes = $identifier->toArray();
            $type = class_basename($identifier);

            return new Resource(
                id: (string) $id,
                type: $type,
                attributes: $attributes,
            );
        }

        // Treat scalar values as simple resource identifiers
        assert(is_string($identifier) || is_int($identifier));

        return new Resource(
            id: (string) $identifier,
            type: 'unknown',
        );
    }
}
