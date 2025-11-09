<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Builders;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

/**
 * Fluent builder for CRUD (Create, Read, Update, Delete) authorization policies.
 *
 * Provides semantic methods for building standard CRUD permission patterns.
 * Uses action names that map to common application operations rather than
 * HTTP verbs.
 *
 * ```php
 * use Patrol\Laravel\Builders\CrudPolicyBuilder;
 *
 * $policy = CrudPolicyBuilder::for('posts')
 *     ->allowReadFor('*')                   // Anyone can read
 *     ->allowCreateFor('role:contributor')  // Contributors can create
 *     ->allowUpdateFor('role:editor')       // Editors can update
 *     ->allowDeleteFor('role:admin')        // Only admins can delete
 *     ->build();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CrudPolicyBuilder
{
    /**
     * Collection of policy rules being built.
     *
     * @var array<int, PolicyRule>
     */
    private array $rules = [];

    /**
     * Create a new CRUD policy builder for a resource.
     *
     * @param string $resource Resource identifier pattern that will be used for all policy rules
     *                         created by this builder. Typically represents a resource type like
     *                         'posts', 'documents', or 'users'. Can include wildcards for broader
     *                         matching patterns.
     */
    private function __construct(
        private readonly string $resource,
    ) {}

    /**
     * Create a new CRUD policy builder for a resource.
     *
     * @param  string $resource Resource identifier pattern
     * @return self   New builder instance
     */
    public static function for(string $resource): self
    {
        return new self($resource);
    }

    /**
     * Allow create action for a subject.
     *
     * @param  string $subject  Subject identifier (e.g., 'role:contributor', 'user:123')
     * @param  int    $priority Rule priority (higher = evaluated first). Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowCreateFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'create',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny create action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyCreateFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'create',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow read action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowReadFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'read',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny read action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyReadFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'read',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow update action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowUpdateFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'update',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny update action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyUpdateFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'update',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow delete action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowDeleteFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'delete',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny delete action for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyDeleteFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'delete',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow all CRUD operations for a subject (full access).
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowAllFor(string $subject, int $priority = 1): self
    {
        return $this
            ->allowCreateFor($subject, $priority)
            ->allowReadFor($subject, $priority)
            ->allowUpdateFor($subject, $priority)
            ->allowDeleteFor($subject, $priority);
    }

    /**
     * Allow read-only access for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowReadOnlyFor(string $subject, int $priority = 1): self
    {
        return $this->allowReadFor($subject, $priority);
    }

    /**
     * Allow read and write access (read, create, update) for a subject.
     * Excludes delete permission.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowReadWriteFor(string $subject, int $priority = 1): self
    {
        return $this
            ->allowReadFor($subject, $priority)
            ->allowCreateFor($subject, $priority)
            ->allowUpdateFor($subject, $priority);
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
