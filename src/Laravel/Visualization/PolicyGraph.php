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

use function str_replace;

/**
 * Generates Graphviz DOT format visualizations of authorization policies.
 *
 * Creates directed graph representations of policy rules showing the flow from
 * subjects to resources through actions. Edges are color-coded (green for Allow,
 * red for Deny) and styled (solid for Allow, dashed for Deny) to make authorization
 * patterns immediately visible. Priority values are included in edge labels to show
 * rule precedence.
 *
 * The generated DOT format can be rendered as images using Graphviz tools (dot, neato,
 * circo, etc.) or imported into graph visualization software. Particularly useful for
 * documenting complex policies, identifying access patterns, and communicating
 * authorization architecture to stakeholders.
 *
 * ```php
 * $graph = PolicyGraph::generate($policy);
 * file_put_contents('policy.dot', $graph);
 *
 * // Convert to PNG image
 * // dot -Tpng policy.dot -o policy.png
 *
 * // Convert to SVG for web display
 * // dot -Tsvg policy.dot -o policy.svg
 *
 * // Use different layout engines for different graph structures
 * // circo -Tpng policy.dot -o policy.png  // Circular layout
 * // neato -Tpng policy.dot -o policy.png  // Force-directed layout
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PolicyGraph
{
    /**
     * Generate a DOT graph representation of a policy.
     *
     * Creates a directed graph in DOT format with left-to-right layout. Each policy
     * rule becomes an edge from subject to resource, labeled with the action name
     * and priority value. Visual styling (color and line style) indicates the effect:
     * Allow rules are green/solid, Deny rules are red/dashed. The output is ready
     * for rendering with Graphviz tools like dot, neato, or circo.
     *
     * @param  Policy $policy The policy to visualize as a directed graph
     * @return string DOT format graph specification suitable for Graphviz rendering tools
     *                (dot, neato, circo, fdp, etc.). Can be saved to a .dot file and converted
     *                to PNG, SVG, PDF, or other image formats using Graphviz command-line tools.
     */
    public static function generate(Policy $policy): string
    {
        $dot = "digraph Policy {\n";
        $dot .= "  rankdir=LR;\n";
        $dot .= "  node [shape=box];\n\n";

        foreach ($policy->rules as $rule) {
            $subject = self::escapeLabel($rule->subject);
            $resource = self::escapeLabel($rule->resource);
            $action = self::escapeLabel($rule->action);

            $color = $rule->effect === Effect::Allow ? 'green' : 'red';
            $style = $rule->effect === Effect::Allow ? 'solid' : 'dashed';
            $priority = $rule->priority->value;

            $dot .= "  \"{$subject}\" -> \"{$resource}\" [label=\"{$action}\\npriority: {$priority}\", color={$color}, style={$style}];\n";
        }

        return $dot."}\n";
    }

    /**
     * Escape a label string for safe use in DOT format.
     *
     * Ensures that label strings containing special characters (particularly double
     * quotes) are properly escaped to prevent DOT syntax errors and graph rendering
     * failures. Handles null values by converting them to empty strings. Uses
     * backslash escaping for double quotes as per DOT language specification.
     *
     * @param  null|string $label The label string to escape, or null which is treated as empty string
     * @return string      Properly escaped label safe for inclusion in DOT graph specifications
     */
    private static function escapeLabel(?string $label): string
    {
        return str_replace('"', '\\"', $label ?? '');
    }
}
