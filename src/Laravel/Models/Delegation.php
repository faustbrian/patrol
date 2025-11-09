<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;
use Patrol\Core\ValueObjects\DelegationState;

/**
 * Eloquent model representing a permission delegation between users.
 *
 * Tracks temporary permission grants where one user (delegator) delegates
 * specific permissions to another user (delegate). Supports expiration,
 * revocation tracking, and transitive delegation chains. Used by the
 * DelegationManager for persistence and query operations.
 *
 * Database schema:
 * - id: Unique delegation identifier (UUID/ULID/int based on config)
 * - delegator_id: User who grants permissions
 * - delegate_id: User who receives permissions
 * - scope: JSON array of resources and actions being delegated
 * - expires_at: Optional expiration timestamp
 * - is_transitive: Whether delegate can re-delegate these permissions
 * - state: Current delegation state (active, expired, revoked)
 * - metadata: Additional context as JSON
 * - revoked_at: Timestamp when delegation was revoked
 * - revoked_by: User ID who performed the revocation
 *
 * @property string                 $delegate_id   User ID receiving permissions
 * @property string                 $delegator_id  User ID granting permissions
 * @property null|DateTimeInterface $expires_at    Optional expiration timestamp
 * @property string                 $id            Unique delegation identifier
 * @property bool                   $is_transitive Whether re-delegation is allowed
 * @property array<string, mixed>   $metadata      Additional context information
 * @property null|DateTimeInterface $revoked_at    Revocation timestamp
 * @property null|string            $revoked_by    User ID who revoked this delegation
 * @property array<string, mixed>   $scope         Resources and actions being delegated
 * @property DelegationState        $state         Current state of the delegation
 */
/**
 * @phpstan-type TFactory Factory<self>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Delegation extends PatrolModel
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    /**
     * The database table associated with this model.
     *
     * @var null|string
     */
    protected $table = 'patrol_delegations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'delegator_id',
        'delegate_id',
        'scope',
        'expires_at',
        'is_transitive',
        'state',
        'metadata',
        'revoked_at',
        'revoked_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override()]
    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'expires_at' => 'datetime',
            'is_transitive' => 'bool',
            'state' => DelegationState::class,
            'metadata' => 'array',
            'revoked_at' => 'datetime',
        ];
    }
}
