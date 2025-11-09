<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\AuditLoggers\NullAuditLogger;
use Patrol\Core\ValueObjects\Effect;

describe('NullAuditLogger', function (): void {
    describe('Happy Paths', function (): void {
        test('does not throw exception when called', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-123');
            $resource = resource('document-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('can be called multiple times without side effects', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Call multiple times
            $logger->logAccess($subject, $resource, $action, $result);
            $logger->logAccess($subject, $resource, $action, $result);
            $logger->logAccess($subject, $resource, $action, $result);

            // If we reach here without exceptions, the test passes
            expect(true)->toBeTrue();
        });

        test('accepts Allow result without side effects', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert - No exception thrown
            expect(true)->toBeTrue();
        });

        test('accepts Deny result without side effects', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Deny;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert - No exception thrown
            expect(true)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('truly performs no operation with no I/O', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act - Capture any potential output
            ob_start();
            $logger->logAccess($subject, $resource, $action, $result);
            $output = ob_get_clean();

            // Assert - No output produced
            expect($output)->toBe('');
        });

        test('handles special characters in subject IDs without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-123@example.com');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles special characters in resource IDs without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('file://path/to/document.pdf', 'file');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles unicode characters without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('用户-123');
            $resource = resource('文档-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles very long subject IDs without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $longSubjectId = str_repeat('a', 10_000);
            $subject = subject($longSubjectId);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles very long resource IDs without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $longResourceId = str_repeat('b', 10_000);
            $subject = subject('user-1');
            $resource = resource($longResourceId, 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles empty string action names without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles complex subject attributes without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1', [
                'role' => 'admin',
                'department' => 'engineering',
                'permissions' => ['read', 'write', 'delete'],
                'metadata' => [
                    'created_at' => '2023-01-01',
                    'updated_at' => '2023-12-31',
                ],
            ]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles complex resource attributes without errors', function (): void {
            // Arrange
            $logger = new NullAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', [
                'owner' => 'user-2',
                'department' => 'sales',
                'tags' => ['important', 'confidential'],
                'metadata' => [
                    'size' => 1_024,
                    'format' => 'pdf',
                ],
            ]);
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('executes in constant time regardless of input size', function (): void {
            // Arrange
            $logger = new NullAuditLogger();

            // Small inputs
            $smallSubject = subject('u');
            $smallResource = resource('d', 'doc');
            $smallAction = patrol_action('r');

            // Large inputs
            $largeSubject = subject(str_repeat('user-', 1_000), array_fill(0, 100, 'value'));
            $largeResource = resource(str_repeat('doc-', 1_000), 'document', array_fill(0, 100, 'value'));
            $largeAction = patrol_action(str_repeat('action-', 100));

            // Act - Measure execution times
            $startSmall = microtime(true);

            for ($i = 0; $i < 1_000; ++$i) {
                $logger->logAccess($smallSubject, $smallResource, $smallAction, Effect::Allow);
            }

            $timeSmall = microtime(true) - $startSmall;

            $startLarge = microtime(true);

            for ($i = 0; $i < 1_000; ++$i) {
                $logger->logAccess($largeSubject, $largeResource, $largeAction, Effect::Allow);
            }

            $timeLarge = microtime(true) - $startLarge;

            // Assert - Execution times should be similar (within reasonable variance)
            // Since it's a no-op, large inputs shouldn't take significantly longer
            expect($timeLarge)->toBeLessThan($timeSmall * 2); // Allow 2x variance for JIT/caching
        });

        test('maintains no state across multiple invocations', function (): void {
            // Arrange
            $logger = new NullAuditLogger();

            // Act - Call with different parameters
            $logger->logAccess(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
                Effect::Allow,
            );

            $logger->logAccess(
                subject('user-2'),
                resource('doc-2', 'document'),
                patrol_action('write'),
                Effect::Deny,
            );

            // Assert - Logger should still be functional (no state corruption)
            expect(fn () => $logger->logAccess(
                subject('user-3'),
                resource('doc-3', 'document'),
                patrol_action('delete'),
                Effect::Allow,
            ))->not->toThrow(Exception::class);
        });

        test('can be instantiated multiple times without interference', function (): void {
            // Arrange
            $logger1 = new NullAuditLogger();
            $logger2 = new NullAuditLogger();
            $logger3 = new NullAuditLogger();

            // Act & Assert - All instances should work independently
            expect(fn () => $logger1->logAccess(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
                Effect::Allow,
            ))->not->toThrow(Exception::class);

            expect(fn () => $logger2->logAccess(
                subject('user-2'),
                resource('doc-2', 'document'),
                patrol_action('write'),
                Effect::Deny,
            ))->not->toThrow(Exception::class);

            expect(fn () => $logger3->logAccess(
                subject('user-3'),
                resource('doc-3', 'document'),
                patrol_action('delete'),
                Effect::Allow,
            ))->not->toThrow(Exception::class);
        });
    });
});
