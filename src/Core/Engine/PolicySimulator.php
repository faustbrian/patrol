<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\SimulationResult;
use Patrol\Core\ValueObjects\Subject;

use function microtime;

/**
 * Simulates policy evaluation for testing and validation without affecting real policies.
 *
 * Enables "what-if" scenario testing by evaluating policies that haven't been deployed
 * yet, allowing policy authors to verify behavior before committing changes. Captures
 * detailed execution metadata including timing information and matched rules to aid
 * debugging and performance optimization.
 *
 * Use cases:
 * - Pre-deployment policy validation and testing
 * - Policy authoring workflow with immediate feedback
 * - Performance profiling and optimization analysis
 * - Debugging authorization decisions in development
 * - Training and documentation with live policy examples
 * - Policy migration validation before applying changes
 *
 * The simulator provides a safe sandbox for policy experimentation without risk of
 * affecting production authorization decisions or persisted policy data. All simulation
 * results include timing data for performance analysis and optimization.
 *
 * ```php
 * $simulator = new PolicySimulator($evaluator);
 * $testPolicy = new Policy([...]);
 *
 * $result = $simulator->simulate($testPolicy, $subject, $resource, $action);
 * echo "Effect: {$result->effect->value}\n";
 * echo "Time: {$result->executionTime}ms\n";
 * ```
 *
 * @see SimulationResult For the detailed result structure with timing and metadata
 * @see PolicyEvaluator For the underlying evaluation engine
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class PolicySimulator
{
    /**
     * Create a new policy simulator with an evaluator.
     *
     * @param PolicyEvaluator $evaluator The policy evaluator for executing simulations. Uses the same
     *                                   evaluation logic as production authorization to ensure simulation
     *                                   results accurately reflect real behavior. The evaluator should be
     *                                   configured with the appropriate rule matcher (ACL, ABAC, REST, etc.)
     *                                   to match production authorization semantics.
     */
    public function __construct(
        private PolicyEvaluator $evaluator,
    ) {}

    /**
     * Simulate an authorization decision without affecting real policies or audit logs.
     *
     * Evaluates the provided policy against the authorization request and returns a
     * comprehensive result including the authorization decision, execution timing, and
     * diagnostic information. The policy is never persisted to storage and the simulation
     * does not trigger audit logging or side effects.
     *
     * Simulation guarantees:
     * - No database writes or policy persistence
     * - No audit log entries created
     * - No side effects in authorization system
     * - Results identical to real evaluation for same inputs
     * - Thread-safe and can run concurrently
     *
     * Performance measurement:
     * - High-resolution timing using microtime(true)
     * - Millisecond precision for fine-grained analysis
     * - Includes full evaluation overhead (rule matching, effect resolution)
     * - Excludes I/O operations (no database queries in simulation)
     *
     * ```php
     * $result = $simulator->simulate($policy, $subject, $resource, $action);
     *
     * if ($result->effect === Effect::Deny) {
     *     // Policy needs refinement
     *     echo "Denied in {$result->executionTime}ms\n";
     * }
     * ```
     *
     * @param  Policy           $policy   The policy to simulate evaluation for. This policy is never saved or
     *                                    persisted to storage. Can be a newly constructed policy, modified version
     *                                    of existing policy, or policy loaded from alternative source for testing.
     * @param  Subject          $subject  The subject requesting access in this simulation. Should represent realistic
     *                                    subject data including attributes and roles to ensure accurate simulation
     *                                    results that match production behavior.
     * @param  resource         $resource The resource being accessed in this simulation. Should include all relevant
     *                                    attributes and metadata that would be present in production authorization
     *                                    requests for accurate rule matching.
     * @param  Action           $action   The action being performed in this simulation. Should match the action format
     *                                    expected by the configured rule matcher (simple names for ACL, HTTP methods
     *                                    for REST, etc.) to ensure realistic results.
     * @return SimulationResult Immutable result object containing the authorization decision (Allow or Deny), execution
     *                          timing in milliseconds, and references to all simulation inputs for correlation and
     *                          analysis. Use toArray() method for serialization to logs or API responses.
     */
    public function simulate(
        Policy $policy,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): SimulationResult {
        // Capture high-resolution start time for performance measurement
        $startTime = microtime(true);

        // Evaluate the policy using production evaluation logic
        $effect = $this->evaluator->evaluate($policy, $subject, $resource, $action);

        // Calculate execution time in milliseconds with microsecond precision
        $executionTime = (microtime(true) - $startTime) * 1_000;

        // Collect matched rules for debugging (currently not implemented)
        $matchedRules = $this->getMatchedRules();

        return new SimulationResult(
            effect: $effect,
            policy: $policy,
            subject: $subject,
            resource: $resource,
            action: $action,
            executionTime: $executionTime,
            matchedRules: $matchedRules,
        );
    }

    /**
     * Identify rules that matched the authorization request.
     *
     * Returns the subset of policy rules that matched the subject, resource, and action
     * in the authorization request. Useful for debugging why a particular decision was
     * made and understanding which rules contributed to the final effect.
     *
     * Currently returns an empty array as a placeholder. Future enhancement will expose
     * the rule matcher's internal matching logic to populate this with actual matched rules.
     *
     * @return array<PolicyRule> Array of rules that matched the request (empty in current implementation).
     *                           Future versions will return the actual matched rules for debugging.
     */
    private function getMatchedRules(): array
    {
        // TODO: Expose matcher internals to populate matched rules
        // This requires refactoring PolicyEvaluator to return matched rules
        return [];
    }
}
