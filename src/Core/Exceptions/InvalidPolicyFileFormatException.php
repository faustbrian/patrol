<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown when a policy file contains invalid JSON or malformed data structure.
 *
 * This exception indicates that the policy file exists and is readable, but contains
 * syntactically invalid JSON or structurally incorrect policy data that cannot be
 * parsed into policy rules. Common issues include malformed JSON syntax, incorrect
 * data types for policy fields, or missing required fields like subject/action/effect.
 *
 * The repository performs fail-fast validation during construction to ensure policy
 * data integrity before any authorization requests are processed, preventing runtime
 * failures during security-critical operations.
 *
 * Common causes:
 * - Syntax errors in JSON (trailing commas, unquoted keys, etc.)
 * - Non-array root element (expecting array of policy objects)
 * - Missing required fields in policy definitions
 * - Incorrect data types (string where object expected, etc.)
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FilePolicyRepository For the repository that validates policy file structure
 */
final class InvalidPolicyFileFormatException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for invalid policy file format.
     *
     * Provides a static factory method that includes the problematic file path
     * in the error message to aid debugging of policy configuration issues.
     *
     * @param  string $filePath Absolute or relative path to the invalid policy file
     * @return self   A new exception instance with file path in error message
     */
    public static function create(string $filePath): self
    {
        return new self(sprintf('Invalid JSON in policy file: %s', $filePath));
    }
}
