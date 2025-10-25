<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Repositories;

use JsonException;
use Override;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Exceptions\InvalidPolicyFileFormatException;
use Patrol\Core\Exceptions\PolicyFileNotFoundException;
use Patrol\Core\Exceptions\PolicyFileUnreadableException;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use function array_key_exists;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function throw_if;
use function throw_unless;

/**
 * File-based policy repository implementation using JSON storage.
 *
 * Loads authorization policies from a JSON file at construction time and provides
 * filtered policy retrieval based on subject and resource matching. Supports both
 * read and write operations for managing policies in JSON format.
 *
 * JSON structure:
 * ```json
 * [
 *   {
 *     "subject": "role:admin",
 *     "resource": "document:*",
 *     "action": "delete",
 *     "effect": "Allow",
 *     "priority": 100,
 *     "domain": "organization:acme"
 *   }
 * ]
 * ```
 *
 * @see PolicyRepositoryInterface For the repository contract
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class FilePolicyRepository implements PolicyRepositoryInterface
{
    /**
     * Cached policy data loaded from the JSON file.
     *
     * Structure: Array of policy definitions with subject, resource, action, effect, and optional priority/domain.
     *
     * @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}>
     */
    private array $policies;

    /**
     * Create a new file-based policy repository from a JSON file.
     *
     * Loads and validates the policy file at construction time, failing fast with exceptions
     * if the file is missing or contains invalid JSON. The policies are cached in memory for
     * the lifetime of the repository instance, eliminating file I/O overhead during authorization
     * checks. This eager loading strategy ensures predictable performance characteristics.
     *
     * @param string $filePath Absolute or relative path to the JSON policy file containing
     *                         an array of policy rule definitions. Must be readable and contain
     *                         valid JSON with the required structure (subject, action, effect).
     *
     * @throws InvalidPolicyFileFormatException If the file contains invalid JSON or malformed policy structure
     * @throws PolicyFileNotFoundException      If the specified file does not exist at the given path
     * @throws PolicyFileUnreadableException    If the file exists but cannot be read due to permissions or I/O errors
     */
    public function __construct(
        private string $filePath,
    ) {
        throw_unless(file_exists($filePath), PolicyFileNotFoundException::create($filePath));

        $content = file_get_contents($filePath);
        throw_if($content === false, PolicyFileUnreadableException::create($filePath));

        $data = json_decode($content, true);

        throw_unless(is_array($data), InvalidPolicyFileFormatException::create($filePath));

        /** @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data */
        $this->policies = $data;
    }

    /**
     * Retrieve applicable policies filtered by subject and resource matching.
     *
     * Filters the loaded policies to identify rules relevant to the authorization context,
     * constructing value objects from the raw JSON data. Applies subject and resource matching
     * using wildcard support and null resource handling. Returns a Policy containing all matched
     * rules, which the caller can then evaluate using a PolicyEvaluator.
     *
     * Filtering strategy:
     * - Subject: Exact match or wildcard (*)
     * - Resource: Exact match, wildcard (*), or null (type-based permission)
     *
     * @param  Subject  $subject  The subject requesting access for filtering relevant rules
     * @param  resource $resource The resource being accessed for filtering relevant rules
     * @return Policy   A policy containing all matching rules as PolicyRule value objects
     */
    #[Override()]
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy
    {
        $rules = [];

        foreach ($this->policies as $policyData) {
            if ($this->matches($subject, $resource, $policyData)) {
                $effectName = $policyData['effect'];
                $effect = $effectName === 'Allow' ? Effect::Allow : Effect::Deny;

                $rules[] = new PolicyRule(
                    subject: $policyData['subject'],
                    resource: $policyData['resource'] ?? null,
                    action: $policyData['action'],
                    effect: $effect,
                    priority: new Priority($policyData['priority'] ?? 1),
                    domain: array_key_exists('domain', $policyData) ? new Domain($policyData['domain']) : null,
                );
            }
        }

        return new Policy($rules);
    }

    /**
     * Save policy to JSON file.
     *
     * Persists all policy rules to the JSON file, overwriting existing content with
     * formatted JSON. Creates the parent directory structure if it doesn't exist.
     * The output is formatted with pretty-printing and unescaped slashes for better
     * readability and version control diff clarity.
     *
     * @param Policy $policy The policy containing all rules to be persisted to the file
     *
     * @throws JsonException If JSON encoding fails (should never occur with valid Policy objects)
     */
    #[Override()]
    public function save(Policy $policy): void
    {
        $dirPath = dirname($this->filePath);

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0o755, true);
        }

        $data = [];

        foreach ($policy->rules as $rule) {
            $policyData = [
                'subject' => $rule->subject,
                'action' => $rule->action,
                'effect' => $rule->effect->value,
                'priority' => $rule->priority->value,
            ];

            if ($rule->resource !== null) {
                $policyData['resource'] = $rule->resource;
            }

            if ($rule->domain !== null) {
                $policyData['domain'] = $rule->domain->id;
            }

            $data[] = $policyData;
        }

        $content = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        file_put_contents($this->filePath, $content);
    }

    /**
     * Determine if a policy definition matches the authorization context.
     *
     * Implements simple subject and resource matching with wildcard support to filter
     * policies before constructing value objects. Both conditions must be true for a
     * policy to be considered applicable. Null resources are treated as matching any
     * resource, enabling type-based permissions that aren't tied to specific instances.
     *
     * @param  Subject                                                                   $subject    The subject from the authorization request
     * @param  resource                                                                  $resource   The resource from the authorization request
     * @param  array{subject: string, resource?: string, action: string, effect: string} $policyData The raw policy definition from JSON
     * @return bool                                                                      True if both subject and resource match the policy definition
     */
    private function matches(Subject $subject, Resource $resource, array $policyData): bool
    {
        // Subject matches via exact ID or wildcard
        $subjectMatch = $policyData['subject'] === $subject->id || $policyData['subject'] === '*';

        // Resource matches via null (type-based), exact ID, or wildcard
        $resourceMatch = !array_key_exists('resource', $policyData)
            || $policyData['resource'] === $resource->id
            || $policyData['resource'] === '*';

        return $subjectMatch && $resourceMatch;
    }
}
