<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\PolicyVersioning;

use Patrol\Core\ValueObjects\Policy;

use function assert;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function serialize;
use function unserialize;

/**
 * Immutable value object representing a versioned snapshot of a policy.
 *
 * Provides policy versioning capabilities for audit trails, rollback functionality,
 * and change tracking. Each version captures the complete policy state along with
 * metadata about when and why the change was made.
 *
 * Supports serialization to/from database storage while maintaining policy integrity.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyVersion
{
    /**
     * Create a new policy version instance.
     *
     * @param int                  $version     The sequential version number for this policy snapshot. Higher
     *                                          numbers represent more recent versions. Used for ordering and
     *                                          identifying specific policy states in the version history.
     * @param Policy               $policy      The complete policy object being versioned, containing all rules
     *                                          and authorization logic. Serialized for storage and deserialized
     *                                          when retrieving historical versions for comparison or rollback.
     * @param string               $createdAt   ISO 8601 timestamp indicating when this version was created.
     *                                          Provides chronological ordering and audit trail capabilities
     *                                          for compliance and change tracking requirements.
     * @param null|string          $description Optional human-readable description of the changes made in this
     *                                          version. Useful for documenting the reason for policy updates,
     *                                          linking to tickets, or explaining business logic changes.
     * @param array<string, mixed> $metadata    additional structured data about this version such as the
     *                                          user who made the change, related tickets, deployment information,
     *                                          or any custom fields needed for organizational processes
     */
    public function __construct(
        public int $version,
        public Policy $policy,
        public string $createdAt,
        public ?string $description = null,
        public array $metadata = [],
    ) {}

    /**
     * Reconstruct a PolicyVersion from database row data.
     *
     * Deserializes the policy object and JSON metadata from storage format back
     * into a PolicyVersion value object. Handles missing optional fields gracefully.
     *
     * @param  array<string, mixed> $data The database row data containing version, policy, created_at,
     *                                    description, and metadata fields
     * @return self                 the reconstructed PolicyVersion instance
     */
    public static function fromArray(array $data): self
    {
        $version = $data['version'];
        assert(is_int($version));

        $policyData = $data['policy'];
        assert(is_string($policyData));
        $policy = unserialize($policyData);
        assert($policy instanceof Policy);

        $createdAt = $data['created_at'];
        assert(is_string($createdAt));

        $description = $data['description'] ?? null;
        assert($description === null || is_string($description));

        $metadataJson = $data['metadata'] ?? '[]';
        assert(is_string($metadataJson));
        $metadata = json_decode($metadataJson, true);
        assert(is_array($metadata));

        /** @var array<string, mixed> $metadata */

        return new self(
            version: $version,
            policy: $policy,
            createdAt: $createdAt,
            description: $description,
            metadata: $metadata,
        );
    }

    /**
     * Convert this PolicyVersion to an array suitable for database storage.
     *
     * Serializes the policy object and encodes metadata as JSON for persistence.
     * The resulting array matches the database schema expected by PolicyVersionRepository.
     *
     * @return array<string, mixed> associative array with keys: version, policy, created_at,
     *                              description, and metadata ready for database insertion
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'policy' => serialize($this->policy),
            'created_at' => $this->createdAt,
            'description' => $this->description,
            'metadata' => json_encode($this->metadata),
        ];
    }
}
