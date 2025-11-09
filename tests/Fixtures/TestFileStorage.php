<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Patrol\Core\Storage\FileStorageBase;

/**
 * Test double for FileStorageBase that exposes protected methods.
 *
 * Used in tests to verify versioning, path building, and other
 * base storage functionality without needing concrete implementations.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TestFileStorage extends FileStorageBase
{
    public function exposedResolveVersion(string $type): ?string
    {
        return $this->resolveVersion($type);
    }

    public function exposedDetectLatestVersion(string $directory): ?string
    {
        return $this->detectLatestVersion($directory);
    }

    public function exposedBuildPath(string $type, string $identifier, string $extension): string
    {
        return $this->buildPath($type, $identifier, $extension);
    }

    public function exposedCreateNewVersion(string $type, string $bumpType = 'patch'): ?string
    {
        return $this->createNewVersion($type, $bumpType);
    }
}
