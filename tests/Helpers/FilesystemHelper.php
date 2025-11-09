<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function is_dir;
use function rmdir;

/**
 * Helper functions for filesystem operations in tests.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FilesystemHelper
{
    /**
     * Recursively delete a directory and all its contents.
     *
     * Safely removes all files and subdirectories without emitting warnings
     * when encountering special files or permission issues.
     *
     * @param string $directory Directory path to delete
     */
    public static function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $func = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $func($fileinfo->getRealPath());
        }

        rmdir($directory);
    }
}
