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
use Yosymfony\Toml\Toml;
use Yosymfony\Toml\TomlBuilder;

use function array_key_exists;
use function is_array;

/**
 * TOML file-based policy repository.
 *
 * Uses yosymfony/Toml package for parsing and building TOML files. Provides
 * clean, human-readable syntax for configuration with strong typing support.
 * Policies are stored under [[policies]] table array syntax.
 *
 * @see AbstractFilePolicyRepository For common policy operations
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class TomlPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode TOML content to array.
     *
     * Parses TOML and extracts policies from [[policies]] table array. Returns
     * null on parse errors to enable graceful degradation in multi-file mode.
     * Handles both explicit [[policies]] arrays and direct policy objects.
     *
     * @param  string                                                                                                                      $content Raw TOML string to decode
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Decoded data or null on error
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $data = Toml::parse($content);

            // TOML structure: [[policies]] creates array under 'policies' key
            if (is_array($data) && array_key_exists('policies', $data) && is_array($data['policies'])) {
                /** @phpstan-ignore return.type (TOML parser returns correct structure, validated at runtime) */
                return $data['policies'];
            }

            /** @phpstan-ignore return.type (TOML parser returns array structure) */
            return is_array($data) ? $data : null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to TOML content.
     *
     * Builds TOML using [[policies]] table array syntax where each policy
     * is a separate table entry. Produces human-readable configuration format.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 Formatted TOML string
     */
    #[Override()]
    protected function encode(array $data): string
    {
        $builder = new TomlBuilder();

        foreach ($data as $policyData) {
            $builder->addArrayOfTable('policies');

            foreach ($policyData as $key => $value) {
                $builder->addValue($key, $value);
            }
        }

        return $builder->getTomlString();
    }

    /**
     * Get TOML file extension.
     *
     * @return string Always returns 'toml'
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'toml';
    }
}
