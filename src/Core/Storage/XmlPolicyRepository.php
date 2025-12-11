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
use Saloon\XmlWrangler\XmlReader;
use Saloon\XmlWrangler\XmlWriter;

use function array_key_exists;

/**
 * XML file-based policy repository.
 *
 * Uses Saloon XML Wrangler for parsing and writing XML. Supports schema
 * validation and transformation pipelines. Policies are stored under
 * <policies><policy>...</policy></policies> structure.
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see AbstractFilePolicyRepository For common policy operations
 */
final readonly class XmlPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode XML content to array.
     *
     * Parses XML and extracts policies from <policies><policy> structure. Returns
     * null on parse errors to enable graceful degradation in multi-file mode.
     * Handles both single policy and array of policies.
     *
     * @param  string                                                                                                                      $content Raw XML string to decode
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Decoded data or null on error
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $reader = XmlReader::fromString($content);
            $data = $reader->values();

            // XML structure: <policies><policy>...</policy></policies>
            // XmlReader returns: ['policies' => ['policy' => [...]]]
            // @phpstan-ignore argument.type (XmlReader returns array, checking structure at runtime)
            if (array_key_exists('policies', $data) && array_key_exists('policy', $data['policies'])) {
                $policies = $data['policies']['policy'];

                // Handle single policy (not wrapped in array)
                // @phpstan-ignore argument.type (XML parser returns mixed array structure)
                if (array_key_exists('subject', $policies)) {
                    /** @phpstan-ignore return.type (wrapping single policy in array) */
                    return [$policies];
                }

                /** @phpstan-ignore return.type (XML parser returns correct structure, validated at runtime) */
                return $policies;
            }

            return null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to XML content using Saloon XmlWrangler.
     *
     * Wraps policy data in <policies><policy> structure. Produces well-formed,
     * human-readable XML suitable for version control and manual editing.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 Formatted XML string
     */
    #[Override()]
    protected function encode(array $data): string
    {
        $writer = new XmlWriter();

        return $writer->write('policies', [
            'policy' => $data,
        ]);
    }

    /**
     * Get XML file extension.
     *
     * @return string Always returns 'xml'
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'xml';
    }
}
