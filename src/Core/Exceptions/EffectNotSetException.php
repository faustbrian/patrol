<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Exceptions;

use InvalidArgumentException;

use function implode;
use function sprintf;

/**
 * Thrown when attempting to build a policy rule without setting the required effect.
 *
 * This exception indicates a programming error in the policy builder workflow where
 * the effect component (Allow or Deny) was not specified before finalizing the rule.
 * The effect determines the authorization decision when a rule matches a request and
 * is mandatory for all policy rules. This validation prevents ambiguous rules that
 * lack a clear authorization directive from being added to policies.
 *
 * Common causes:
 * - Calling addRule() without first calling allow() or deny()
 * - Attempting to build a rule with null effect
 * - Incorrectly chaining builder methods
 *
 * @see PolicyRuleBuilder For the builder pattern requiring this validation
 * @see Effect For the Allow/Deny effect enumeration
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class EffectNotSetException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for missing effect configuration.
     *
     * Provides a static factory method for consistent exception creation with
     * a standardized error message that clearly identifies the validation failure.
     *
     * @param  null|string $context Additional context about what was being built
     * @return self        A new exception instance with descriptive error message
     */
    public static function create(?string $context = null): self
    {
        $message = 'Effect must be set before adding a rule (call allow() or deny())';

        if ($context !== null && $context !== '') {
            $message .= sprintf(' (building rule for %s)', $context);
        }

        return new self($message);
    }

    /**
     * Create exception with subject and resource context.
     *
     * Provides a more detailed error message by including the subject and resource
     * identifiers that were being configured when the effect was found to be missing.
     * This helps developers quickly identify which specific rule builder call failed.
     *
     * @param  null|string $subject  Subject identifier being configured when the error occurred
     * @param  null|string $resource Resource identifier being configured when the error occurred
     * @return self        Exception instance with contextual information in the error message
     */
    public static function withContext(?string $subject = null, ?string $resource = null): self
    {
        $parts = [];

        if ($subject !== null) {
            $parts[] = sprintf("subject: '%s'", $subject);
        }

        if ($resource !== null) {
            $parts[] = sprintf("resource: '%s'", $resource);
        }

        $context = $parts === [] ? null : implode(', ', $parts);

        return self::create($context);
    }
}
