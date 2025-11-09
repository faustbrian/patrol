<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when attempting to build a policy rule without setting the required subject.
 *
 * This exception indicates a programming error in the policy builder workflow where
 * the subject component was not specified before finalizing the rule. Subjects identify
 * who is requesting authorization (e.g., user ID, role, group) and are mandatory for
 * all policy rules. This validation ensures that incomplete rules without a defined
 * subject cannot be created, preventing runtime authorization failures.
 *
 * Common causes:
 * - Calling addRule() without first calling subject()
 * - Attempting to build a rule with null subject
 * - Incorrectly chaining builder methods
 *
 * @see PolicyRuleBuilder For the builder pattern requiring this validation
 * @see Subject For the subject value object identifying who requests access
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SubjectNotSetException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for missing subject configuration.
     *
     * Provides a static factory method for consistent exception creation with
     * a standardized error message that clearly identifies the validation failure.
     *
     * @return self A new exception instance with descriptive error message
     */
    public static function create(): self
    {
        return new self('Subject must be set before adding a rule');
    }
}
