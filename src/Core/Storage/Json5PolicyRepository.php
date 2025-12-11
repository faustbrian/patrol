<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use ColinODell\Json5\Json5Decoder;
use Exception;
use Override;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function is_array;
use function json_encode;

/**
 * JSON5 file-based policy repository.
 *
 * Uses colinodell/json5 for parsing JSON5 files with comments, trailing commas,
 * and unquoted keys. Provides human-friendly JSON editing while maintaining
 * standard JSON compatibility for output.
 *
 * JSON5 features:
 * - Single-line and multi-line comments
 * - Trailing commas in arrays and objects
 * - Unquoted object keys
 * - Single quotes for strings
 * - Multi-line strings
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see AbstractFilePolicyRepository For common policy operations
 */
final readonly class Json5PolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode JSON5 content to array.
     *
     * Parses JSON5-formatted policy data into a PHP array structure for processing.
     * Returns null on parse failures to signal invalid content. Supports all JSON5
     * features including comments and trailing commas.
     *
     * @param  string                                                                                                                      $content JSON5-formatted string containing policy definitions
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Parsed policy data or null if parsing fails
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $data = Json5Decoder::decode($content, true);

            if (!is_array($data)) {
                return null;
            }

            return $data; // @phpstan-ignore return.type
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to JSON content.
     *
     * Outputs standard JSON (not JSON5) for maximum compatibility. While we accept
     * JSON5 input for human editing, we write standard JSON to ensure universal
     * compatibility with all JSON parsers.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 JSON-formatted string representation
     */
    #[Override()]
    protected function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Get JSON5 file extension.
     *
     * Returns the file extension used for JSON5 policy files in this repository.
     * Uses .json5 extension to clearly indicate JSON5 format support.
     *
     * @return string File extension without leading dot
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'json5';
    }
}
