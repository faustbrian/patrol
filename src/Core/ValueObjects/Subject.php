<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing a subject in the authorization system.
 *
 * A subject represents an entity requesting authorization, typically a user,
 * but can also represent service accounts, API clients, or other actors.
 * The subject carries an identifier and additional attributes that can be
 * used for attribute-based access control (ABAC) policy evaluation.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Subject
{
    /**
     * Create a new subject value object.
     *
     * @param string               $id         The unique identifier for the subject, typically a user ID
     *                                         or a special identifier like "guest" for unauthenticated requests.
     *                                         This ID is used to look up the subject's roles and permissions
     *                                         during policy evaluation.
     * @param array<string, mixed> $attributes Additional attributes associated with the subject that can be
     *                                         evaluated in policy conditions. May include user properties like
     *                                         email, department, roles, or custom attributes. These attributes
     *                                         enable attribute-based access control (ABAC) by allowing policies
     *                                         to make decisions based on subject characteristics.
     */
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {}
}
