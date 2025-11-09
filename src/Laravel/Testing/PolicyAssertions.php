<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Testing;

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use PHPUnit\Framework\Assert;

use function sprintf;

/**
 * PHPUnit test assertions for policy-based access control validation.
 *
 * Provides fluent assertion methods for testing authorization policies in PHPUnit
 * and Pest tests. Integrates with the Patrol policy evaluation engine to verify
 * that policies correctly allow or deny access based on subject, resource, and
 * action combinations. Includes detailed failure messages for debugging.
 *
 * ```php
 * use Patrol\Laravel\Testing\PolicyAssertions;
 *
 * class PolicyTest extends TestCase
 * {
 *     use PolicyAssertions;
 *
 *     public function test_admin_can_delete_posts(): void
 *     {
 *         $policy = $this->createPolicy([...]);
 *         $this->assertCanAccess('admin', 'post:123', 'delete', $policy);
 *     }
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-ignore trait.unused
 */
trait PolicyAssertions
{
    /**
     * Assert that a subject can access a resource with a specific action.
     *
     * Evaluates the provided policy against the subject, resource, and action,
     * expecting an Allow effect. Fails the test if the policy denies access.
     * Supports both value objects and string identifiers for convenience. Uses
     * the Patrol policy evaluation engine to perform the authorization check.
     *
     * @param string|Subject  $subject  The subject requesting access (user, role, etc.). String values
     *                                  are automatically converted to Subject objects using the string
     *                                  as the subject ID.
     * @param resource|string $resource The target resource being accessed. String values are converted
     *                                  to Resource objects with the string as ID and type "default".
     * @param Action|string   $action   The action being performed (e.g., "read", "write", "delete").
     *                                  String values are converted to Action objects.
     * @param Policy          $policy   The policy set to evaluate against. Must contain rules that
     *                                  determine access for the given subject-resource-action combination.
     * @param string          $message  Optional custom assertion failure message. When empty, defaults
     *                                  to a descriptive message showing subject ID, action name, and
     *                                  resource ID for debugging.
     */
    protected function assertCanAccess(
        Subject|string $subject,
        Resource|string $resource,
        Action|string $action,
        Policy $policy,
        string $message = '',
    ): void {
        $subject = $subject instanceof Subject ? $subject : new Subject($subject);
        $resource = $resource instanceof Resource ? $resource : new Resource($resource, 'default');
        $action = $action instanceof Action ? $action : new Action($action);

        $evaluator = new PolicyEvaluator(
            new AclRuleMatcher(),
            new EffectResolver(),
        );
        $result = $evaluator->evaluate($policy, $subject, $resource, $action);

        Assert::assertSame(
            Effect::Allow,
            $result,
            $message !== '' && $message !== '0' ? $message : sprintf(
                'Failed asserting that subject [%s] can [%s] resource [%s]',
                $subject->id,
                $action->name,
                $resource->id,
            ),
        );
    }

    /**
     * Assert that a subject cannot access a resource with a specific action.
     *
     * Evaluates the provided policy against the subject, resource, and action,
     * expecting a Deny effect. Fails the test if the policy allows access.
     * Supports both value objects and string identifiers for convenience. Uses
     * the Patrol policy evaluation engine to perform the authorization check.
     *
     * @param string|Subject  $subject  The subject requesting access (user, role, etc.). String values
     *                                  are automatically converted to Subject objects using the string
     *                                  as the subject ID.
     * @param resource|string $resource The target resource being accessed. String values are converted
     *                                  to Resource objects with the string as ID and type "default".
     * @param Action|string   $action   The action being performed (e.g., "read", "write", "delete").
     *                                  String values are converted to Action objects.
     * @param Policy          $policy   The policy set to evaluate against. Must contain rules that
     *                                  determine access for the given subject-resource-action combination.
     * @param string          $message  Optional custom assertion failure message. When empty, defaults
     *                                  to a descriptive message showing subject ID, action name, and
     *                                  resource ID for debugging.
     */
    protected function assertCannotAccess(
        Subject|string $subject,
        Resource|string $resource,
        Action|string $action,
        Policy $policy,
        string $message = '',
    ): void {
        $subject = $subject instanceof Subject ? $subject : new Subject($subject);
        $resource = $resource instanceof Resource ? $resource : new Resource($resource, 'default');
        $action = $action instanceof Action ? $action : new Action($action);

        $evaluator = new PolicyEvaluator(
            new AclRuleMatcher(),
            new EffectResolver(),
        );
        $result = $evaluator->evaluate($policy, $subject, $resource, $action);

        Assert::assertSame(
            Effect::Deny,
            $result,
            $message !== '' && $message !== '0' ? $message : sprintf(
                'Failed asserting that subject [%s] cannot [%s] resource [%s]',
                $subject->id,
                $action->name,
                $resource->id,
            ),
        );
    }

    /**
     * Assert that a policy allows access for given subject, resource, and action.
     *
     * Convenience alias for assertCanAccess() that enforces string-only parameters.
     * Useful for maintaining consistent naming conventions in policy test suites
     * and ensuring test readability with explicit "policy allows" semantics.
     *
     * @param string $subject  The subject identifier requesting access
     * @param string $resource The resource identifier being accessed
     * @param string $action   The action being performed
     * @param Policy $policy   The policy set to evaluate against
     */
    protected function assertPolicyAllows(
        string $subject,
        string $resource,
        string $action,
        Policy $policy,
    ): void {
        $this->assertCanAccess($subject, $resource, $action, $policy);
    }

    /**
     * Assert that a policy denies access for given subject, resource, and action.
     *
     * Convenience alias for assertCannotAccess() that enforces string-only parameters.
     * Useful for maintaining consistent naming conventions in policy test suites
     * and ensuring test readability with explicit "policy denies" semantics.
     *
     * @param string $subject  The subject identifier requesting access
     * @param string $resource The resource identifier being accessed
     * @param string $action   The action being performed
     * @param Policy $policy   The policy set to evaluate against
     */
    protected function assertPolicyDenies(
        string $subject,
        string $resource,
        string $action,
        Policy $policy,
    ): void {
        $this->assertCannotAccess($subject, $resource, $action, $policy);
    }
}
