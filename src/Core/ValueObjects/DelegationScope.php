<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use function array_any;
use function fnmatch;
use function in_array;

/**
 * Immutable value object defining the scope of permissions being delegated.
 *
 * Encapsulates the resource patterns, actions, and optional domain constraints
 * that bound what permissions a delegation grants to the delegate. Supports
 * wildcard matching for flexible delegation patterns while maintaining security
 * through explicit scope definition.
 *
 * Scope examples:
 * - All actions on specific resources: resources=['document:123'], actions=['*']
 * - Specific actions on resource pattern: resources=['document:*'], actions=['read', 'edit']
 * - Domain-scoped delegation: resources=['*'], actions=['read'], domain='tenant-x'
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DelegationScope
{
    /**
     * Create a new immutable delegation scope.
     *
     * @param array<string> $resources Resource patterns this delegation covers (e.g., 'document:*',
     *                                 'report:123'). Supports wildcards for pattern matching.
     *                                 Each pattern defines which resources the delegate can access
     *                                 using the delegated permissions.
     * @param array<string> $actions   Action patterns this delegation permits (e.g., 'read', 'edit',
     *                                 '*'). Defines which operations the delegate can perform on
     *                                 the scoped resources. Wildcards grant all actions within the
     *                                 resource constraints.
     * @param null|string   $domain    Optional domain context for multi-tenant scenarios. When set,
     *                                 restricts the delegation to only apply within the specified
     *                                 tenant or organizational boundary.
     */
    public function __construct(
        public array $resources,
        public array $actions,
        public ?string $domain = null,
    ) {}

    /**
     * Check if this scope matches a specific resource and action.
     *
     * Evaluates whether the given resource identifier and action fall within
     * the boundaries defined by this delegation scope. Uses wildcard pattern
     * matching to support flexible resource and action definitions while
     * maintaining explicit authorization boundaries.
     *
     * Matching logic:
     * - Exact match: 'document:123' matches 'document:123'
     * - Wildcard match: 'document:*' matches 'document:123'
     * - Action wildcard: '*' matches any action
     *
     * ```php
     * $scope = new DelegationScope(
     *     resources: ['document:*'],
     *     actions: ['read', 'edit']
     * );
     *
     * $scope->matches('document:123', 'read');  // true
     * $scope->matches('document:123', 'delete'); // false
     * $scope->matches('report:456', 'read');     // false
     * ```
     *
     * @param  string $resource The resource identifier being checked
     * @param  string $action   The action being checked
     * @return bool   True if the resource and action fall within this delegation scope
     */
    public function matches(string $resource, string $action): bool
    {
        $resourceMatches = false;

        foreach ($this->resources as $pattern) {
            if (fnmatch($pattern, $resource)) {
                $resourceMatches = true;

                break;
            }
        }

        if (!$resourceMatches) {
            return false;
        }

        // Check if action matches (supports wildcards)
        if (in_array('*', $this->actions, true)) {
            return true;
        }

        return array_any($this->actions, fn ($pattern): bool => fnmatch($pattern, $action));
    }
}
