<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\AuditLoggers;

use Illuminate\Support\Facades\Log;
use Override;
use Patrol\Core\Contracts\AuditLoggerInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function now;

/**
 * Log file-based audit logger for recording authorization decisions.
 *
 * Writes audit records to Laravel log files using the configured logging channel.
 * Provides a lightweight alternative to database logging, useful for development,
 * debugging, or when centralized log aggregation systems are in place.
 *
 * Each access decision is logged with structured context including subject, resource,
 * action, result, and ISO 8601 timestamp for easy parsing by log analysis tools.
 *
 * ```php
 * $auditLogger = new LogFileAuditLogger(channel: 'security');
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class LogFileAuditLogger implements AuditLoggerInterface
{
    /**
     * Create a new log file audit logger instance.
     *
     * @param string $channel The Laravel logging channel to use for audit records. Should be
     *                        configured in config/logging.php. Using a dedicated channel allows
     *                        audit logs to be separated from application logs and routed to
     *                        specialized storage or monitoring systems.
     */
    public function __construct(
        private string $channel = 'patrol',
    ) {}

    /**
     * Log an authorization access attempt to the configured log channel.
     *
     * Writes a structured log entry with access decision details. The log message
     * includes the subject identifier, resource identifier, action name, and the
     * resulting effect (Allow/Deny) along with an ISO 8601 formatted timestamp.
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
        Log::channel($this->channel)->info('Access decision', [
            'subject' => $subject->id,
            'resource' => $resource->id,
            'action' => $action->name,
            'result' => $result->name,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
