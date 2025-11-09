<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Illuminate\Support\Facades\File;
use JsonException;
use Override;
use Patrol\Core\Exceptions\InvalidPolicyFileFormatException;
use Patrol\Core\Exceptions\PolicyFileNotFoundException;
use Patrol\Core\Exceptions\PolicyFileUnreadableException;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function array_key_exists;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;
use function throw_if;
use function throw_unless;

/**
 * JSON file-based policy repository with versioning and caching.
 *
 * Stores policies as JSON files with support for single-file or multi-file modes,
 * semantic versioning, and in-memory caching. Uses Symfony-style dump/parse pattern
 * for serialization. Throws exceptions for missing or invalid files in single-file mode.
 *
 * @see FileStorageBase For versioning and path management
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class JsonPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Load all policies with strict validation.
     *
     * Overrides parent to add strict validation in single-file mode. Throws
     * exceptions for missing or invalid files instead of returning empty arrays.
     * Multi-file mode maintains graceful degradation behavior.
     *
     * @throws InvalidPolicyFileFormatException If single file contains invalid JSON
     * @throws PolicyFileNotFoundException      If single file doesn't exist
     * @throws PolicyFileUnreadableException    If single file can't be read
     *
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Policy data structures
     */
    #[Override()]
    protected function loadPolicies(): array
    {
        $cacheKey = $this->getCacheKey();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey]; // @phpstan-ignore return.type
        }

        if ($this->fileMode === FileMode::Single) {
            return $this->loadSingleFileStrict();
        }

        return $this->loadMultipleFiles();
    }

    /**
     * Decode JSON content to array.
     *
     * Returns null on invalid JSON to enable graceful handling in multi-file
     * mode. Parent class skips files that fail to decode.
     *
     * @param  string                                                                                                                      $content Raw JSON string to decode
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Decoded data or null on error
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        $data = json_decode($content, true);

        if (!is_array($data)) {
            return null;
        }

        return $data; // @phpstan-ignore return.type
    }

    /**
     * Encode array to JSON content.
     *
     * Formats with pretty-print for human readability, unescaped slashes for
     * cleaner resource paths, and throws on errors for early detection.
     *
     * @param array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     *
     * @throws JsonException If encoding fails
     *
     * @return string Formatted JSON string
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
     * Get JSON file extension.
     *
     * @return string Always returns 'json'
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'json';
    }

    /**
     * Load from single file with strict validation.
     *
     * Unlike parent's loadSingleFile(), this throws exceptions for missing or
     * invalid files instead of returning empty arrays. Ensures configuration
     * errors are caught early rather than silently ignored.
     *
     * @throws InvalidPolicyFileFormatException If JSON is invalid
     * @throws PolicyFileNotFoundException      If file doesn't exist
     * @throws PolicyFileUnreadableException    If file can't be read
     *
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Policy data
     */
    private function loadSingleFileStrict(): array
    {
        $filePath = $this->buildPath('policies', 'policies', $this->getExtension());

        throw_unless(File::exists($filePath), PolicyFileNotFoundException::create($filePath));

        $content = File::get($filePath);
        // @phpstan-ignore-next-line identical.alwaysFalse - File::get() can actually return false on read errors
        throw_if($content === false, PolicyFileUnreadableException::create($filePath));

        $data = json_decode($content, true);
        throw_unless(is_array($data), InvalidPolicyFileFormatException::create($filePath));

        /** @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data */
        /** @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> */
        return $data;
    }

    /**
     * Generate cache key for policy storage.
     *
     * Combines version and file mode to ensure cache invalidation when
     * configuration changes or version switches occur.
     *
     * @return string Unique cache identifier
     */
    private function getCacheKey(): string
    {
        return sprintf(
            'policies:%s:%s',
            $this->resolveVersion('policies') ?? 'latest',
            $this->fileMode->value,
        );
    }
}
