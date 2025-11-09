<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Subject;

/**
 * Resolves the current authenticated subject for authorization requests.
 *
 * Implementations determine the subject (user, API client, service account) making
 * the authorization request by accessing authentication context, session data, or
 * security tokens. This abstraction decouples the authorization system from specific
 * authentication mechanisms, enabling support for various authentication strategies.
 *
 * @see Subject For the subject value object structure
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface SubjectResolverInterface
{
    /**
     * Resolve the currently authenticated subject.
     *
     * Retrieves and transforms the authenticated entity into a Subject value object
     * that the authorization engine can process. Implementations typically access
     * Laravel's Auth facade, JWT tokens, API keys, or custom authentication contexts
     * to extract subject identity, roles, and relevant attributes for policy evaluation.
     *
     * @return Subject The resolved subject with ID, type, and authorization attributes
     */
    public function resolve(): Subject;
}
