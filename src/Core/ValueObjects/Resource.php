<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a protected resource in the authorization system.
 *
 * Encapsulates the target of an authorization request, defining what is being accessed.
 * Combines a unique identifier, resource type classification, and optional metadata for
 * flexible attribute-based and role-based access control. Resources can represent
 * domain objects, API endpoints, file system paths, or any protected entity.
 *
 * Resource patterns:
 * - Domain objects: id="document:123", type="document"
 * - API endpoints: id="/api/users/456", type="api_endpoint"
 * - Type-based: id="*", type="document" (matches all documents)
 * - With roles: id="project:789", type="project", attributes=['roles' => ['member', 'viewer']]
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Resource
{
    /**
     * Create a new immutable resource value object.
     *
     * @param string               $id         Unique resource identifier or path (e.g., "document:123",
     *                                         "/api/users/456"). Can be specific (exact resource) or
     *                                         pattern-based (e.g., "/api/users/*" for RESTful matching).
     *                                         Used by rule matchers to determine resource-level authorization.
     * @param string               $type       Resource type classification for type-based permissions
     *                                         (e.g., "document", "user", "project", "api_endpoint").
     *                                         Enables rules that apply to all resources of a specific
     *                                         type without specifying individual identifiers.
     * @param array<string, mixed> $attributes Optional resource metadata for attribute-based access control
     *                                         such as ownership information, resource roles, sensitivity
     *                                         levels, or custom properties. Common attributes include
     *                                         'roles' (array) for resource role matching and 'owner_id'
     *                                         (string) for ownership verification. Empty by default.
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $attributes = [],
    ) {}
}
