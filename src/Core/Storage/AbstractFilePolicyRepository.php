<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Override;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\FileMode;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_key_exists;
use function array_key_first;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function is_dir;
use function mkdir;
use function sprintf;

/**
 * Abstract base for file-based policy repositories.
 *
 * Provides shared logic for loading, filtering, and caching policies across
 * different file formats (JSON, YAML, XML, TOML, Serialized). Subclasses
 * implement format-specific encode/decode operations.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract readonly class AbstractFilePolicyRepository extends FileStorageBase implements PolicyRepositoryInterface
{
    /**
     * Retrieve applicable policies for subject-resource pair.
     *
     * Loads all policies from storage, filters by subject/resource wildcards,
     * and assembles them into a unified Policy object with priority-ordered rules.
     *
     * @param  Subject  $subject  The subject requesting access (user, role, etc.)
     * @param  resource $resource The resource being accessed
     * @return Policy   Aggregated policy containing all matching rules
     */
    #[Override()]
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $policies = $this->loadPolicies();
        $rules = [];

        foreach ($policies as $policyData) {
            if ($this->matches($subject, $resource, $policyData)) {
                $effectName = $policyData['effect'];
                $effect = $effectName === 'Allow' ? Effect::Allow : Effect::Deny;

                $priority = (int) ($policyData['priority'] ?? 1);

                $rules[] = new PolicyRule(
                    subject: $policyData['subject'],
                    resource: $policyData['resource'] ?? null,
                    action: $policyData['action'],
                    effect: $effect,
                    priority: new Priority($priority),
                    domain: array_key_exists('domain', $policyData) ? new Domain($policyData['domain']) : null,
                );
            }
        }

        return new Policy($rules);
    }

    /**
     * Persist policy to storage.
     *
     * Delegates to single-file or multi-file save strategy based on configured
     * file mode. Creates parent directories and version subdirectories as needed.
     *
     * @param Policy $policy The policy to persist with all rules
     */
    #[Override()]
    public function save(Policy $policy): void
    {
        if ($this->fileMode === FileMode::Single) {
            $this->saveSingleFile($policy);
        } else {
            $this->saveMultipleFiles($policy);
        }
    }

    /**
     * Load all policies from storage with caching.
     *
     * Checks in-memory cache first to avoid repeated file I/O. Cache key includes
     * version and file mode to ensure cache invalidation on configuration changes.
     *
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Policy data structures
     */
    protected function loadPolicies(): array
    {
        $cacheKey = $this->getCacheKey();

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if ($this->fileMode === FileMode::Single) {
            return $this->loadSingleFile();
        }

        return $this->loadMultipleFiles();
    }

    /**
     * Load policies from single file.
     *
     * Returns empty array if file doesn't exist or is unreadable. Delegates
     * format-specific parsing to decode() method.
     *
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Policy data or empty array
     */
    protected function loadSingleFile(): array
    {
        $filePath = $this->buildPath('policies', 'policies', $this->getExtension());

        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);

        // @codeCoverageIgnoreStart
        if ($content === false) {
            return [];
        }

        // @codeCoverageIgnoreEnd

        return $this->decode($content) ?? [];
    }

    /**
     * Load policies from multiple files.
     *
     * Scans directory for files matching the format extension. Supports both
     * individual policy objects and arrays of policies per file. Skips unreadable
     * or malformed files to enable partial recovery from corruption.
     *
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Aggregated policy data
     */
    protected function loadMultipleFiles(): array
    {
        $dirPath = $this->buildDirectoryPath('policies');
        $policies = [];

        if (!is_dir($dirPath)) {
            return [];
        }

        $files = glob(sprintf('%s/*.%s', $dirPath, $this->getExtension()));

        // @codeCoverageIgnoreStart
        if ($files === false) {
            return [];
        }

        // @codeCoverageIgnoreEnd

        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);

            // @codeCoverageIgnoreStart
            if ($content === false) {
                continue;
            }

            /** @codeCoverageIgnoreEnd */
            $data = $this->decode($content);

            // decode() returns array<int, array{subject: string, ...}>
            // But individual policy files may contain a single object with string keys
            if ($data !== null && $data !== []) {
                $firstKey = array_key_first($data);

                // If first key is string 'subject', it's a single policy object - wrap it
                // @phpstan-ignore identical.alwaysFalse (firstKey can be int or string depending on file format)
                if ($firstKey === 'subject') {
                    /** @var array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string} $data */
                    $policies[] = $data;
                } else {
                    // It's an array of policies
                    foreach ($data as $policy) {
                        $policies[] = $policy;
                    }
                }
            }
        }

        return $policies;
    }

    /**
     * Decode file contents to array.
     *
     * Format-specific deserialization implemented by subclasses. Returns null
     * on parse errors to enable graceful degradation in multi-file mode.
     *
     * @param  string                                                                                                                      $content Raw file contents to decode
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Decoded policy data or null on error
     */
    abstract protected function decode(string $content): ?array;

    /**
     * Encode array to file contents.
     *
     * Format-specific serialization implemented by subclasses. Should produce
     * human-readable output with consistent formatting where applicable.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 Encoded file contents ready for writing
     */
    abstract protected function encode(array $data): string;

    /**
     * Get file extension for this format.
     *
     * Used in path construction for single-file and multi-file modes.
     * Extension should match standard conventions for the format.
     *
     * @return string Extension without dot (e.g., 'json', 'yaml', 'xml')
     */
    abstract protected function getExtension(): string;

    /**
     * Determine if policy matches authorization context.
     *
     * Implements wildcard matching for subject and resource fields. A policy
     * matches if both subject and resource match, supporting '*' as wildcard.
     *
     * @param  Subject                                                                   $subject    Subject from authorization request
     * @param  resource                                                                  $resource   Resource from authorization request
     * @param  array{subject: string, resource?: string, action: string, effect: string} $policyData Policy data from storage
     * @return bool                                                                      True if policy applies to this context
     */
    private function matches(Subject $subject, Resource $resource, array $policyData): bool
    {
        $subjectMatch = $policyData['subject'] === $subject->id || $policyData['subject'] === '*';
        $resourceMatch = !array_key_exists('resource', $policyData)
            || $policyData['resource'] === $resource->id
            || $policyData['resource'] === '*';

        return $subjectMatch && $resourceMatch;
    }

    /**
     * Build directory path for multi-file mode.
     *
     * Constructs path including version subdirectory if versioning is enabled.
     * Used for glob operations and directory creation.
     *
     * @param  string $type Storage type (e.g., 'policies', 'delegations')
     * @return string Absolute directory path
     */
    private function buildDirectoryPath(string $type): string
    {
        $parts = [$this->basePath, $type];

        // @codeCoverageIgnoreStart
        if ($this->versioningEnabled) {
            $version = $this->resolveVersion($type);

            if ($version !== null) {
                $parts[] = $version;
            }
        }

        // @codeCoverageIgnoreEnd

        return implode('/', $parts);
    }

    /**
     * Generate cache key for policy storage.
     *
     * Combines version and file mode to ensure cache invalidation when
     * configuration changes or version switches occur.
     *
     * @return string Unique cache identifier
     */
    private function getCacheKey(): string
    {
        return sprintf(
            'policies:%s:%s',
            $this->resolveVersion('policies') ?? 'latest',
            $this->fileMode->value,
        );
    }

    /**
     * Save all policies to a single file.
     *
     * Creates parent directories with 0755 permissions if needed. Overwrites
     * existing file with complete policy set.
     *
     * @param Policy $policy Complete policy set to persist
     */
    private function saveSingleFile(Policy $policy): void
    {
        $filePath = $this->buildPath('policies', 'policies', $this->getExtension());
        $dirPath = dirname($filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0o755, true);
        }

        $data = $this->convertPolicyToArray($policy);
        $content = $this->encode($data);

        file_put_contents($filePath, $content);
    }

    /**
     * Save each policy rule to individual file.
     *
     * Creates separate file per rule using index-based naming (policy_0, policy_1, etc.).
     * Creates parent directories with 0755 permissions if needed.
     *
     * @param Policy $policy Policy containing rules to persist separately
     */
    private function saveMultipleFiles(Policy $policy): void
    {
        $dirPath = $this->buildDirectoryPath('policies');

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0o755, true);
        }

        foreach ($policy->rules as $index => $rule) {
            $filePath = sprintf('%s/policy_%d.%s', $dirPath, $index, $this->getExtension());
            $data = [$this->convertRuleToArray($rule)];
            $content = $this->encode($data);

            file_put_contents($filePath, $content);
        }
    }

    /**
     * Convert policy to array format for serialization.
     *
     * Transforms all rules into storage-compatible array structures. Preserves
     * all rule properties including optional resource and domain fields.
     *
     * @param  Policy                                                                                                                 $policy Policy to serialize
     * @return array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Serializable policy data
     */
    private function convertPolicyToArray(Policy $policy): array
    {
        $data = [];

        foreach ($policy->rules as $rule) {
            $data[] = $this->convertRuleToArray($rule);
        }

        return $data;
    }

    /**
     * Convert single policy rule to array format for serialization.
     *
     * Extracts all rule properties with appropriate type conversions. Omits
     * null optional fields to reduce storage size and improve readability.
     *
     * @param  PolicyRule                                                                                                 $rule Policy rule to serialize
     * @return array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string} Serializable rule data
     */
    private function convertRuleToArray(PolicyRule $rule): array
    {
        $data = [
            'subject' => $rule->subject,
            'action' => $rule->action,
            'effect' => $rule->effect->value,
            'priority' => $rule->priority->value,
        ];

        if ($rule->resource !== null) {
            $data['resource'] = $rule->resource;
        }

        if ($rule->domain instanceof Domain) {
            $data['domain'] = $rule->domain->id;
        }

        return $data;
    }
}
