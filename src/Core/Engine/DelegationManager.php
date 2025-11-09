<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Subject;
use RuntimeException;

use function random_int;
use function sprintf;
use function throw_unless;

/**
 * Orchestrates delegation business logic and lifecycle management.
 *
 * Provides high-level operations for creating, revoking, and querying delegations
 * with proper validation and security controls. Coordinates between the delegation
 * repository and validator to ensure delegations meet business rules and don't
 * introduce security vulnerabilities.
 *
 * Also handles conversion of active delegations into PolicyRules for evaluation
 * by the authorization engine, enabling seamless integration with existing policy
 * evaluation infrastructure.
 *
 * Responsibilities:
 * - Create delegations with validation
 * - Revoke delegations with authorization checks
 * - Query active delegations for subjects
 * - Convert delegations to policy rules for evaluation
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DelegationManager
{
    /**
     * Create a new delegation manager.
     *
     * @param DelegationRepositoryInterface $repository Handles delegation persistence and retrieval
     * @param DelegationValidator           $validator  Validates delegations before creation
     */
    public function __construct(
        private DelegationRepositoryInterface $repository,
        private DelegationValidator $validator,
    ) {}

    /**
     * Create a new delegation after validation.
     *
     * Validates the delegation request to ensure:
     * - Delegator has the permissions being delegated
     * - No delegation cycles are created
     * - Expiration timestamp is valid
     *
     * If validation passes, creates and persists the delegation.
     *
     * ```php
     * $delegation = $manager->delegate(
     *     delegator: new Subject('user:123'),
     *     delegate: new Subject('user:456'),
     *     scope: new DelegationScope(['document:*'], ['read', 'edit']),
     *     expiresAt: (new DateTimeImmutable())->modify('+7 days')
     * );
     * ```
     *
     * @param Subject                $delegator  The subject granting permissions
     * @param Subject                $delegate   The subject receiving permissions
     * @param DelegationScope        $scope      The permissions being delegated
     * @param null|DateTimeImmutable $expiresAt  Optional expiration timestamp
     * @param bool                   $transitive Whether delegate can re-delegate
     * @param array<string, mixed>   $metadata   Optional metadata for audit/context
     *
     * @throws RuntimeException If validation fails
     *
     * @return Delegation The created delegation
     */
    public function delegate(
        Subject $delegator,
        Subject $delegate,
        DelegationScope $scope,
        ?DateTimeImmutable $expiresAt = null,
        bool $transitive = false,
        array $metadata = [],
    ): Delegation {
        $delegation = new Delegation(
            id: $this->generateId(),
            delegatorId: $delegator->id,
            delegateId: $delegate->id,
            scope: $scope,
            createdAt: CarbonImmutable::now(),
            expiresAt: $expiresAt,
            isTransitive: $transitive,
            status: DelegationState::Active,
            metadata: $metadata,
        );

        throw_unless($this->validator->validate($delegation, $delegator), RuntimeException::class, 'Delegation validation failed');

        $this->repository->create($delegation);

        return $delegation;
    }

    /**
     * Revoke an existing delegation.
     *
     * Marks the delegation as revoked, immediately removing it from active
     * authorization checks. The delegation record is retained for audit purposes
     * until cleanup processes remove it based on retention policies.
     *
     * @param string $delegationId The unique identifier of the delegation to revoke
     */
    public function revoke(string $delegationId): void
    {
        $this->repository->revoke($delegationId);
    }

    /**
     * Find all active delegations where the subject is the delegate.
     *
     * Returns delegations that grant permissions to the specified subject,
     * filtered to only include currently active (not expired/revoked) delegations.
     * Used for authorization checks and delegation management interfaces.
     *
     * @param  Subject           $delegate The subject receiving delegated permissions
     * @return array<Delegation> Active delegations for this delegate
     */
    public function findActiveDelegations(Subject $delegate): array
    {
        return $this->repository->findActiveForDelegate($delegate->id);
    }

    /**
     * Check if a subject can delegate the specified scope.
     *
     * Verifies the subject has the permissions they would be delegating,
     * preventing privilege escalation. Useful for UI controls and pre-flight
     * checks before attempting delegation.
     *
     * @param  Subject         $delegator The subject who would grant permissions
     * @param  DelegationScope $scope     The permissions to be delegated
     * @return bool            True if delegator can delegate this scope
     */
    public function canDelegate(Subject $delegator, DelegationScope $scope): bool
    {
        return $this->validator->validateDelegatorPermissions($delegator, $scope);
    }

    /**
     * Convert active delegations to policy rules for evaluation.
     *
     * Transforms all active delegations for a delegate into PolicyRules that can
     * be evaluated by the PolicyEvaluator. This enables delegated permissions to
     * be checked using the same evaluation engine as direct permissions.
     *
     * Each delegation scope is expanded into one or more PolicyRules matching
     * the resource and action patterns. Delegated permissions are additive and
     * evaluated alongside direct permissions.
     *
     * @param  Subject           $delegate The subject whose delegated permissions to convert
     * @return array<PolicyRule> Policy rules representing active delegations
     */
    public function toPolicyRules(Subject $delegate): array
    {
        $delegations = $this->findActiveDelegations($delegate);

        $rules = [];

        foreach ($delegations as $delegation) {
            foreach ($delegation->scope->resources as $resource) {
                foreach ($delegation->scope->actions as $action) {
                    $rules[] = new PolicyRule(
                        subject: $delegate->id,
                        resource: $resource,
                        action: $action,
                        effect: Effect::Allow,
                        priority: new Priority(50), // Medium priority for delegated permissions
                    );
                }
            }
        }

        return $rules;
    }

    /**
     * Generate a unique identifier for a new delegation.
     *
     * Uses UUID v4 for globally unique delegation identifiers. Implementations
     * can override this to use alternative ID generation strategies (ULID, etc).
     *
     * @return string Unique delegation identifier
     */
    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFF_FF),
            random_int(0, 0xFF_FF),
            random_int(0, 0xFF_FF),
            random_int(0, 0x0F_FF) | 0x40_00,
            random_int(0, 0x3F_FF) | 0x80_00,
            random_int(0, 0xFF_FF),
            random_int(0, 0xFF_FF),
            random_int(0, 0xFF_FF),
        );
    }
}
