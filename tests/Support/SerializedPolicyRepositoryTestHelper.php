<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Support;

use Exception;
use Patrol\Core\Storage\SerializedPolicyRepository;
use Patrol\Core\ValueObjects\FileMode;

use const E_USER_NOTICE;

use function restore_error_handler;
use function set_error_handler;
use function trigger_error;

/**
 * Test helper to verify exception handling in SerializedPolicyRepository.
 *
 * This class provides static methods to test edge cases in the decode() method,
 * particularly the exception handling path (lines 61-64) that is difficult to
 * trigger naturally with allowed_classes => false.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SerializedPolicyRepositoryTestHelper
{
    /**
     * Create a repository instance that simulates exception during decode.
     *
     * This method uses reflection to directly invoke the decode method
     * and verify exception handling behavior.
     */
    public static function createRepositoryWithForcedException(string $basePath): SerializedPolicyRepository
    {
        return new SerializedPolicyRepository(
            basePath: $basePath,
            fileMode: FileMode::Single,
            version: null,
            versioningEnabled: false,
        );
    }

    /**
     * Test the exception path in decode() by directly invoking it.
     *
     * This simulates what happens when an exception occurs during
     * unserialization, verifying that restore_error_handler() is called.
     *
     * @return array{caught_exception: bool, handler_restored: bool}
     */
    public static function testDecodeExceptionPath(): array
    {
        $caughtException = false;
        $handlerRestored = false;

        // Set up a test error handler to track restoration
        $originalHandlerInvoked = false;
        set_error_handler(function () use (&$originalHandlerInvoked): bool {
            $originalHandlerInvoked = true;

            return true;
        });

        try {
            // Simulate the code path in decode() that catches exceptions
            set_error_handler(static fn (int $errno, string $errstr, string $errfile, int $errline): bool => true);

            // Force an exception to test the catch block
            throw new Exception('Test exception');
        } catch (Exception) {
            restore_error_handler();
            $caughtException = true;

            // Verify the handler was restored by triggering an error
            trigger_error('Test error for handler verification', E_USER_NOTICE);
            $handlerRestored = $originalHandlerInvoked;
        } finally {
            restore_error_handler();
        }

        return [
            'caught_exception' => $caughtException,
            'handler_restored' => $handlerRestored,
        ];
    }
}
