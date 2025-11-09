<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Authorization decision effect for policy evaluation.
 *
 * Represents the outcome of a policy rule evaluation, determining whether
 * an access request should be granted or rejected. Used in explicit deny
 * and explicit allow authorization models where both positive and negative
 * permissions can be declared.
 *
 * Typical precedence patterns:
 * - Deny overrides: Any Deny effect blocks access regardless of Allow rules
 * - Allow by default: Deny effects are exceptions to general Allow policies
 * - Priority-based: Effect combined with priority for complex decision logic
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum Effect: string
{
    /**
     * Grants access to the requested resource and action.
     *
     * Represents an explicit permission allowing the subject to perform
     * the specified action on the target resource. Multiple Allow rules
     * can combine to grant cumulative permissions.
     */
    case Allow = 'Allow';

    /**
     * Denies access to the requested resource and action.
     *
     * Represents an explicit prohibition preventing the subject from
     * performing the specified action on the target resource. Typically
     * takes precedence over Allow rules to enforce security boundaries.
     */
    case Deny = 'Deny';
}
