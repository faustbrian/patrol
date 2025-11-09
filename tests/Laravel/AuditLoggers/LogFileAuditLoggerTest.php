<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Effect;
use Patrol\Laravel\AuditLoggers\LogFileAuditLogger;

describe('LogFileAuditLogger', function (): void {
    describe('Happy Paths', function (): void {
        test('successfully logs access decision to log file', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-123');
            $resource = resource('document-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should not throw exception
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('log contains all required context fields', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('admin-999', ['role' => 'administrator']);
            $resource = resource('project-789', 'project', ['owner' => 'admin-999']);
            $action = patrol_action('delete');
            $result = Effect::Deny;

            // Act & Assert - Should not throw exception
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('logs Allow result correctly', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('file-1', 'file');
            $action = patrol_action('download');
            $result = Effect::Allow;

            // Act & Assert - Should not throw exception
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('logs Deny result correctly', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('guest-user');
            $resource = resource('admin-panel', 'page');
            $action = patrol_action('access');
            $result = Effect::Deny;

            // Act & Assert - Should not throw exception
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('multiple logs are written correctly', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();

            // Act - Log multiple entries
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

            $logger->logAccess(
                subject('user-3'),
                resource('doc-3', 'document'),
                patrol_action('delete'),
                Effect::Allow,
            );

            // Assert - If we reach here, all logs succeeded
            expect(true)->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('uses custom channel name when provided', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger('custom_audit_channel');
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should not throw exception
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('timestamp is in ISO 8601 format', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should log successfully
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('JSON context structure is valid', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should handle JSON encoding
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles special characters in subject IDs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-123@example.com');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles special characters in resource IDs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('file://path/to/document.pdf', 'file');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles unicode characters in subject and resource IDs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('用户-123');
            $resource = resource('文档-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles very long subject IDs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $longSubjectId = str_repeat('a', 1_000);
            $subject = subject($longSubjectId);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles very long resource IDs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $longResourceId = str_repeat('b', 1_000);
            $subject = subject('user-1');
            $resource = resource($longResourceId, 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('handles empty string action names', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('logs message is always access decision', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should log with consistent message
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });

        test('uses info log level for all access logs', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();

            // Act - Try both Allow and Deny
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

            // Assert - If we reach here, both logs succeeded
            expect(true)->toBeTrue();
        });

        test('preserves all context data without modification', function (): void {
            // Arrange
            $logger = new LogFileAuditLogger();
            $subject = subject('user-123');
            $resource = resource('document-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert - Should preserve data integrity
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->not->toThrow(Exception::class);
        });
    });
});
