<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a role in the authorization system.
 *
 * Roles are assigned to subjects (users) and can be optionally scoped to
 * a specific domain (tenant/organization). This enables both global roles
 * and tenant-specific role assignments for multi-tenant applications.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Role
{
    /**
     * Create a new role value object.
     *
     * @param string      $name   The unique identifier for the role (e.g., "admin", "editor", "viewer").
     *                            Used for matching against policy statements and determining subject
     *                            permissions. Role names are case-sensitive and should follow a
     *                            consistent naming convention across the application.
     * @param null|Domain $domain Optional domain/tenant scope for the role assignment. When provided,
     *                            the role only applies within that specific tenant context, enabling
     *                            multi-tenant authorization where users can have different roles in
     *                            different organizations or workspaces.
     */
    public function __construct(
        public string $name,
        public ?Domain $domain = null,
    ) {}
}
