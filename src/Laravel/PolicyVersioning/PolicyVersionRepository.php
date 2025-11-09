<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\PolicyVersioning;

use Illuminate\Support\Facades\DB;
use Patrol\Core\ValueObjects\Policy;
use stdClass;

use function array_map;
use function now;

/**
 * Repository for persisting and retrieving versioned policy snapshots.
 *
 * Manages the storage and retrieval of policy versions in a database table,
 * enabling version control, audit trails, and rollback capabilities for
 * authorization policies. Automatically handles version numbering and provides
 * methods for querying historical policy states.
 *
 * ```php
 * $repository = new PolicyVersionRepository(
 *     table: 'custom_policy_versions',
 *     connection: 'audit_db'
 * );
 *
 * $version = $repository->save($policy, 'Added admin permissions');
 * $previous = $repository->get($version->version - 1);
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyVersionRepository
{
    /**
     * Create a new policy version repository instance.
     *
     * @param string $table      The database table name for storing policy versions. Should have
     *                           columns: version (int), policy (text), created_at (datetime),
     *                           description (text, nullable), and metadata (json/text).
     * @param string $connection The Laravel database connection name to use. Allows storing
     *                           policy versions in a separate database for compliance or
     *                           isolation requirements.
     */
    public function __construct(
        private string $table = 'patrol_policy_versions',
        private string $connection = 'default',
    ) {}

    /**
     * Save a new policy version to the database.
     *
     * Creates a new version record with an auto-incremented version number,
     * the serialized policy, and optional metadata. Returns the created
     * PolicyVersion object for immediate use.
     *
     * @param  Policy               $policy      The policy to version and store
     * @param  null|string          $description Optional description of changes in this version
     * @param  array<string, mixed> $metadata    Optional metadata about this version (author, ticket ID, etc.)
     * @return PolicyVersion        the created policy version with assigned version number
     */
    public function save(Policy $policy, ?string $description = null, array $metadata = []): PolicyVersion
    {
        $version = $this->getNextVersion();

        $policyVersion = new PolicyVersion(
            version: $version,
            policy: $policy,
            createdAt: now()->toDateTimeString(),
            description: $description,
            metadata: $metadata,
        );

        DB::connection($this->connection)
            ->table($this->table)
            ->insert($policyVersion->toArray());

        return $policyVersion;
    }

    /**
     * Retrieve a specific policy version by its version number.
     *
     * Fetches and deserializes the policy version from storage. Useful for
     * comparing versions, viewing historical policies, or preparing for rollback.
     *
     * @param  int                $version The version number to retrieve
     * @return null|PolicyVersion The policy version if found, null otherwise
     */
    public function get(int $version): ?PolicyVersion
    {
        $row = DB::connection($this->connection)
            ->table($this->table)
            ->where('version', $version)
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return PolicyVersion::fromArray($data);
    }

    /**
     * Retrieve the most recent policy version.
     *
     * Returns the latest version by version number. Useful for loading the
     * current active policy or displaying recent changes.
     *
     * @return null|PolicyVersion The latest policy version if any exist, null otherwise
     */
    public function getLatest(): ?PolicyVersion
    {
        $row = DB::connection($this->connection)
            ->table($this->table)
            ->orderBy('version', 'desc')
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return PolicyVersion::fromArray($data);
    }

    /**
     * Retrieve all policy versions ordered from newest to oldest.
     *
     * Returns the complete version history for audit review, change analysis,
     * or displaying a version timeline. Consider pagination for large histories.
     *
     * @return array<int, PolicyVersion> Array of all policy versions in descending order
     */
    public function getAll(): array
    {
        $rows = DB::connection($this->connection)
            ->table($this->table)
            ->orderBy('version', 'desc')
            ->get();

        return array_map(
            function (stdClass $row): PolicyVersion {
                /** @var array<string, mixed> $data */
                $data = (array) $row;

                return PolicyVersion::fromArray($data);
            },
            $rows->all(),
        );
    }

    /**
     * Retrieve a policy version for rollback purposes.
     *
     * Convenience method that retrieves a specific version, typically used
     * when restoring a previous policy state. Currently delegates to get()
     * but provides semantic clarity for rollback operations.
     *
     * @param  int                $version The version number to roll back to
     * @return null|PolicyVersion The policy version to restore, or null if not found
     */
    public function rollback(int $version): ?PolicyVersion
    {
        return $this->get($version);
    }

    /**
     * Calculate the next sequential version number.
     *
     * Queries the database for the highest existing version number and increments
     * it by one. Returns 1 if no versions exist yet.
     *
     * @return int The next version number to use for new policy versions
     */
    private function getNextVersion(): int
    {
        /** @var null|int $latest */
        $latest = DB::connection($this->connection)
            ->table($this->table)
            ->max('version');

        return ($latest ?? 0) + 1;
    }
}
