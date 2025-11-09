<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Composer\Semver\Semver;
use Illuminate\Support\Facades\File;
use Patrol\Core\Exceptions\StorageVersionNotFoundException;
use Patrol\Core\ValueObjects\FileMode;

use function array_filter;
use function explode;
use function implode;
use function scandir;
use function sprintf;
use function throw_unless;

/**
 * Base functionality for file-based storage with versioning support.
 *
 * Provides common operations for file-based storage implementations including
 * version resolution, directory management, and path construction. Handles
 * semantic versioning with automatic latest version detection.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract readonly class FileStorageBase
{
    /**
     * Create a new file storage instance.
     *
     * @param string               $basePath          Base storage directory path
     * @param FileMode             $fileMode          File organization mode (single/multiple)
     * @param null|string          $version           Specific version or null for latest
     * @param bool                 $versioningEnabled Whether versioning is active
     * @param array<string, mixed> $cache             In-memory cache for file contents
     */
    public function __construct(
        protected string $basePath,
        protected FileMode $fileMode,
        protected ?string $version = null,
        protected bool $versioningEnabled = true,
        protected array $cache = [],
    ) {}

    /**
     * Resolve the active version to use.
     *
     * If version is explicitly set, validates it exists. Otherwise, auto-detects
     * the highest semantic version in the storage directory. Returns null if
     * versioning is disabled.
     *
     * @param string $type Storage type (policies/delegations)
     *
     * @throws StorageVersionNotFoundException If specified version doesn't exist
     *
     * @return null|string Resolved version or null
     */
    protected function resolveVersion(string $type): ?string
    {
        if (!$this->versioningEnabled) {
            return null;
        }

        $typeDir = sprintf('%s/%s', $this->basePath, $type);

        // If version specified, validate it exists
        if ($this->version !== null) {
            $versionDir = sprintf('%s/%s', $typeDir, $this->version);

            throw_unless(File::isDirectory($versionDir), StorageVersionNotFoundException::create($this->version, $type));

            return $this->version;
        }

        // Auto-detect latest version
        return $this->detectLatestVersion($typeDir);
    }

    /**
     * Detect the highest semantic version in directory.
     *
     * Scans directory for subdirectories with valid semantic version names,
     * filters out invalid entries and non-directories, then returns the highest
     * version according to semver rules using rsort comparison.
     *
     * @param  string      $directory Base directory to scan for version subdirectories
     * @return null|string Latest semantic version or null if no valid versions found
     */
    protected function detectLatestVersion(string $directory): ?string
    {
        if (!File::isDirectory($directory)) {
            return null;
        }

        $entries = scandir($directory);

        // @codeCoverageIgnoreStart
        if ($entries === false) {
            return null; // @codeCoverageIgnoreEnd
        }

        // Filter to valid semantic versions only
        $versions = array_filter($entries, static function (string $entry) use ($directory): bool {
            if ($entry === '.' || $entry === '..') {
                return false;
            }

            $path = sprintf('%s/%s', $directory, $entry);

            if (!File::isDirectory($path)) {
                return false;
            }

            // Validate semantic version format
            return Semver::satisfies($entry, '*');
        });

        if ($versions === []) {
            return null;
        }

        // Return highest version
        return Semver::rsort($versions)[0] ?? null;
    }

    /**
     * Build file path for storage operation.
     *
     * Constructs full path including versioning directory if enabled, type
     * subdirectory, and filename based on file mode. In single-file mode, uses
     * type as filename. In multi-file mode, uses identifier as filename.
     *
     * @param  string $type       Storage type (e.g., 'policies', 'delegations')
     * @param  string $identifier Item identifier for multi-file mode filename
     * @param  string $extension  File extension without leading dot
     * @return string Absolute file path for read/write operations
     */
    protected function buildPath(string $type, string $identifier, string $extension): string
    {
        $parts = [$this->basePath, $type];

        // Add version directory if versioning enabled
        if ($this->versioningEnabled) {
            $version = $this->resolveVersion($type);

            if ($version !== null) {
                $parts[] = $version;
            }
        }

        // Add filename based on mode
        if ($this->fileMode === FileMode::Single) {
            $parts[] = sprintf('%s.%s', $type, $extension);
        } else {
            $parts[] = sprintf('%s.%s', $identifier, $extension);
        }

        return implode('/', $parts);
    }

    /**
     * Create new version directory for writes.
     *
     * When versioning is enabled, creates a new version directory by bumping
     * the current latest version according to semantic versioning rules. Defaults
     * to patch bumps. Creates directory with 0755 permissions if it doesn't exist.
     *
     * @param  string      $type     Storage type (e.g., 'policies', 'delegations')
     * @param  string      $bumpType Semantic version bump type: 'major', 'minor', or 'patch'
     * @return null|string New version identifier or null if versioning disabled
     */
    protected function createNewVersion(string $type, string $bumpType = 'patch'): ?string
    {
        if (!$this->versioningEnabled) {
            return null;
        }

        $typeDir = sprintf('%s/%s', $this->basePath, $type);
        $currentVersion = $this->detectLatestVersion($typeDir) ?? '0.0.0';

        $newVersion = match ($bumpType) {
            'major' => Semver::satisfies($currentVersion, '0.0.0') ? '1.0.0' : Semver::sort([$currentVersion, '0.0.0'])[1],
            'minor' => Semver::satisfies($currentVersion, '0.0.0') ? '0.1.0' : $currentVersion,
            default => Semver::satisfies($currentVersion, '0.0.0') ? '0.0.1' : $currentVersion,
        };

        // Bump version appropriately
        [$major, $minor, $patch] = explode('.', $newVersion);

        $newVersion = match ($bumpType) {
            'major' => sprintf('%d.0.0', (int) $major + 1),
            'minor' => sprintf('%s.%d.0', $major, (int) $minor + 1),
            default => sprintf('%s.%s.%d', $major, $minor, (int) $patch + 1),
        };

        $newDir = sprintf('%s/%s', $typeDir, $newVersion);

        if (!File::isDirectory($newDir)) {
            File::makeDirectory($newDir, 0o755, true);
        }

        return $newVersion;
    }
}
