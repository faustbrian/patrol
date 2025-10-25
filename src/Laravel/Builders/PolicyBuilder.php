<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Builders;

use InvalidArgumentException;
use Patrol\Core\Exceptions\ActionNotSetException;
use Patrol\Core\Exceptions\EffectNotSetException;
use Patrol\Core\Exceptions\SubjectNotSetException;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

use function throw_if;
use function throw_unless;

/**
 * Fluent builder for constructing authorization policies.
 *
 * Provides an expressive, chainable API for building complex authorization policies
 * with support for subjects, resources, actions, priorities, and multi-tenant domains.
 * Enforces validation to ensure all required components are present before creating rules.
 *
 * ```php
 * $policy = PolicyBuilder::make()
 *     ->for('user:123')
 *     ->on('document:456')
 *     ->allow('read')
 *     ->withPriority(10)
 *     ->for('user:123')
 *     ->deny('delete')
 *     ->inDomain('tenant-a')
 *     ->build();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PolicyBuilder
{
    /**
     * Collection of policy rules being built.
     *
     * @var array<int, PolicyRule>
     */
    private array $rules = [];

    /**
     * The current subject identifier being configured.
     */
    private ?string $currentSubject = null;

    /**
     * The current resource identifier being configured.
     */
    private ?string $currentResource = null;

    /**
     * The current action being configured.
     */
    private ?string $currentAction = null;

    /**
     * The current effect (Allow/Deny) being configured.
     */
    private ?Effect $currentEffect = null;

    /**
     * The current priority level being configured.
     */
    private ?Priority $currentPriority = null;

    /**
     * The current domain/tenant identifier being configured.
     */
    private ?Domain $currentDomain = null;

    /**
     * Create a new policy builder instance.
     *
     * Factory method for creating a PolicyBuilder with fluent interface support.
     * This is the primary entry point for building policies programmatically.
     *
     * @return self A new policy builder instance ready for configuration
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Set the subject (user/role/entity) for subsequent rules.
     *
     * The subject can be a user ID, role name, or any identifier that represents
     * the actor performing actions. Call this method before adding allow/deny rules.
     *
     * @param  string $subject The subject identifier (e.g., 'user:123', 'role:admin', '*')
     * @return self   Fluent interface
     */
    public function for(string $subject): self
    {
        $this->currentSubject = $subject;

        return $this;
    }

    /**
     * Set the resource for subsequent rules.
     *
     * The resource represents the entity being accessed or modified. Use null to
     * create resource-agnostic rules that apply to any resource.
     *
     * @param  null|string $resource The resource identifier (e.g., 'document:456', 'api:users', '*')
     * @return self        Fluent interface
     */
    public function on(?string $resource): self
    {
        $this->currentResource = $resource;

        return $this;
    }

    /**
     * Add an Allow rule for the specified action.
     *
     * Creates a policy rule granting permission for the current subject to perform
     * the action on the current resource. The rule is immediately added to the policy.
     *
     * @param string $action The action to allow (e.g., 'read', 'write', 'delete')
     *
     * @throws InvalidArgumentException if subject has not been set with for()
     *
     * @return self Fluent interface
     */
    public function allow(string $action): self
    {
        $this->currentAction = $action;
        $this->currentEffect = Effect::Allow;
        $this->addRule();

        return $this;
    }

    /**
     * Add a Deny rule for the specified action.
     *
     * Creates a policy rule denying permission for the current subject to perform
     * the action on the current resource. Deny rules can override Allow rules when
     * using appropriate priority levels.
     *
     * @param string $action The action to deny (e.g., 'read', 'write', 'delete')
     *
     * @throws InvalidArgumentException if subject has not been set with for()
     *
     * @return self Fluent interface
     */
    public function deny(string $action): self
    {
        $this->currentAction = $action;
        $this->currentEffect = Effect::Deny;
        $this->addRule();

        return $this;
    }

    /**
     * Set the priority level for the next rule.
     *
     * Priority determines the order of rule evaluation. Higher priority rules are
     * evaluated first. Useful for creating override rules or ensuring specific
     * rules take precedence. Defaults to priority 1 if not specified.
     *
     * @param  int  $priority The priority level (higher values = higher priority)
     * @return self Fluent interface
     */
    public function withPriority(int $priority): self
    {
        $this->currentPriority = new Priority($priority);

        return $this;
    }

    /**
     * Set the domain/tenant scope for the next rule.
     *
     * Limits the rule to a specific domain or tenant in multi-tenant applications.
     * Only subjects within the same domain will match this rule during evaluation.
     *
     * @param  string $domain The domain/tenant identifier (e.g., 'tenant-a', 'organization-123')
     * @return self   Fluent interface
     */
    public function inDomain(string $domain): self
    {
        $this->currentDomain = new Domain($domain);

        return $this;
    }

    /**
     * Build and return the final Policy object.
     *
     * Constructs a Policy value object from all the rules that have been added.
     * The policy can then be evaluated by the PolicyEvaluator.
     *
     * @return Policy The constructed policy containing all configured rules
     */
    public function build(): Policy
    {
        return new Policy($this->rules);
    }

    /**
     * Add the current rule configuration to the rules collection.
     *
     * Validates that subject, action, and effect are set, then creates a PolicyRule
     * and adds it to the collection. Resets action, effect, priority, and domain
     * for the next rule while preserving subject and resource for chaining.
     *
     * @throws InvalidArgumentException if subject, action, or effect is not set
     */
    private function addRule(): void
    {
        throw_if($this->currentSubject === null, SubjectNotSetException::create());

        throw_if(
            $this->currentAction === null,
            ActionNotSetException::withContext($this->currentSubject, $this->currentResource),
        );

        throw_unless(
            $this->currentEffect instanceof Effect,
            EffectNotSetException::withContext($this->currentSubject, $this->currentResource),
        );

        $this->rules[] = new PolicyRule(
            subject: $this->currentSubject,
            resource: $this->currentResource,
            action: $this->currentAction,
            effect: $this->currentEffect,
            priority: $this->currentPriority ?? new Priority(1),
            domain: $this->currentDomain,
        );

        $this->currentAction = null;
        $this->currentEffect = null;
        $this->currentPriority = null;
        $this->currentDomain = null;
    }
}
