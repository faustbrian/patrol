<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Immutable value object representing rule evaluation priority.
 *
 * Defines the precedence order for policy rule evaluation when multiple rules
 * match an authorization request. Higher priority values are evaluated first,
 * allowing specific rules or explicit denials to override broader permissions.
 *
 * Priority patterns:
 * - Default rules: 1-10 (low priority, general permissions)
 * - Standard rules: 50 (medium priority, specific permissions)
 * - Override rules: 100+ (high priority, exceptions and explicit denials)
 * - Critical rules: 1000+ (emergency overrides, security policies)
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Priority
{
    /**
     * Create a new immutable priority value object.
     *
     * @param int $value The numeric priority level where higher values indicate greater
     *                   precedence during rule evaluation. Positive integers are typical,
     *                   with common ranges being 1-10 for defaults, 50-100 for standard
     *                   rules, and 100+ for high-priority overrides and security policies.
     */
    public function __construct(
        public int $value,
    ) {}

    /**
     * Determine if this priority is higher than another priority.
     *
     * Compares priority values to establish evaluation order, with higher values
     * taking precedence during policy decision-making.
     *
     * @param  self $other The priority to compare against
     * @return bool True if this priority value is numerically greater than the other
     */
    public function isHigherThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Determine if this priority is lower than another priority.
     *
     * Compares priority values to establish evaluation order, with lower values
     * being evaluated later during policy decision-making.
     *
     * @param  self $other The priority to compare against
     * @return bool True if this priority value is numerically less than the other
     */
    public function isLowerThan(self $other): bool
    {
        return $this->value < $other->value;
    }
}
