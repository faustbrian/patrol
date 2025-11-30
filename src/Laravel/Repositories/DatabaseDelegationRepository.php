<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Repositories;

use DateTimeImmutable;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\ValueObjects\Delegation;
use Patrol\Core\ValueObjects\DelegationScope;
use Patrol\Core\ValueObjects\DelegationState;
use Patrol\Laravel\Models\Delegation as DelegationModel;

use function array_key_exists;
use function config;
use function is_array;
use function is_bool;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function now;

/**
 * Database-backed repository for delegation persistence.
 *
 * Stores delegations in a relational database table with JSON serialization
 * for complex value objects like DelegationScope. Provides optimized queries
 * for active delegation lookups (critical authorization path) with appropriate
 * indexes on delegate_id, status, and expires_at.
 *
 * Uses Laravel's database abstraction for compatibility with multiple database
 * engines (MySQL, PostgreSQL, SQLite) and supports custom connections for
 * multi-tenant architectures.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class DatabaseDelegationRepository implements DelegationRepositoryInterface
{
    /**
     * Create a new database delegation repository.
     *
     * @param string $connection The Laravel database connection name. Allows delegation
     *                           storage in separate databases or tenant-specific connections.
     */
    public function __construct(
        private string $connection = 'default',
    ) {}

    /**
     * Persist a new delegation to the database.
     *
     * Serializes the delegation value object to a database row with JSON encoding
     * for the scope and metadata fields. The delegation is immediately available
     * for queries after insertion.
     *
     * @param Delegation $delegation The delegation to persist
     */
    #[Override()]
    public function create(Delegation $delegation): void
    {
        DelegationModel::on($this->connection)->create([
            'id' => $delegation->id,
            'delegator_id' => $delegation->delegatorId,
            'delegate_id' => $delegation->delegateId,
            'scope' => [
                'resources' => $delegation->scope->resources,
                'actions' => $delegation->scope->actions,
                'domain' => $delegation->scope->domain,
            ],
            'created_at' => $delegation->createdAt,
            'expires_at' => $delegation->expiresAt,
            'is_transitive' => $delegation->isTransitive,
            'state' => $delegation->status,
            'metadata' => $delegation->metadata,
            'revoked_at' => null,
            'revoked_by' => null,
        ]);
    }

    /**
     * Retrieve a delegation by ID.
     *
     * Loads a delegation from the database and reconstitutes the value object
     * with proper deserialization of JSON fields. Returns null if the delegation
     * doesn't exist.
     *
     * @param  string          $id The unique delegation identifier
     * @return null|Delegation The delegation if found, null otherwise
     */
    #[Override()]
    public function findById(string $id): ?Delegation
    {
        $model = DelegationModel::on($this->connection)->find($id);

        if ($model === null) {
            return null;
        }

        return $this->hydrate($model);
    }

    /**
     * Find all active delegations for a delegate.
     *
     * Optimized query with indexes on delegate_id, status, and expires_at
     * for fast lookups on the authorization hot path. Filters to only active,
     * non-expired delegations.
     *
     * @param  string            $delegateId The delegate subject identifier
     * @return array<Delegation> Array of active delegations
     */
    #[Override()]
    public function findActiveForDelegate(string $delegateId): array
    {
        $query = DelegationModel::on($this->connection)
            ->where('delegate_id', $delegateId)
            ->where('state', DelegationState::Active);

        $query->where(function ($q): void {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });

        $models = $query->get();

        $delegations = [];

        foreach ($models as $model) {
            $delegations[] = $this->hydrate($model);
        }

        return $delegations;
    }

    /**
     * Revoke a delegation by ID.
     *
     * Updates the delegation status to Revoked and records revocation timestamp.
     * The delegation is immediately excluded from active queries.
     *
     * @param string $id The delegation to revoke
     */
    #[Override()]
    public function revoke(string $id): void
    {
        DelegationModel::on($this->connection)
            ->where('id', $id)
            ->update([
                'state' => DelegationState::Revoked,
                'revoked_at' => now(),
            ]);
    }

    /**
     * Remove old expired and revoked delegations.
     *
     * Deletes delegation records that are no longer needed for authorization
     * or audit purposes, based on configured retention periods. This prevents
     * unbounded table growth.
     *
     * @return int Number of delegations removed
     */
    #[Override()]
    public function cleanup(): int
    {
        /** @var int $retentionDays */
        $retentionDays = config('patrol.delegation.retention_days', 90);

        $deleted = DelegationModel::on($this->connection)
            ->where(function ($query) use ($retentionDays): void {
                // Remove expired delegations older than retention period
                $query->where('state', DelegationState::Expired)
                    ->where('expires_at', '<', now()->subDays($retentionDays));
            })
            ->orWhere(function ($query) use ($retentionDays): void {
                // Remove revoked delegations older than retention period
                $query->where('state', DelegationState::Revoked)
                    ->where('revoked_at', '<', now()->subDays($retentionDays));
            })
            ->forceDelete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * Hydrate a delegation value object from an Eloquent model.
     *
     * Converts the Eloquent model to a domain value object with proper type conversion
     * for scope, dates, and metadata fields.
     *
     * @param  DelegationModel $model The Eloquent model to convert
     * @return Delegation      The reconstituted delegation value object
     */
    private function hydrate(DelegationModel $model): Delegation
    {
        $scope = $model->getAttribute('scope');
        $scope = is_array($scope) ? $scope : [];

        $resources = is_array($scope['resources'] ?? null) ? $scope['resources'] : [];
        $actions = is_array($scope['actions'] ?? null) ? $scope['actions'] : [];

        /** @var array<string> $resources */
        /** @var array<string> $actions */
        $id = $model->getAttribute('id');
        $delegatorId = $model->getAttribute('delegator_id');
        $delegateId = $model->getAttribute('delegate_id');
        $isTransitive = $model->getAttribute('is_transitive');
        $state = $model->getAttribute('state');
        $metadata = $model->getAttribute('metadata');

        $createdAt = $model->getAttribute('created_at');
        $createdAtString = $createdAt && is_object($createdAt) && method_exists($createdAt, 'toDateTimeString')
            ? $createdAt->toDateTimeString()
            : now()->toDateTimeString();

        $expiresAt = $model->getAttribute('expires_at');
        $expiresAtValue = $expiresAt && is_object($expiresAt) && method_exists($expiresAt, 'toDateTimeString')
            // @phpstan-ignore argument.type (verified via method_exists check)
            ? new DateTimeImmutable($expiresAt->toDateTimeString())
            : null;

        if (!is_array($metadata)) {
            $metadata = [];
        }

        /** @var array<string, mixed> $metadata */

        return new Delegation(
            // @phpstan-ignore cast.string (id from database is int/string, casting for safety)
            id: is_string($id) ? $id : (string) $id,
            delegatorId: is_string($delegatorId) ? $delegatorId : '',
            delegateId: is_string($delegateId) ? $delegateId : '',
            scope: new DelegationScope(
                resources: $resources,
                actions: $actions,
                domain: array_key_exists('domain', $scope) && is_string($scope['domain']) ? $scope['domain'] : null,
            ),
            // @phpstan-ignore argument.type (createdAtString verified as string via runtime check above)
            createdAt: new DateTimeImmutable($createdAtString),
            expiresAt: $expiresAtValue,
            isTransitive: is_bool($isTransitive) && $isTransitive,
            status: $state instanceof DelegationState ? $state : DelegationState::Active,
            metadata: $metadata,
        );
    }
}
