<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\AuditLoggers;

use Illuminate\Support\Facades\DB;
use Override;
use Patrol\Core\Contracts\AuditLoggerInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function now;

/**
 * Database-backed audit logger for recording authorization decisions.
 *
 * Persists audit records to a database table for compliance, security monitoring,
 * and forensic analysis. Each access attempt is recorded with subject, resource,
 * action, and the resulting effect (Allow/Deny), along with a timestamp.
 *
 * Typical usage in a service provider:
 * ```php
 * $auditLogger = new DatabaseAuditLogger(
 *     table: 'custom_audit_logs',
 *     connection: 'audit_db'
 * );
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DatabaseAuditLogger implements AuditLoggerInterface
{
    /**
     * Create a new database audit logger instance.
     *
     * @param string $table      The database table name where audit logs are stored. Should contain
     *                           columns: subject, resource, action, result, and created_at for
     *                           proper audit record storage and querying.
     * @param string $connection The Laravel database connection name to use for storing audit records.
     *                           Allows directing audit logs to a dedicated database separate from
     *                           application data for security and performance isolation.
     */
    public function __construct(
        private string $table = 'patrol_audit_logs',
        private string $connection = 'default',
    ) {}

    /**
     * Log an authorization access attempt to the database.
     *
     * Records who (subject) attempted what action on which resource and whether
     * it was allowed or denied. Useful for compliance auditing, security monitoring,
     * and investigating authorization failures.
     *
     * @param Subject  $subject  The user or entity that attempted the action
     * @param resource $resource The resource that was accessed or attempted to be accessed
     * @param Action   $action   The operation that was attempted (read, write, delete, etc.)
     * @param Effect   $result   The authorization decision (Allow or Deny) from policy evaluation
     */
    #[Override()]
    public function logAccess(
        Subject $subject,
        Resource $resource,
        Action $action,
        Effect $result,
    ): void {
        DB::connection($this->connection)
            ->table($this->table)
            ->insert([
                'subject' => $subject->id,
                'resource' => $resource->id,
                'action' => $action->name,
                'result' => $result->name,
                'created_at' => now(),
            ]);
    }
}
