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
 * Thrown when an invalid primary key type is configured for Patrol database tables.
 *
 * This exception indicates that the configured primary key type does not match
 * any of the supported types (autoincrement, uuid, ulid). The configuration is
 * validated during service provider boot to ensure migrations will execute
 * successfully before any database operations are attempted.
 *
 * The exception provides clear guidance on which value was invalid and lists
 * all supported options to aid in correcting the configuration.
 *
 * Common causes:
 * - Typographical error in config value
 * - Using unsupported key type from previous versions
 * - Incorrect environment variable value
 *
 * @see PrimaryKeyType For the enum defining valid primary key types
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPrimaryKeyTypeException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for invalid primary key type.
     *
     * Provides a static factory method that includes both the invalid value
     * and the list of supported types in the error message to aid debugging.
     *
     * @param  string        $invalidType    The invalid primary key type that was configured
     * @param  array<string> $supportedTypes All valid primary key type values
     * @return self          A new exception instance with detailed error message
     */
    public static function create(string $invalidType, array $supportedTypes): self
    {
        return new self(
            sprintf(
                'Invalid primary key type "%s" configured for Patrol. Supported types: %s',
                $invalidType,
                implode(', ', $supportedTypes),
            ),
        );
    }
}
