<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Exception;
use Override;

use function is_array;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function unserialize;

/**
 * PHP serialized file-based policy repository.
 *
 * Uses PHP's native serialize/unserialize for maximum performance. Fastest
 * file-based option but not human-readable or portable to other languages.
 * Disallows object unserialization for security (allowed_classes => false).
 *
 * @see AbstractFilePolicyRepository For common policy operations
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SerializedPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode serialized content to array.
     *
     * Unserializes with allowed_classes => false for security. Suppresses
     * warnings during unserialization and returns null on any errors to enable
     * graceful degradation in multi-file mode.
     *
     * @param  string                                                                                                                      $content Raw serialized string to decode
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Decoded data or null on error
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            // Temporarily suppress unserialize warnings for invalid data
            set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): bool => true);
            $data = unserialize($content, ['allowed_classes' => false]);
            restore_error_handler();

            if (!is_array($data)) {
                return null;
            }

            /** @var null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data @phpstan-ignore varTag.nativeType (asserting correct array shape after unserialize) */
            return $data;
            // @codeCoverageIgnoreStart
        } catch (Exception) {
            restore_error_handler();

            return null;
        }

        // @codeCoverageIgnoreEnd
    }

    /**
     * Encode array to serialized content.
     *
     * Uses PHP's native serialize() for compact, performant storage. Output
     * is binary-safe but not human-readable.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 Serialized binary string
     */
    #[Override()]
    protected function encode(array $data): string
    {
        return serialize($data);
    }

    /**
     * Get serialized file extension.
     *
     * @return string Always returns 'ser' for serialized files
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'ser';
    }
}
