<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

/**
 * Enumeration of file storage organization modes.
 *
 * Determines how policies and delegations are structured in file-based storage
 * systems. Each mode offers different trade-offs between file organization,
 * version control granularity, and operational complexity.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum FileMode: string
{
    /**
     * Single file containing all policies/delegations as array.
     *
     * All policies or delegations are stored in one file (e.g., policies.yaml)
     * as an array of entries. Simplifies version control with atomic commits
     * and reduces file system overhead. Suitable for small to medium data sets.
     *
     * Example: storage/patrol/policies/1.2.0/policies.json
     */
    case Single = 'single';

    /**
     * Individual file per policy/delegation.
     *
     * Each policy or delegation is stored in its own file named after its
     * identifier. Enables granular version control, parallel modifications,
     * and easier conflict resolution. Better for large data sets and teams.
     *
     * Example: storage/patrol/policies/1.2.0/admin-policy.json
     */
    case Multiple = 'multiple';
}
