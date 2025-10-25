<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a named permission with optional domain scope.
 *
 * Encapsulates a specific access right that can be granted to subjects, defining
 * what operations are allowed in the authorization system. Supports both global
 * permissions (applicable across all domains) and domain-scoped permissions
 * (restricted to specific tenants or organizational boundaries).
 *
 * Permission naming conventions:
 * - Dot notation: "users.create", "documents.read", "reports.export"
 * - Verb-noun: "create-user", "read-document", "export-report"
 * - Hierarchical: "admin.users.manage", "api.documents.write"
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Permission
{
    /**
     * Create a new immutable permission value object.
     *
     * @param string      $name   Unique permission identifier describing the access right being
     *                            granted (e.g., "users.create", "documents.read"). Used to match
     *                            against policy rules and determine authorization for specific
     *                            operations. Should follow consistent naming conventions.
     * @param null|Domain $domain Optional domain scope restricting this permission to a specific
     *                            tenant, organization, or security boundary. When null, the
     *                            permission is global and applies across all domains. When set,
     *                            the permission only grants access within the specified domain.
     */
    public function __construct(
        public string $name,
        public ?Domain $domain = null,
    ) {}
}
