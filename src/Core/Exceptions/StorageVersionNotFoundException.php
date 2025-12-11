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
 * Thrown when attempting to load a non-existent storage version.
 *
 * This exception indicates that the requested version identifier does not exist
 * in the file-based storage system for the specified storage type. Version-based
 * storage allows maintaining multiple versions of policies or delegations for
 * versioning, rollback, or A/B testing scenarios.
 *
 * The storage system typically organizes versions in directories or files with
 * version identifiers, and this exception is thrown when a requested version
 * cannot be found during load operations.
 *
 * Common causes:
 * - Requesting a version that was never created
 * - Typographical error in version identifier
 * - Version deleted or not yet migrated
 * - Incorrect storage path configuration
 *
 * Resolution:
 * - Verify the version identifier is correct
 * - List available versions using storage directory scanning
 * - Ensure the version was properly saved before attempting to load
 * - Check storage path configuration points to correct location
 *
 * @author Brian Faust <brian@cline.sh>
 * @see VersionedPolicyStorage For version management implementation
 */
final class StorageVersionNotFoundException extends InvalidArgumentException
{
    /**
     * Create a new exception for missing storage version.
     *
     * Provides a static factory method that includes both the requested version
     * identifier and storage type in the error message, along with guidance for
     * discovering available versions through directory scanning.
     *
     * @param  string $version The requested version identifier that was not found (e.g., "v1.0", "2024-01-15")
     * @param  string $type    The storage type being accessed (e.g., "policies", "delegations")
     * @return self   A new exception instance with detailed error message and resolution guidance
     */
    public static function create(string $version, string $type): self
    {
        return new self(
            sprintf(
                'Storage version "%s" not found for type "%s". Available versions can be detected by scanning the storage directory.',
                $version,
                $type,
            ),
        );
    }
}
