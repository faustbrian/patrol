<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Visualization;

use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;

use const PHP_EOL;

use function array_values;
use function assert;
use function collect;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Generates tabular permission matrices from authorization policies.
 *
 * Creates structured matrix representations of policy rules that show which subjects
 * can perform which actions on which resources. Supports multiple output formats
 * (data structure, HTML table, CSV) for different use cases like documentation,
 * auditing, compliance reporting, and administrative dashboards.
 *
 * The matrix format makes it easy to spot authorization patterns, identify overly
 * permissive rules, and audit access control at a glance. HTML output includes
 * color-coded action names (green for Allow, red for Deny) for quick visual scanning.
 *
 * ```php
 * // Generate raw matrix data
 * $matrix = PolicyMatrix::generate($policy);
 * $subjects = $matrix['subjects'];
 * $resources = $matrix['resources'];
 * $permissions = $matrix['permissions'];
 *
 * // Generate HTML table for display
 * $html = PolicyMatrix::toHtml($policy);
 * echo $html;
 *
 * // Generate CSV for export
 * $csv = PolicyMatrix::toCsv($policy);
 * file_put_contents('permissions.csv', $csv);
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyMatrix
{
    /**
     * Generate a permission matrix data structure from a policy.
     *
     * Extracts and organizes all policy rules into a structured matrix format with
     * distinct lists of subjects, resources, and permission entries. Permission
     * entries are deduplicated by subject-resource-action key to ensure each unique
     * permission appears only once. Useful for building custom visualizations,
     * exporting to external systems, or generating audit reports.
     *
     * @param  Policy               $policy The policy to convert into matrix format
     * @return array<string, mixed> Associative array with three keys: 'subjects' (array of unique
     *                              subject identifier strings), 'resources' (array of unique resource
     *                              identifier strings), and 'permissions' (array of permission entries,
     *                              each with subject, resource, action, effect, and priority fields)
     */
    public static function generate(Policy $policy): array
    {
        $subjects = [];
        $resources = [];
        $permissions = [];

        foreach ($policy->rules as $rule) {
            $subject = $rule->subject;
            $resource = $rule->resource;
            $action = $rule->action;

            if (!in_array($subject, $subjects, true)) {
                $subjects[] = $subject;
            }

            if (!in_array($resource, $resources, true)) {
                $resources[] = $resource;
            }

            $key = sprintf('%s|%s|%s', $subject, $resource, $action);
            $permissions[$key] = [
                'subject' => $subject,
                'resource' => $resource,
                'action' => $action,
                'effect' => $rule->effect,
                'priority' => $rule->priority->value,
            ];
        }

        return [
            'subjects' => $subjects,
            'resources' => $resources,
            'permissions' => array_values($permissions),
        ];
    }

    /**
     * Generate an HTML table representation of the permission matrix.
     *
     * Creates a visual table where rows represent subjects and columns represent
     * resources. Each cell shows the permitted actions for that subject-resource
     * pair, color-coded by effect (green for Allow, red for Deny). Multiple actions
     * per cell are displayed on separate lines. Suitable for embedding in web
     * dashboards, documentation pages, or administrative interfaces.
     *
     * @param  Policy $policy The policy to visualize as an HTML table
     * @return string HTML table markup with inline styles. Includes border="1", cellpadding="5",
     *                and cellspacing="0" attributes for consistent rendering. Action names are
     *                color-coded using inline styles (green for Allow, red for Deny) and separated
     *                by <br> tags for readability.
     */
    public static function toHtml(Policy $policy): string
    {
        $matrix = self::generate($policy);

        $subjects = $matrix['subjects'];
        assert(is_array($subjects));

        $resources = $matrix['resources'];
        assert(is_array($resources));

        $permissionsData = $matrix['permissions'];
        assert(is_array($permissionsData));
        $permissions = collect($permissionsData);

        $html = '<table border="1" cellpadding="5" cellspacing="0">';
        $html .= '<thead><tr><th>Subject / Resource</th>';

        foreach ($resources as $resource) {
            assert(is_string($resource));
            $html .= sprintf('<th>%s</th>', $resource);
        }

        $html .= '</tr></thead><tbody>';

        foreach ($subjects as $subject) {
            assert(is_string($subject));
            $html .= sprintf('<tr><td><strong>%s</strong></td>', $subject);

            foreach ($resources as $resource) {
                assert(is_string($resource));
                $subjectPerms = $permissions->filter(
                    function (mixed $p) use ($subject, $resource): bool {
                        assert(is_array($p));
                        assert(is_string($p['subject']));
                        assert(is_string($p['resource']));

                        return $p['subject'] === $subject && $p['resource'] === $resource;
                    },
                );

                $html .= '<td>';

                foreach ($subjectPerms as $perm) {
                    assert(is_array($perm));
                    assert($perm['effect'] instanceof Effect);
                    assert(is_string($perm['action']));

                    $color = $perm['effect'] === Effect::Allow ? 'green' : 'red';
                    $html .= sprintf("<span style='color: %s;'>%s</span><br>", $color, $perm['action']);
                }

                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /**
     * Generate a CSV representation of the permission matrix.
     *
     * Exports policy rules as comma-separated values suitable for spreadsheet
     * applications, data analysis tools, or compliance documentation. Each row
     * represents a single permission with quoted fields to handle special characters.
     * Effect values are converted to uppercase strings (ALLOW/DENY) for clarity and
     * consistency. Includes header row for easy import into tools like Excel or Google Sheets.
     *
     * @param  Policy $policy The policy to export as CSV format
     * @return string CSV formatted permissions with header row. Fields are double-quoted to safely
     *                handle commas and special characters. Includes columns: Subject, Resource,
     *                Action, Effect (ALLOW/DENY uppercase), and Priority. Each row ends with PHP_EOL
     *                for cross-platform compatibility.
     */
    public static function toCsv(Policy $policy): string
    {
        $matrix = self::generate($policy);

        $permissionsData = $matrix['permissions'];
        assert(is_array($permissionsData));

        $csv = "Subject,Resource,Action,Effect,Priority\n";

        foreach ($permissionsData as $perm) {
            assert(is_array($perm));
            assert($perm['effect'] instanceof Effect);
            assert(is_string($perm['subject']));
            assert(is_string($perm['resource']));
            assert(is_string($perm['action']));
            assert(is_int($perm['priority']));

            $effect = $perm['effect'] === Effect::Allow ? 'ALLOW' : 'DENY';
            $csv .= sprintf('"%s","%s","%s",%s,%s%s', $perm['subject'], $perm['resource'], $perm['action'], $effect, $perm['priority'], PHP_EOL);
        }

        return $csv;
    }
}
