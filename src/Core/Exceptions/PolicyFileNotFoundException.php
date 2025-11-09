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
 * Thrown when a policy file cannot be found at the specified path.
 *
 * This exception indicates a configuration error where the policy file path provided
 * to the repository does not exist in the filesystem. The repository performs fail-fast
 * validation during construction to ensure the policy file is accessible before any
 * authorization requests are processed, preventing runtime failures during security
 * operations.
 *
 * Common causes:
 * - Incorrect file path in configuration
 * - Missing policy file that was not deployed
 * - Relative path resolved from wrong working directory
 * - File permissions preventing visibility (uncommon)
 *
 * Resolution:
 * - Verify the policy file exists at the specified path
 * - Check for typos in the file path configuration
 * - Ensure the path is absolute or correctly resolved from working directory
 * - Confirm the file was deployed with the application
 *
 * @see FilePolicyRepository For the repository requiring valid policy file paths
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyFileNotFoundException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for missing policy file.
     *
     * Provides a static factory method that includes the expected file path
     * in the error message to aid debugging of configuration issues.
     *
     * @param  string $filePath The path where the policy file was expected but not found
     * @return self   A new exception instance with file path in error message
     */
    public static function create(string $filePath): self
    {
        return new self(sprintf('Policy file not found: %s', $filePath));
    }
}
