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
 * Thrown when a policy file exists but cannot be read due to permissions or I/O errors.
 *
 * This exception indicates that the policy file was found in the filesystem but could
 * not be read, typically due to insufficient file permissions or underlying I/O failures.
 * The repository performs fail-fast validation during construction to ensure policy
 * data is accessible before any authorization requests are processed, preventing runtime
 * failures during security operations.
 *
 * Common causes:
 * - Insufficient file permissions (file not readable by application user)
 * - File system I/O errors or disk failures
 * - File locked by another process
 * - Network file system connectivity issues
 *
 * Resolution:
 * - Verify the application has read permissions for the policy file
 * - Check file ownership and permission settings (chmod/chown)
 * - Ensure the disk and file system are functioning properly
 * - If on network storage, verify connectivity and mount status
 *
 * @author Brian Faust <brian@cline.sh>
 * @see FilePolicyRepository For the repository requiring readable policy files
 */
final class PolicyFileUnreadableException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for unreadable policy file.
     *
     * Provides a static factory method that includes the problematic file path
     * in the error message to aid debugging of permission and I/O issues.
     *
     * @param  string $filePath The path to the file that exists but cannot be read
     * @return self   A new exception instance with file path in error message
     */
    public static function create(string $filePath): self
    {
        return new self(sprintf('Failed to read policy file: %s', $filePath));
    }
}
