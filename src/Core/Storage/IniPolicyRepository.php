<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Exception;
use Override;

use const INI_SCANNER_TYPED;

use function array_key_exists;
use function is_array;
use function parse_ini_string;
use function sprintf;

/**
 * INI file-based policy repository.
 *
 * Uses PHP's native parse_ini_string() for parsing INI files. Provides
 * excellent compatibility with traditional configuration files and zero
 * external dependencies. Each policy is stored as a section.
 *
 * INI structure:
 * [policy_0]
 * subject = user:alice
 * resource = document:123
 * action = read
 * effect = Allow
 * priority = 1
 * domain = engineering
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see AbstractFilePolicyRepository For common policy operations
 */
final readonly class IniPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode INI content to array.
     *
     * Parses INI-formatted policy data into a PHP array structure for processing.
     * Returns null on parse failures to signal invalid content. Each INI section
     * represents one policy rule.
     *
     * @param  string                                                                                                                      $content INI-formatted string containing policy definitions
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Parsed policy data or null if parsing fails
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $data = parse_ini_string($content, true, INI_SCANNER_TYPED);

            // @codeCoverageIgnoreStart
            if (!is_array($data)) {
                return null; // @codeCoverageIgnoreEnd
            }

            $policies = [];

            foreach ($data as $values) {
                if (!is_array($values)) {
                    continue;
                }

                // Build policy array from INI section
                $policy = [
                    'subject' => $values['subject'] ?? '',
                    'action' => $values['action'] ?? '',
                    'effect' => $values['effect'] ?? '',
                ];

                // Add optional fields only if they have values
                if (array_key_exists('resource', $values) && $values['resource'] !== '') {
                    $policy['resource'] = $values['resource'];
                }

                if (array_key_exists('priority', $values) && $values['priority'] !== '') {
                    /** @var int|string $priority */
                    $priority = $values['priority'];
                    $policy['priority'] = (int) $priority;
                }

                if (array_key_exists('domain', $values) && $values['domain'] !== '') {
                    $policy['domain'] = $values['domain'];
                }

                $policies[] = $policy;
            }

            /** @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $policies */
            return $policies;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to INI content.
     *
     * Converts policy array structure to INI format for file storage. Each policy
     * becomes a numbered section with key-value pairs.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 INI-formatted string representation
     */
    #[Override()]
    protected function encode(array $data): string
    {
        $ini = '';

        foreach ($data as $index => $policy) {
            $ini .= sprintf("[policy_%d]\n", $index);
            $ini .= sprintf("subject = \"%s\"\n", $policy['subject']);

            if (array_key_exists('resource', $policy)) {
                $ini .= sprintf("resource = \"%s\"\n", $policy['resource']);
            }

            $ini .= sprintf("action = \"%s\"\n", $policy['action']);
            $ini .= sprintf("effect = \"%s\"\n", $policy['effect']);

            if (array_key_exists('priority', $policy)) {
                $ini .= sprintf("priority = %d\n", $policy['priority']);
            }

            if (array_key_exists('domain', $policy)) {
                $ini .= sprintf("domain = \"%s\"\n", $policy['domain']);
            }

            $ini .= "\n";
        }

        return $ini;
    }

    /**
     * Get INI file extension.
     *
     * Returns the file extension used for INI policy files in this repository.
     *
     * @return string File extension without leading dot
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'ini';
    }
}
