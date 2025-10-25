<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Builders;

use LogicException;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

use function is_array;
use function throw_if;

/**
 * Fluent builder for Role-Based Access Control (RBAC) policies.
 *
 * Provides a semantic API for defining role-based permissions. Focuses on
 * assigning capabilities to roles rather than individual users.
 *
 * ```php
 * use Patrol\Laravel\Builders\RbacPolicyBuilder;
 *
 * $policy = RbacPolicyBuilder::make()
 *     ->role('admin')
 *         ->can('*')              // Admin can do everything
 *     ->role('editor')
 *         ->can(['read', 'write'])
 *         ->on('posts')
 *     ->role('viewer')
 *         ->can('read')
 *         ->onAny()
 *     ->build();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RbacPolicyBuilder
{
    /**
     * Collection of policy rules being built.
     *
     * @var array<int, PolicyRule>
     */
    private array $rules = [];

    /**
     * The current role being configured.
     */
    private ?string $currentRole = null;

    /**
     * The current resource being configured.
     */
    private ?string $currentResource = null;

    /**
     * The current priority level.
     */
    private int $currentPriority = 1;

    /**
     * Create a new RBAC policy builder.
     *
     * Factory method for creating an RbacPolicyBuilder with fluent interface support.
     * This is the primary entry point for building role-based policies programmatically.
     *
     * @return self A new RBAC policy builder instance ready for configuration
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Start configuring permissions for a role.
     *
     * Automatically prefixes the role identifier with 'role:' for consistency with
     * Patrol's subject identifier format. Resets resource and priority for the new role.
     *
     * @param  string $role Role identifier (e.g., 'admin', 'editor', 'viewer')
     * @return self   Fluent interface
     */
    public function role(string $role): self
    {
        $this->currentRole = 'role:'.$role;
        $this->currentResource = null; // Reset resource for new role
        $this->currentPriority = 1;    // Reset priority

        return $this;
    }

    /**
     * Specify resource(s) for the current role's permissions.
     *
     * @param  string $resource Resource identifier pattern (e.g., 'posts', 'api/users')
     * @return self   Fluent interface
     */
    public function on(string $resource): self
    {
        $this->currentResource = $resource;

        return $this;
    }

    /**
     * Allow permissions on any resource (wildcard).
     *
     * @return self Fluent interface
     */
    public function onAny(): self
    {
        $this->currentResource = '*';

        return $this;
    }

    /**
     * Set the priority for subsequent rules.
     *
     * @param  int  $priority Priority level (higher = evaluated first)
     * @return self Fluent interface
     */
    public function withPriority(int $priority): self
    {
        $this->currentPriority = $priority;

        return $this;
    }

    /**
     * Grant permissions to the current role.
     *
     * Accepts either a single action string or an array of action strings. Each action
     * generates a separate Allow rule with the current priority level.
     *
     * @param array<int, string>|string $actions Action(s) to allow (e.g., 'read', ['read', 'write'], '*')
     *
     * @throws LogicException If role() has not been called first
     *
     * @return self Fluent interface
     */
    public function can(array|string $actions): self
    {
        throw_if($this->currentRole === null, LogicException::class, 'Must call role() before can()');

        $actionList = is_array($actions) ? $actions : [$actions];

        foreach ($actionList as $action) {
            $this->rules[] = new PolicyRule(
                subject: $this->currentRole,
                resource: $this->currentResource,
                action: $action,
                effect: Effect::Allow,
                priority: new Priority($this->currentPriority),
            );
        }

        return $this;
    }

    /**
     * Deny permissions for the current role.
     *
     * Accepts either a single action string or an array of action strings. Each action
     * generates a separate Deny rule with the current priority level.
     *
     * @param array<int, string>|string $actions Action(s) to deny
     *
     * @throws LogicException If role() has not been called first
     *
     * @return self Fluent interface
     */
    public function cannot(array|string $actions): self
    {
        throw_if($this->currentRole === null, LogicException::class, 'Must call role() before cannot()');

        $actionList = is_array($actions) ? $actions : [$actions];

        foreach ($actionList as $action) {
            $this->rules[] = new PolicyRule(
                subject: $this->currentRole,
                resource: $this->currentResource,
                action: $action,
                effect: Effect::Deny,
                priority: new Priority($this->currentPriority),
            );
        }

        return $this;
    }

    /**
     * Grant full access (all actions on all resources) to the current role.
     *
     * Convenience method for superuser/admin roles. Sets resource to wildcard '*'
     * and grants permission for all actions. Uses high priority (100) by default
     * to ensure this rule takes precedence over more specific restrictions.
     *
     * @param int $priority Priority level. Defaults to 100 for high precedence.
     *
     * @throws LogicException If role() has not been called first
     *
     * @return self Fluent interface
     */
    public function fullAccess(int $priority = 100): self
    {
        throw_if($this->currentRole === null, LogicException::class, 'Must call role() before fullAccess()');

        $this->currentPriority = $priority;
        $this->currentResource = '*';

        return $this->can('*');
    }

    /**
     * Grant read-only access to the current role.
     *
     * @return self Fluent interface
     */
    public function readOnly(): self
    {
        return $this->can('read');
    }

    /**
     * Grant read and write access to the current role.
     *
     * Grants permissions for read, write, create, and update actions. Does not
     * include delete permission. Useful for editor or contributor roles.
     *
     * @return self Fluent interface
     */
    public function readWrite(): self
    {
        return $this->can(['read', 'write', 'create', 'update']);
    }

    /**
     * Build and return the final Policy object.
     *
     * @return Policy The constructed policy containing all configured rules
     */
    public function build(): Policy
    {
        return new Policy($this->rules);
    }
}
