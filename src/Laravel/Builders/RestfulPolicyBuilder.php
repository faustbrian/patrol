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
 * Fluent builder for RESTful API authorization policies.
 *
 * Provides convenient methods for building common RESTful permission patterns
 * using HTTP verb semantics. Maps standard HTTP methods to authorization rules.
 *
 * ```php
 * use Patrol\Laravel\Builders\RestfulPolicyBuilder;
 *
 * $policy = RestfulPolicyBuilder::for('posts')
 *     ->allowGetFor('*')                    // Anyone can read
 *     ->allowPostFor('role:contributor')    // Contributors can create
 *     ->allowPutFor('role:editor')          // Editors can update
 *     ->allowPatchFor('role:editor')        // Editors can partially update
 *     ->allowDeleteFor('role:admin')        // Only admins can delete
 *     ->build();
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RestfulPolicyBuilder
{
    /**
     * Collection of policy rules being built.
     *
     * @var array<int, PolicyRule>
     */
    private array $rules = [];

    /**
     * Create a new RESTful policy builder for a resource.
     *
     * @param string $resource Resource identifier pattern that will be used for all policy rules
     *                         created by this builder. Typically represents an API endpoint or
     *                         resource type like 'posts', 'api/users', or 'documents'. Can include
     *                         wildcards for broader matching patterns.
     */
    private function __construct(
        private readonly string $resource,
    ) {}

    /**
     * Create a new RESTful policy builder for a resource.
     *
     * Factory method for creating a RestfulPolicyBuilder with fluent interface support.
     * This is the primary entry point for building RESTful API authorization policies.
     *
     * @param  string $resource Resource identifier pattern (e.g., 'posts', 'api/users')
     * @return self   New builder instance ready for configuration
     */
    public static function for(string $resource): self
    {
        return new self($resource);
    }

    /**
     * Allow GET requests (read) for a subject.
     *
     * @param  string $subject  Subject identifier (e.g., 'role:viewer', 'user:123', '*')
     * @param  int    $priority Rule priority (higher = evaluated first). Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowGetFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'GET',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny GET requests (read) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority (higher = evaluated first). Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyGetFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'GET',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow POST requests (create) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowPostFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'POST',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny POST requests (create) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyPostFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'POST',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow PUT requests (full update) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowPutFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'PUT',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny PUT requests (full update) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyPutFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'PUT',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow PATCH requests (partial update) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowPatchFor(string $subject, int $priority = 1): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'PATCH',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny PATCH requests (partial update) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 10.
     * @return self   Fluent interface
     */
    public function denyPatchFor(string $subject, int $priority = 10): self
    {
        $this->rules[] = new PolicyRule(
            subject: $subject,
            resource: $this->resource,
            action: 'PATCH',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow DELETE requests (delete) for a subject.
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
            action: 'DELETE',
            effect: Effect::Allow,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Deny DELETE requests (delete) for a subject.
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
            action: 'DELETE',
            effect: Effect::Deny,
            priority: new Priority($priority),
        );

        return $this;
    }

    /**
     * Allow all HTTP methods for a subject (full access).
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowAllFor(string $subject, int $priority = 1): self
    {
        return $this
            ->allowGetFor($subject, $priority)
            ->allowPostFor($subject, $priority)
            ->allowPutFor($subject, $priority)
            ->allowPatchFor($subject, $priority)
            ->allowDeleteFor($subject, $priority);
    }

    /**
     * Allow read-only access (GET only) for a subject.
     *
     * @param  string $subject  Subject identifier
     * @param  int    $priority Rule priority. Defaults to 1.
     * @return self   Fluent interface
     */
    public function allowReadOnlyFor(string $subject, int $priority = 1): self
    {
        return $this->allowGetFor($subject, $priority);
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
