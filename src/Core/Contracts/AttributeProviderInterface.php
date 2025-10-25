<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

/**
 * Provides dynamic attribute resolution for entities in the authorization system.
 *
 * Implementations enable custom attribute extraction logic for ABAC (Attribute-Based
 * Access Control) policies, allowing flexible access decisions based on entity properties,
 * relationships, or computed values that may not be directly accessible as properties.
 *
 * @see AttributeResolver For the core attribute resolution implementation
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface AttributeProviderInterface
{
    /**
     * Retrieve a specific attribute value from an entity.
     *
     * Implementations should handle attribute extraction from various sources such as
     * object properties, array keys, database relationships, or computed values. Used
     * by the authorization engine to evaluate ABAC conditions like "resource.owner == subject.id".
     *
     * @param  object $entity    The entity (Subject or Resource) to extract the attribute from
     * @param  string $attribute The attribute name to retrieve (e.g., "owner", "department")
     * @return mixed  The attribute value, or null if the attribute does not exist
     */
    public function getAttribute(object $entity, string $attribute): mixed;
}
