<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a security or organizational domain.
 *
 * Encapsulates domain-scoped authorization boundaries for multi-tenant applications,
 * organizational hierarchies, or security partitions. Enables domain-specific role
 * assignments and resource isolation, allowing users to have different permissions
 * across domains (e.g., admin in organization A, viewer in organization B).
 *
 * Common use cases:
 * - Multi-tenant SaaS: Each tenant is a separate domain
 * - Enterprise hierarchies: Departments, business units, or subsidiaries
 * - Security zones: Public, internal, restricted, classified
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Domain
{
    /**
     * Create a new immutable domain value object.
     *
     * @param string               $id         Unique identifier for the domain (e.g., tenant ID,
     *                                         organization slug, or security zone name). Used to
     *                                         scope permissions and isolate resources within the
     *                                         authorization system.
     * @param array<string, mixed> $attributes Optional domain metadata for policy evaluation such
     *                                         as domain properties, hierarchical relationships, or
     *                                         custom attributes used in attribute-based access control.
     *                                         Empty by default for simple domain identification.
     */
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {}
}
