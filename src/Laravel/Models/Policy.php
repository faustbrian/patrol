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
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Patrol\Core\ValueObjects\Effect;

/**
 * Eloquent model representing an authorization policy rule.
 *
 * Defines access control rules that determine whether a subject (user/role)
 * can perform an action on a resource. Policies are evaluated in priority
 * order, with higher priority rules taking precedence. Supports wildcards
 * for flexible matching and optional domain/tenant isolation.
 *
 * Database schema:
 * - id: Unique policy identifier (UUID/ULID/int based on config)
 * - subject: Subject identifier or wildcard (e.g., "user:123", "role:admin", "*")
 * - resource: Resource identifier or wildcard (e.g., "document:456", "post:*", "*")
 * - action: Action identifier or wildcard (e.g., "read", "write", "*")
 * - effect: Allow or Deny the action
 * - priority: Evaluation priority (higher values evaluated first)
 * - domain: Optional domain/tenant identifier for multi-tenancy
 *
 * @property string      $action   Action pattern or wildcard
 * @property null|string $domain   Optional domain/tenant identifier
 * @property Effect      $effect   Whether to allow or deny the action
 * @property string      $id       Unique policy rule identifier
 * @property int         $priority Evaluation priority (higher first)
 * @property null|string $resource Resource pattern or wildcard
 * @property string      $subject  Subject pattern (user/role ID or wildcard)
 */
/**
 * @phpstan-type TFactory Factory<self>
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Policy extends PatrolModel
{
    /** @use HasFactory<TFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * The database table associated with this model.
     *
     * @var null|string
     */
    protected $table = 'patrol_policies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject',
        'resource',
        'action',
        'effect',
        'priority',
        'domain',
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
            'effect' => Effect::class,
            'priority' => 'int',
        ];
    }
}
