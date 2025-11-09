<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing an operation performed on a resource.
 *
 * Encapsulates the action component of an authorization request, representing
 * what operation is being attempted (e.g., "read", "write", "delete"). Supports
 * both traditional permission names and RESTful HTTP methods (e.g., "GET /api/documents").
 *
 * Common action patterns:
 * - CRUD operations: "create", "read", "update", "delete"
 * - HTTP methods: "GET", "POST", "PUT", "PATCH", "DELETE"
 * - Custom operations: "publish", "approve", "archive"
 * - RESTful actions: "GET /api/documents", "POST /api/documents"
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Action
{
    /**
     * Create a new immutable action value object.
     *
     * @param string $name The operation name or HTTP method being performed on the resource.
     *                     Can be a simple verb (e.g., "read"), an HTTP method (e.g., "GET"),
     *                     or a full RESTful action (e.g., "GET /api/documents"). Case-sensitive
     *                     for traditional permissions, case-insensitive for HTTP methods.
     */
    public function __construct(
        public string $name,
    ) {}
}
