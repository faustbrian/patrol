<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use function array_key_exists;

/**
 * Override function_exists for testing purposes.
 *
 * This allows us to test the fallback path in StorageFactory::getDefaultPath()
 * when storage_path() is not available.
 */
$GLOBALS['__test_function_exists_override'] = null;

/**
 * Test override for function_exists().
 *
 * When $GLOBALS['__test_function_exists_override'] is set to false,
 * this will make function_exists('storage_path') return false,
 * allowing us to test line 179 in StorageFactory.
 */
function function_exists(string $function): bool
{
    if (array_key_exists('__test_function_exists_override', $GLOBALS) && $function === 'storage_path') {
        return (bool) $GLOBALS['__test_function_exists_override'];
    }

    return \function_exists($function);
}
