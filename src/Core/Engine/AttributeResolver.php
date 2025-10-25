<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Illuminate\Support\Facades\Date;
use Patrol\Core\Contracts\AttributeProviderInterface;

use function array_map;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_numeric;
use function mb_substr;
use function property_exists;
use function str_contains;
use function str_starts_with;

/**
 * Resolves entity attributes and evaluates conditional expressions for ABAC policies.
 *
 * Provides the core attribute extraction and condition evaluation logic that enables
 * attribute-based access control. Supports dotted notation for accessing nested properties
 * (e.g., "resource.owner", "subject.department"), equality and inequality operators for
 * comparisons, and pluggable attribute providers for custom resolution strategies.
 *
 * @see AttributeProviderInterface For custom attribute extraction implementations
 * @see AbacRuleMatcher For ABAC rule matching using attribute conditions
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class AttributeResolver
{
    /**
     * Create a new attribute resolver.
     *
     * @param null|AttributeProviderInterface $provider Optional custom attribute provider for
     *                                                  extracting entity attributes. When provided,
     *                                                  delegates attribute resolution to the provider
     *                                                  instead of using direct property access, enabling
     *                                                  custom extraction logic for complex entities.
     */
    public function __construct(
        private ?AttributeProviderInterface $provider = null,
    ) {}

    /**
     * Resolve an attribute expression to extract its value from an entity.
     *
     * Parses dotted notation expressions like "resource.owner" or "subject.department"
     * to extract attribute values from entity objects. The resolution strategy depends
     * on whether a custom attribute provider is configured or falls back to direct
     * property and array access for standard entity structures.
     *
     * @param  string $expression The attribute expression to resolve (e.g., "resource.owner")
     * @param  object $context    The entity context to extract the attribute from
     * @return mixed  The resolved attribute value, or null if not found
     */
    public function resolve(string $expression, object $context): mixed
    {
        // Parse dotted expression into context type and attribute name
        $parts = explode('.', $expression, 2);

        // Require valid dotted notation with exactly two parts
        if (count($parts) !== 2) {
            return null;
        }

        [$contextType, $attribute] = $parts;

        // Delegate to custom provider when configured for flexible extraction
        if ($this->provider instanceof AttributeProviderInterface) {
            return $this->provider->getAttribute($context, $attribute);
        }

        // Fall back to direct property and array access for standard entities
        return $this->getDirectAttribute($context, $attribute);
    }

    /**
     * Evaluate a conditional expression comparing entity attributes.
     *
     * Parses and evaluates condition strings containing equality (==) or inequality (!=)
     * operators, resolving both sides of the comparison from subject and resource contexts.
     * Supports expressions like "resource.owner == subject.id" or "resource.protected == true"
     * for dynamic authorization decisions based on runtime entity state.
     *
     * Advanced ABAC operators supported:
     * - Time-based: `resource.embargo_until < request.time`
     * - Numeric: `subject.level >= resource.required_level`
     * - Array contains: `subject.tags contains resource.category`
     * - Array in: `resource.category in subject.allowed_categories`
     *
     * @param  string $condition The condition expression to evaluate (e.g., "resource.owner == subject.id")
     * @param  object $subject   The subject entity for attribute resolution
     * @param  object $resource  The resource entity for attribute resolution
     * @return bool   True if the condition evaluates to true, false otherwise
     */
    public function evaluateCondition(
        string $condition,
        object $subject,
        object $resource,
    ): bool {
        // Greater than or equal
        if (str_contains($condition, '>=')) {
            [$left, $right] = array_map(trim(...), explode('>=', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue >= $rightValue;
        }

        // Less than or equal
        if (str_contains($condition, '<=')) {
            [$left, $right] = array_map(trim(...), explode('<=', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue <= $rightValue;
        }

        // Greater than (no need to check >=, already handled above)
        if (str_contains($condition, '>')) {
            [$left, $right] = array_map(trim(...), explode('>', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue > $rightValue;
        }

        // Less than (no need to check <=, already handled above)
        if (str_contains($condition, '<')) {
            [$left, $right] = array_map(trim(...), explode('<', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue < $rightValue;
        }

        // Array contains operator (e.g., "subject.tags contains resource.category")
        if (str_contains($condition, ' contains ')) {
            [$left, $right] = array_map(trim(...), explode(' contains ', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            if (!is_array($leftValue)) {
                return false;
            }

            return in_array($rightValue, $leftValue, true);
        }

        // Array in operator (e.g., "resource.category in subject.allowed_categories")
        if (str_contains($condition, ' in ')) {
            [$left, $right] = array_map(trim(...), explode(' in ', $condition, 2));
            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            if (!is_array($rightValue)) {
                return false;
            }

            return in_array($leftValue, $rightValue, true);
        }

        // Evaluate equality conditions with strict comparison
        if (str_contains($condition, '==')) {
            [$left, $right] = array_map(trim(...), explode('==', $condition, 2));

            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue === $rightValue;
        }

        // Evaluate inequality conditions with strict comparison
        if (str_contains($condition, '!=')) {
            [$left, $right] = array_map(trim(...), explode('!=', $condition, 2));

            $leftValue = $this->resolveValue($left, $subject, $resource);
            $rightValue = $this->resolveValue($right, $subject, $resource);

            return $leftValue !== $rightValue;
        }

        // Invalid condition format returns false
        return false;
    }

    /**
     * Extract an attribute value using direct property and array access.
     *
     * Attempts two extraction strategies: direct property access for attributes defined
     * as object properties, and array access via an "attributes" property for entities
     * using associative array storage. This fallback resolution enables basic attribute
     * extraction without requiring a custom attribute provider.
     *
     * @param  object $context   The entity to extract the attribute from
     * @param  string $attribute The attribute name to retrieve
     * @return mixed  The attribute value, or null if not found
     */
    private function getDirectAttribute(object $context, string $attribute): mixed
    {
        // Access attribute as direct object property
        if (property_exists($context, $attribute)) {
            return $context->{$attribute};
        }

        // Access attribute from array-based storage via attributes property
        if (property_exists($context, 'attributes') && is_array($context->attributes)) {
            return $context->attributes[$attribute] ?? null;
        }

        return null;
    }

    /**
     * Resolve a value from an expression, supporting attributes, literals, and booleans.
     *
     * Determines the value type based on the expression format: dotted notation prefixed
     * with "subject." or "resource." triggers attribute extraction from the respective
     * entity, boolean string literals "true" and "false" are converted to boolean values,
     * and all other expressions are treated as literal string values for comparison.
     *
     * @param  string $expression The expression to resolve (attribute, literal, or boolean)
     * @param  object $subject    The subject entity for subject.* attribute resolution
     * @param  object $resource   The resource entity for resource.* attribute resolution
     * @return mixed  The resolved value ready for comparison
     */
    private function resolveValue(string $expression, object $subject, object $resource): mixed
    {
        // Resolve subject attributes from dotted notation
        if (str_starts_with($expression, 'subject.')) {
            $attribute = mb_substr($expression, 8); // Remove "subject." prefix

            return $this->getDirectAttribute($subject, $attribute);
        }

        // Resolve resource attributes from dotted notation
        if (str_starts_with($expression, 'resource.')) {
            $attribute = mb_substr($expression, 9); // Remove "resource." prefix

            return $this->getDirectAttribute($resource, $attribute);
        }

        // Resolve request.time for time-based conditions
        if ($expression === 'request.time') {
            return Date::now()->getTimestamp();
        }

        // Convert boolean string literals to boolean values
        if ($expression === 'true') {
            return true;
        }

        if ($expression === 'false') {
            return false;
        }

        // Convert numeric strings to numbers for numeric comparisons
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        // Treat all other values as literal strings for comparison
        return $expression;
    }
}
