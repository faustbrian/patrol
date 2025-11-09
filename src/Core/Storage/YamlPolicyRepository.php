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
use Symfony\Component\Yaml\Yaml;

use function is_array;

/**
 * YAML file-based policy repository.
 *
 * Uses Symfony YAML component for parsing and encoding. Provides excellent
 * readability and supports complex nested structures.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class YamlPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode YAML content to array.
     *
     * Parses YAML-formatted policy data into a PHP array structure for processing.
     * Returns null on parse failures or non-array results to signal invalid content.
     *
     * @param  string                                                                                                                      $content YAML-formatted string containing policy definitions
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Parsed policy data or null if parsing fails
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $data = Yaml::parse($content);

            if (!is_array($data)) {
                return null;
            }

            return $data; // @phpstan-ignore return.type
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to YAML content.
     *
     * Converts policy array structure to YAML format for file storage. Uses
     * 4-level inline depth and 2-space indentation for readable output.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 YAML-formatted string representation
     */
    #[Override()]
    protected function encode(array $data): string
    {
        return Yaml::dump($data, 4, 2);
    }

    /**
     * Get YAML file extension.
     *
     * Returns the file extension used for YAML policy files in this repository.
     *
     * @return string File extension without leading dot
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'yaml';
    }
}
