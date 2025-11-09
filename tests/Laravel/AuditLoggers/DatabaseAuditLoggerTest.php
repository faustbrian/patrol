<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Laravel\AuditLoggers\DatabaseAuditLogger;

describe('DatabaseAuditLogger', function (): void {
    beforeEach(function (): void {
        // Set up default database connection for the logger
        config(['database.connections.default' => config('database.connections.testing')]);

        // Create audit logs table for testing
        DB::connection('default')->getSchemaBuilder()->create('patrol_audit_logs', function ($table): void {
            $table->id();
            $table->string('subject');
            $table->string('resource');
            $table->string('action');
            $table->string('result');
            $table->timestamp('created_at');
        });
    });

    describe('Happy Paths', function (): void {
        test('successfully logs access decision to database', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-123');
            $resource = resource('document-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', [
                'subject' => 'user-123',
                'resource' => 'document-456',
                'action' => 'read',
                'result' => 'Allow',
            ], 'default');
        });

        test('logs with all fields populated including subject resource action and result', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('admin-999', ['role' => 'administrator']);
            $resource = resource('project-789', 'project', ['owner' => 'admin-999']);
            $action = patrol_action('delete');
            $result = Effect::Deny;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $record = DB::connection('default')->table('patrol_audit_logs')->first();

            expect($record)->not->toBeNull()
                ->and($record->subject)->toBe('admin-999')
                ->and($record->resource)->toBe('project-789')
                ->and($record->action)->toBe('delete')
                ->and($record->result)->toBe('Deny')
                ->and($record->created_at)->not->toBeNull();
        });

        test('logs Allow result correctly', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-1');
            $resource = resource('file-1', 'file');
            $action = patrol_action('download');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['result' => 'Allow'], 'default');
        });

        test('logs Deny result correctly', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('guest-user');
            $resource = resource('admin-panel', 'page');
            $action = patrol_action('access');
            $result = Effect::Deny;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['result' => 'Deny'], 'default');
        });

        test('multiple logs are inserted correctly', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();

            // Act
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

            // Assert
            expect(DB::connection('default')->table('patrol_audit_logs')->count())->toBe(3);
            $this->assertDatabaseHas('patrol_audit_logs', ['subject' => 'user-1', 'action' => 'read'], 'default');
            $this->assertDatabaseHas('patrol_audit_logs', ['subject' => 'user-2', 'action' => 'write'], 'default');
            $this->assertDatabaseHas('patrol_audit_logs', ['subject' => 'user-3', 'action' => 'delete'], 'default');
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when database connection fails', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger('patrol_audit_logs', 'nonexistent_connection');
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->toThrow(InvalidArgumentException::class);
        });

        test('throws exception when table does not exist', function (): void {
            // Arrange
            DB::connection('default')->getSchemaBuilder()->dropIfExists('patrol_audit_logs');
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act & Assert
            expect(fn () => $logger->logAccess($subject, $resource, $action, $result))
                ->toThrow(Exception::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('handles special characters in subject IDs', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-123@example.com');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['subject' => 'user-123@example.com'], 'default');
        });

        test('handles special characters in resource IDs', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-1');
            $resource = resource('file://path/to/document.pdf', 'file');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['resource' => 'file://path/to/document.pdf'], 'default');
        });

        test('handles very long subject IDs', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $longSubjectId = str_repeat('a', 255);
            $subject = subject($longSubjectId);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['subject' => $longSubjectId], 'default');
        });

        test('handles very long resource IDs', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $longResourceId = str_repeat('b', 255);
            $subject = subject('user-1');
            $resource = resource($longResourceId, 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['resource' => $longResourceId], 'default');
        });

        test('uses custom table name when provided', function (): void {
            // Arrange
            DB::connection('default')->getSchemaBuilder()->create('custom_audit_table', function ($table): void {
                $table->id();
                $table->string('subject');
                $table->string('resource');
                $table->string('action');
                $table->string('result');
                $table->timestamp('created_at');
            });

            $logger = new DatabaseAuditLogger('custom_audit_table');
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('custom_audit_table', [
                'subject' => 'user-1',
                'resource' => 'doc-1',
                'action' => 'read',
                'result' => 'Allow',
            ], 'default');
        });

        test('uses custom database connection when provided', function (): void {
            // Arrange
            config(['database.connections.audit_db' => config('database.connections.testing')]);

            DB::connection('audit_db')->getSchemaBuilder()->create('patrol_audit_logs', function ($table): void {
                $table->id();
                $table->string('subject');
                $table->string('resource');
                $table->string('action');
                $table->string('result');
                $table->timestamp('created_at');
            });

            $logger = new DatabaseAuditLogger('patrol_audit_logs', 'audit_db');
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $record = DB::connection('audit_db')->table('patrol_audit_logs')->first();
            expect($record)->not->toBeNull()
                ->and($record->subject)->toBe('user-1');
        });

        test('handles unicode characters in subject and resource IDs', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('用户-123');
            $resource = resource('文档-456', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', [
                'subject' => '用户-123',
                'resource' => '文档-456',
            ], 'default');
        });

        test('handles empty string action names', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('');
            $result = Effect::Allow;

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $this->assertDatabaseHas('patrol_audit_logs', ['action' => ''], 'default');
        });

        test('records timestamp at time of logging', function (): void {
            // Arrange
            $logger = new DatabaseAuditLogger();
            $subject = subject('user-1');
            $resource = resource('doc-1', 'document');
            $action = patrol_action('read');
            $result = Effect::Allow;
            $beforeLog = now();

            // Act
            $logger->logAccess($subject, $resource, $action, $result);

            // Assert
            $afterLog = now();
            $record = DB::connection('default')->table('patrol_audit_logs')->first();

            expect($record->created_at)
                ->toBeGreaterThanOrEqual($beforeLog->toDateTimeString())
                ->toBeLessThanOrEqual($afterLog->toDateTimeString());
        });
    });
});
