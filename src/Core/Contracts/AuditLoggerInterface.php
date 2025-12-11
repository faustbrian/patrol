<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Contracts;

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * Records authorization decisions for security auditing and compliance tracking.
 *
 * Implementations capture access control events to persistent storage (database,
 * log files, external audit services) for security monitoring, compliance reporting,
 * and forensic analysis. The interface enables pluggable audit logging strategies
 * while maintaining a consistent contract for the authorization system.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see NullAuditLogger For a no-operation implementation when logging is disabled
 */
interface AuditLoggerInterface
{
    /**
     * Record an authorization decision to the audit log.
     *
     * Captures the complete authorization context including the subject attempting
     * access, the target resource, the action performed, and the resulting decision.
     * Implementations should persist this information with timestamps and additional
     * context (IP address, session ID, user agent) for comprehensive audit trails.
     *
     * @param Subject  $subject  The subject (user, role, service) that attempted access
     * @param resource $resource The resource that was accessed or acted upon
     * @param Action   $action   The action that was attempted on the resource
     * @param Effect   $result   The authorization decision (Allow or Deny)
     */
    public function logAccess(
        Subject $subject,
        Resource $resource,
        Action $action,
        Effect $result,
    ): void;
}
