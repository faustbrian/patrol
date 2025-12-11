<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Storage;

use Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use Override;

use function array_key_exists;

/**
 * CSV file-based policy repository.
 *
 * Uses League CSV package for parsing and writing CSV files. Provides excellent
 * compatibility with spreadsheet tools and data analysis workflows. Suitable for
 * configuration management and bulk policy imports/exports.
 *
 * CSV structure: subject,resource,action,effect,priority,domain
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 * @see AbstractFilePolicyRepository For common policy operations
 */
final readonly class CsvPolicyRepository extends AbstractFilePolicyRepository
{
    /**
     * Decode CSV content to array.
     *
     * Parses CSV-formatted policy data into a PHP array structure for processing.
     * Returns null on parse failures to signal invalid content. Header row is
     * automatically detected and used for associative array mapping.
     *
     * @param  string                                                                                                                      $content CSV-formatted string containing policy definitions
     * @return null|array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> Parsed policy data or null if parsing fails
     */
    #[Override()]
    protected function decode(string $content): ?array
    {
        try {
            $csv = Reader::createFromString($content);
            $csv->setHeaderOffset(0);

            $records = [];

            foreach ($csv->getRecords() as $record) {
                /** @var array<string, mixed> $record */

                // Build policy array from CSV record
                $policy = [
                    'subject' => $record['subject'] ?? '',
                    'action' => $record['action'] ?? '',
                    'effect' => $record['effect'] ?? '',
                ];

                // Add optional fields only if they have values
                if (array_key_exists('resource', $record) && $record['resource'] !== '') {
                    $policy['resource'] = $record['resource'];
                }

                if (array_key_exists('priority', $record) && $record['priority'] !== '') {
                    /** @var int|string $priority */
                    $priority = $record['priority'];
                    $policy['priority'] = (int) $priority;
                }

                if (array_key_exists('domain', $record) && $record['domain'] !== '') {
                    $policy['domain'] = $record['domain'];
                }

                $records[] = $policy;
            }

            /** @var array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $records */
            return $records;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encode array to CSV content.
     *
     * Converts policy array structure to CSV format for file storage. Includes
     * header row with column names for readability and compatibility with
     * spreadsheet applications.
     *
     * @param  array<int, array{subject: string, resource?: string, action: string, effect: string, priority?: int, domain?: string}> $data Policy data to encode
     * @return string                                                                                                                 CSV-formatted string representation
     */
    #[Override()]
    protected function encode(array $data): string
    {
        $csv = Writer::createFromString();

        // Insert header row
        $csv->insertOne(['subject', 'resource', 'action', 'effect', 'priority', 'domain']);

        // Insert data rows
        foreach ($data as $policy) {
            $csv->insertOne([
                $policy['subject'],
                $policy['resource'] ?? '',
                $policy['action'],
                $policy['effect'],
                array_key_exists('priority', $policy) ? (string) $policy['priority'] : '',
                $policy['domain'] ?? '',
            ]);
        }

        return $csv->toString();
    }

    /**
     * Get CSV file extension.
     *
     * Returns the file extension used for CSV policy files in this repository.
     *
     * @return string File extension without leading dot
     */
    #[Override()]
    protected function getExtension(): string
    {
        return 'csv';
    }
}
