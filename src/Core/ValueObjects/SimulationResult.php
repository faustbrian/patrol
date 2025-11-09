<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\ValueObjects;

use function count;

/**
 * Immutable value object representing a policy simulation result with diagnostics.
 *
 * Captures the complete outcome of a policy evaluation including the authorization
 * decision, execution timing, and diagnostic metadata. Enables "what-if" scenario
 * testing, policy debugging, and performance profiling without affecting production
 * authorization or persisted policies.
 *
 * The result preserves all simulation inputs (policy, subject, resource, action) along
 * with outputs (effect, timing, matched rules) to provide full traceability and enable
 * correlation analysis. This facilitates debugging complex authorization scenarios and
 * optimizing policy performance.
 *
 * Common use cases:
 * - Policy validation before deployment
 * - Performance profiling and optimization
 * - Debugging authorization decisions
 * - Policy authoring with immediate feedback
 * - Documentation and training examples
 * - Regression testing for policy changes
 *
 * Results can be serialized to arrays for logging, API responses, or storage using
 * the toArray() method which provides a compact JSON-friendly representation.
 *
 * ```php
 * $result = $simulator->simulate($policy, $subject, $resource, $action);
 *
 * if ($result->effect === Effect::Deny) {
 *     error_log("Simulation denied in {$result->executionTime}ms");
 *     error_log(json_encode($result->toArray()));
 * }
 * ```
 *
 * @see PolicySimulator For generating simulation results
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class SimulationResult
{
    /**
     * Create a new immutable simulation result.
     *
     * @param Effect            $effect        The final authorization decision produced by the simulation (Allow or Deny).
     *                                         This reflects what would happen if the policy were deployed and evaluated
     *                                         with the given subject, resource, and action in production.
     * @param Policy            $policy        The policy that was evaluated during the simulation. This policy was never
     *                                         persisted to storage and exists only within the simulation context. Useful
     *                                         for correlating results with specific policy versions during testing.
     * @param Subject           $subject       The subject that requested access in the simulation. Includes all attributes
     *                                         and metadata used during policy evaluation. Preserving this enables analysis
     *                                         of how different subject characteristics affect authorization decisions.
     * @param resource          $resource      The resource that was accessed in the simulation. Includes all attributes
     *                                         and metadata used for rule matching. Preserving this enables correlation
     *                                         between resource properties and authorization outcomes.
     * @param Action            $action        The action that was performed in the simulation. Preserving this completes
     *                                         the authorization context triple (subject, resource, action) for full
     *                                         traceability and debugging support.
     * @param float             $executionTime The policy evaluation duration in milliseconds with microsecond precision.
     *                                         Measured using microtime(true) for high-resolution timing. Useful for
     *                                         performance profiling, identifying slow policies, and optimization analysis.
     * @param array<PolicyRule> $matchedRules  Rules that matched the authorization request during evaluation. Provides
     *                                         insight into which rules contributed to the final effect, enabling debugging
     *                                         of complex policies. Currently empty in simulation results as a placeholder
     *                                         for future enhancement.
     *
     * @phpstan-param Resource $resource PHPDoc uses lowercase 'resource' to avoid conflicts with PHP's resource type
     */
    public function __construct(
        public Effect $effect,
        public Policy $policy,
        public Subject $subject,
        public Resource $resource,
        public Action $action,
        public float $executionTime,
        public array $matchedRules,
    ) {}

    /**
     * Convert the simulation result to an array for serialization.
     *
     * Produces a compact, JSON-friendly representation of the simulation result suitable
     * for logging, API responses, or persistent storage. The array format omits the full
     * policy object and reduces subjects, resources, and actions to their identifiers to
     * minimize payload size while preserving essential diagnostic information.
     *
     * Use this for:
     * - JSON API responses returning simulation results
     * - Structured logging of simulation outcomes
     * - Storage in databases or document stores
     * - Metrics and monitoring systems
     * - Audit trails for policy testing activities
     *
     * @return array{effect: string, subject: string, resource: string, action: string, execution_time_ms: float, matched_rules: int}
     *                                                                                                                                Associative array containing the authorization effect, subject/resource/action identifiers,
     *                                                                                                                                execution timing, and matched rule count. All values are primitive types suitable for JSON
     *                                                                                                                                encoding and database storage.
     */
    public function toArray(): array
    {
        return [
            'effect' => $this->effect->value,
            'subject' => $this->subject->id,
            'resource' => $this->resource->id,
            'action' => $this->action->name,
            'execution_time_ms' => $this->executionTime,
            'matched_rules' => count($this->matchedRules),
        ];
    }
}
