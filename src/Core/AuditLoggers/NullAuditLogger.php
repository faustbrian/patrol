<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\AuditLoggers;

use Override;
use Patrol\Core\Contracts\AuditLoggerInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

/**
 * No-operation audit logger implementation for when audit logging is disabled.
 *
 * Provides a null object pattern implementation of the audit logger interface,
 * allowing the authorization system to operate without audit logging overhead
 * when logging is not required. This implementation performs no operations and
 * incurs minimal performance cost, making it ideal for development environments
 * or systems where access logging is handled by external infrastructure.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see AuditLoggerInterface For the audit logger contract
 */
final readonly class NullAuditLogger implements AuditLoggerInterface
{
    /**
     * No-operation implementation that silently discards access log events.
     *
     * Accepts all required audit logging parameters but performs no logging
     * operations, providing a zero-overhead audit logging implementation when
     * access tracking is disabled or handled by external systems.
     *
     * @param Subject  $subject  The subject (user, role, service) that attempted access
     * @param resource $resource The resource that was accessed or acted upon
     * @param Action   $action   The action that was performed on the resource
     * @param Effect   $result   The authorization decision (Allow or Deny)
     */
    #[Override()]
    public function logAccess(
        Subject $subject,
        Resource $resource,
        Action $action,
        Effect $result,
    ): void {
        // No-op: intentionally empty for null object pattern
    }
}
