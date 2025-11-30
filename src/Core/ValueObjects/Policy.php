<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use function usort;

/**
 * Immutable value object representing a collection of authorization rules.
 *
 * Encapsulates a complete policy document containing multiple rules that define
 * access control decisions. Provides immutable operations for rule management
 * and priority-based evaluation order. Each rule specifies who (subject) can
 * perform what (action) on which resources, with what effect (allow/deny).
 *
 * Policies are evaluated by processing rules in priority order, with higher
 * priority rules taking precedence in decision-making. Supports both explicit
 * allow and explicit deny patterns for fine-grained access control.
 *
 * Supports basic policy inheritance through the extends field, enabling policies
 * to inherit rules from base policies. Use inheritFrom() to merge base policy
 * rules with child policy rules.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class Policy
{
    /**
     * Create a new immutable policy with a collection of rules.
     *
     * @param array<PolicyRule> $rules   Collection of policy rules defining the access control
     *                                   decisions for this policy. Can be empty for a new policy
     *                                   that will be populated via addRule(). Rules are stored
     *                                   in insertion order and can be sorted by priority for
     *                                   evaluation using sortedByPriority().
     * @param null|string       $name    Optional policy identifier for inheritance and lookup
     * @param null|string       $extends Optional base policy name to inherit rules from
     */
    public function __construct(
        public array $rules = [],
        public ?string $name = null,
        public ?string $extends = null,
    ) {}

    /**
     * Add a new rule to the policy, returning a new policy instance.
     *
     * Creates a new immutable Policy instance with the additional rule appended
     * to the existing rule set. The original policy remains unchanged, following
     * immutable value object principles.
     *
     * @param  PolicyRule $rule The policy rule to add to the collection
     * @return self       New Policy instance containing all previous rules plus the new rule
     */
    public function addRule(PolicyRule $rule): self
    {
        return new self(
            rules: [...$this->rules, $rule],
            name: $this->name,
            extends: $this->extends,
        );
    }

    /**
     * Merge this policy with a base policy, inheriting its rules.
     *
     * Creates a new Policy instance containing all rules from the base policy
     * followed by all rules from this policy. Base policy rules have lower
     * priority in evaluation order, allowing child policies to override or
     * extend base behavior.
     *
     * @param  self $basePolicy The base policy to inherit rules from
     * @return self New Policy instance with merged rules
     */
    public function inheritFrom(self $basePolicy): self
    {
        return new self(
            rules: [...$basePolicy->rules, ...$this->rules],
            name: $this->name,
            extends: $this->extends,
        );
    }

    /**
     * Get rules sorted by priority in descending order (highest first).
     *
     * Returns a new array of rules ordered by priority value, ensuring higher
     * priority rules are evaluated first during policy decision-making. This
     * allows explicit deny rules or high-priority exceptions to override default
     * permissions.
     *
     * The original policy and its rules array remain unchanged. The sort is
     * stable and uses spaceship operator comparison for numeric priority values.
     *
     * @return array<PolicyRule> New array of rules sorted from highest to lowest priority
     */
    public function sortedByPriority(): array
    {
        $sorted = $this->rules;
        usort($sorted, fn ($a, $b): int => $b->priority->value <=> $a->priority->value);

        return $sorted;
    }
}
