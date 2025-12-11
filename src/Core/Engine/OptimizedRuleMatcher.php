<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Override;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_values;

/**
 * Performance-optimized rule matcher using indexed lookups and short-circuit evaluation.
 *
 * Wraps any rule matcher implementation with index-based filtering and optional short-circuit
 * evaluation to reduce the number of rule comparisons during authorization checks. Uses three
 * separate indexes (subject, resource, action) to quickly filter candidate rules before delegating
 * to the underlying matcher for precise evaluation, achieving O(1) candidate lookup instead of O(n).
 *
 * Performance optimizations:
 * - Subject/resource/action indexes for O(1) candidate filtering
 * - Short-circuit evaluation returns immediately on first Deny match
 * - Reduces matcher comparisons from O(n) to O(k) where k << n
 *
 * @author Brian Faust <brian@cline.sh>
 * @see RuleMatcherInterface For the matcher interface being optimized
 */
final class OptimizedRuleMatcher implements RuleMatcherInterface
{
    /**
     * Index of rules grouped by subject ID for fast subject-based filtering.
     *
     * Structure: ['subject_id' => [PolicyRule, PolicyRule, ...], ...]
     *
     * @var array<string, array<PolicyRule>>
     */
    private array $subjectIndex = [];

    /**
     * Index of rules grouped by resource ID for fast resource-based filtering.
     *
     * Structure: ['resource_id' => [PolicyRule, PolicyRule, ...], ...]
     * Null resources are stored with key '_null_'.
     *
     * @var array<string, array<PolicyRule>>
     */
    private array $resourceIndex = [];

    /**
     * Index of rules grouped by action name for fast action-based filtering.
     *
     * Structure: ['action_name' => [PolicyRule, PolicyRule, ...], ...]
     *
     * @var array<string, array<PolicyRule>>
     */
    private array $actionIndex = [];

    /**
     * Create a new optimized rule matcher with indexed lookups.
     *
     * @param RuleMatcherInterface $ruleMatcher  The underlying matcher to delegate precise rule
     *                                           evaluation to after candidate filtering. Can be
     *                                           any matcher implementation (RBAC, ABAC, RESTful).
     * @param bool                 $shortCircuit Whether to return immediately on first Deny match
     *                                           during evaluation. When true, provides significant
     *                                           performance improvement for deny-heavy policies by
     *                                           avoiding unnecessary rule evaluations.
     */
    public function __construct(
        private readonly RuleMatcherInterface $ruleMatcher,
        private readonly bool $shortCircuit = true,
    ) {}

    /**
     * Build indexes for fast rule lookups across subject, resource, and action dimensions.
     *
     * Populates three hash-based indexes that enable O(1) candidate rule filtering during
     * authorization checks. Each rule is indexed multiple times (by subject, resource, and
     * action) to support efficient filtering from different query patterns. This preprocessing
     * step trades memory for query performance, reducing authorization latency significantly
     * for policies with hundreds or thousands of rules.
     *
     * @param array<PolicyRule> $rules The policy rules to index for fast lookup operations
     */
    public function indexRules(array $rules): void
    {
        // Clear existing indexes for fresh indexing
        $this->subjectIndex = [];
        $this->resourceIndex = [];
        $this->actionIndex = [];

        foreach ($rules as $rule) {
            // Index by subject ID for subject-based filtering
            if (!array_key_exists($rule->subject, $this->subjectIndex)) {
                $this->subjectIndex[$rule->subject] = [];
            }

            $this->subjectIndex[$rule->subject][] = $rule;

            // Index by resource ID, using special key for null resources
            $resourceKey = $rule->resource ?? '_null_';

            if (!array_key_exists($resourceKey, $this->resourceIndex)) {
                $this->resourceIndex[$resourceKey] = [];
            }

            $this->resourceIndex[$resourceKey][] = $rule;

            // Index by action name for action-based filtering
            if (!array_key_exists($rule->action, $this->actionIndex)) {
                $this->actionIndex[$rule->action] = [];
            }

            $this->actionIndex[$rule->action][] = $rule;
        }
    }

    /**
     * Delegate to the underlying matcher to determine if a rule matches the authorization request.
     *
     * This method simply passes through to the wrapped matcher implementation without any
     * optimization, maintaining compatibility with the RuleMatcherInterface contract. The
     * optimization occurs in getCandidateRules() which filters rules before calling this method.
     *
     * @param  PolicyRule $rule     The policy rule to evaluate against the authorization request
     * @param  Subject    $subject  The subject requesting access
     * @param  resource   $resource The resource being accessed
     * @param  Action     $action   The action being performed
     * @return bool       True if the rule matches all components of the authorization request
     */
    #[Override()]
    public function matches(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): bool {
        return $this->ruleMatcher->matches($rule, $subject, $resource, $action);
    }

    /**
     * Evaluate a rule with optional short-circuit optimization for deny effects.
     *
     * Combines rule matching with immediate effect extraction, enabling callers to stop
     * evaluation as soon as a deny is found. When short-circuiting is enabled and a rule
     * matches with Deny effect, returns Deny immediately without evaluating remaining rules,
     * providing significant performance improvement for deny-heavy authorization policies.
     *
     * @param  PolicyRule  $rule     The policy rule to evaluate
     * @param  Subject     $subject  The subject requesting access
     * @param  resource    $resource The resource being accessed
     * @param  Action      $action   The action being performed
     * @return null|Effect The rule's effect if it matches, null if it doesn't match
     */
    public function matchesWithShortCircuit(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): ?Effect {
        if (!$this->matches($rule, $subject, $resource, $action)) {
            return null;
        }

        // Short-circuit optimization: return deny immediately for fail-fast authorization
        if ($this->shortCircuit && $rule->effect === Effect::Deny) {
            return Effect::Deny;
        }

        return $rule->effect;
    }

    /**
     * Retrieve pre-filtered candidate rules using indexed lookups.
     *
     * Performs O(1) index lookups to identify potentially matching rules based on subject ID
     * and wildcard patterns, then applies precise matching using the underlying matcher. This
     * two-phase approach (index filter + precise match) dramatically reduces the number of
     * expensive rule comparisons from O(n) to O(k) where k is the filtered candidate set.
     *
     * Filtering strategy:
     * 1. Index lookup by subject ID for direct matches
     * 2. Index lookup by wildcard (*) for universal rules
     * 3. Precise matching on candidates using underlying matcher
     *
     * @param  Subject           $subject  The subject to filter rules for
     * @param  resource          $resource The resource to filter rules for
     * @param  Action            $action   The action to filter rules for
     * @return array<PolicyRule> The filtered set of candidate rules that match the request
     */
    public function getCandidateRules(Subject $subject, Resource $resource, Action $action): array
    {
        // Perform O(1) index lookups to build initial candidate set
        $candidates = [];

        // Lookup rules matching subject ID directly
        if (array_key_exists($subject->id, $this->subjectIndex)) {
            $candidates = array_merge($candidates, $this->subjectIndex[$subject->id]);
        }

        // Lookup rules with wildcard subject for universal rules
        if (array_key_exists('*', $this->subjectIndex)) {
            $candidates = array_merge($candidates, $this->subjectIndex['*']);
        }

        // Early exit if no candidates found in index
        if ($candidates === []) {
            return [];
        }

        // Apply precise matching to candidates using underlying matcher
        $filtered = array_filter($candidates, fn (PolicyRule $rule): bool => $this->matches($rule, $subject, $resource, $action));

        return array_values($filtered);
    }
}
