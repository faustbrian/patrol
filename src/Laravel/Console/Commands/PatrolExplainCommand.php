<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use function array_filter;
use function array_values;
use function assert;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Artisan command to explain authorization decision with detailed trace.
 *
 * Provides a detailed breakdown of how an authorization decision was made,
 * showing all matching rules, their evaluation order, and the final decision.
 * This is invaluable for debugging complex authorization scenarios.
 *
 * Usage:
 *   php artisan patrol:explain user:123 document:456 read
 *   php artisan patrol:explain editor * write-article --json
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolExplainCommand extends Command
{
    /**
     * The console command signature defining arguments and options.
     *
     * @var string
     */
    protected $signature = 'patrol:explain
                            {subject : The subject identifier (e.g., user:123 or role:editor)}
                            {resource : The resource identifier (e.g., document:456 or *)}
                            {action : The action to check (e.g., read, write, delete)}
                            {--json : Output explanation as JSON}';

    /**
     * The console command description displayed in command listings.
     *
     * @var string
     */
    protected $description = 'Explain how an authorization decision is made with detailed trace';

    /**
     * Execute the authorization explanation command.
     *
     * Evaluates an authorization request and provides a detailed trace showing
     * which policy rules matched, their evaluation order, and the final decision.
     * Supports both human-readable and JSON output formats for debugging and auditing.
     *
     * @param  PolicyRepositoryInterface $repository The policy repository for loading applicable policies
     * @param  PolicyEvaluator           $evaluator  The policy evaluation engine for making authorization decisions
     * @return int                       self::SUCCESS (0) if authorization granted, self::FAILURE (1) if denied
     */
    public function handle(
        PolicyRepositoryInterface $repository,
        PolicyEvaluator $evaluator,
    ): int {
        $subjectIdRaw = $this->argument('subject');
        $resourceIdRaw = $this->argument('resource');
        $actionIdRaw = $this->argument('action');

        assert(is_string($subjectIdRaw), 'Subject argument must be a string');
        assert(is_string($resourceIdRaw), 'Resource argument must be a string');
        assert(is_string($actionIdRaw), 'Action argument must be a string');

        $subjectId = $subjectIdRaw;
        $resourceId = $resourceIdRaw;
        $actionId = $actionIdRaw;

        $subject = new Subject($subjectId);
        // Extract type from resource ID (e.g., "document:123" -> "document")
        $resourceType = str_contains($resourceId, ':')
            ? explode(':', $resourceId, 2)[0]
            : 'resource';
        $resource = new Resource($resourceId, $resourceType);
        $action = new Action($actionId);

        $policy = $repository->getPoliciesFor($subject, $resource);
        $effect = $evaluator->evaluate($policy, $subject, $resource, $action);

        if ($this->option('json') === true) {
            /** @var array<int, PolicyRule> $rules */
            $rules = array_values($policy->rules);

            return $this->outputJson($subjectId, $resourceId, $actionId, $rules, $effect);
        }

        /** @var array<int, PolicyRule> $rules */
        $rules = array_values($policy->rules);

        return $this->outputHuman($subjectId, $resourceId, $actionId, $rules, $effect);
    }

    /**
     * Check if a rule matches the query.
     *
     * @param  PolicyRule $rule       Rule to check
     * @param  string     $subjectId  Subject identifier
     * @param  string     $resourceId Resource identifier
     * @param  string     $actionId   Action identifier
     * @return bool       True if rule matches
     */
    private function ruleMatches(object $rule, string $subjectId, string $resourceId, string $actionId): bool
    {
        $subjectMatch = $rule->subject === '*' || $rule->subject === $subjectId;

        // Resource matching with type wildcard support (e.g., "post:*")
        $resourceMatch = in_array($rule->resource, [null, '*', $resourceId], true)
            || (str_contains($rule->resource, ':*') && str_starts_with($resourceId, str_replace(':*', ':', $rule->resource)));

        $actionMatch = $rule->action === '*' || $rule->action === $actionId;

        return $subjectMatch && $resourceMatch && $actionMatch;
    }

    /**
     * Get explanation for why a rule doesn't match.
     *
     * @param  PolicyRule $rule       Rule to explain
     * @param  string     $subjectId  Subject identifier
     * @param  string     $resourceId Resource identifier
     * @param  string     $actionId   Action identifier
     * @return string     Explanation text
     */
    private function getMatchReason(object $rule, string $subjectId, string $resourceId, string $actionId): string
    {
        $reasons = [];

        if ($rule->subject !== '*' && $rule->subject !== $subjectId) {
            $reasons[] = sprintf('subject mismatch (expected: %s)', $rule->subject);
        }

        if (!in_array($rule->resource, [null, '*', $resourceId], true)) {
            $reasons[] = sprintf('resource mismatch (expected: %s)', $rule->resource);
        }

        if ($rule->action !== '*' && $rule->action !== $actionId) {
            $reasons[] = sprintf('action mismatch (expected: %s)', $rule->action);
        }

        if ($reasons === []) {
            return 'Rule matches';
        }

        return 'Does not match: '.implode(', ', $reasons);
    }

    /**
     * Output explanation in JSON format.
     *
     * @param  string                 $subjectId  Subject identifier
     * @param  string                 $resourceId Resource identifier
     * @param  string                 $actionId   Action identifier
     * @param  array<int, PolicyRule> $rules      Applicable rules
     * @param  Effect                 $effect     Final decision
     * @return int                    Exit code
     */
    private function outputJson(string $subjectId, string $resourceId, string $actionId, array $rules, Effect $effect): int
    {
        $evaluationSteps = [];

        foreach ($rules as $index => $rule) {
            $matched = $this->ruleMatches($rule, $subjectId, $resourceId, $actionId);

            $evaluationSteps[] = [
                'step' => $index + 1,
                'rule' => [
                    'subject' => $rule->subject,
                    'resource' => $rule->resource,
                    'action' => $rule->action,
                    'effect' => $rule->effect === Effect::Allow ? 'allow' : 'deny',
                    'priority' => $rule->priority->value,
                ],
                'matched' => $matched,
                'reason' => $this->getMatchReason($rule, $subjectId, $resourceId, $actionId),
            ];
        }

        $output = [
            'query' => [
                'subject' => $subjectId,
                'resource' => $resourceId,
                'action' => $actionId,
            ],
            'evaluation' => $evaluationSteps,
            'result' => [
                'decision' => $effect === Effect::Allow ? 'granted' : 'denied',
                'rules_evaluated' => count($rules),
                'rules_matched' => count(array_filter($evaluationSteps, fn (array $step): bool => $step['matched'])),
            ],
        ];

        $encoded = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        assert($encoded !== false, 'JSON encoding failed');
        $this->line($encoded);

        return $effect === Effect::Allow ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Output explanation in human-readable format.
     *
     * @param  string                 $subjectId  Subject identifier
     * @param  string                 $resourceId Resource identifier
     * @param  string                 $actionId   Action identifier
     * @param  array<int, PolicyRule> $rules      Applicable rules
     * @param  Effect                 $effect     Final decision
     * @return int                    Exit code
     */
    private function outputHuman(string $subjectId, string $resourceId, string $actionId, array $rules, Effect $effect): int
    {
        $this->info('Authorization Evaluation Trace');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=yellow>Subject</>', $subjectId);
        $this->components->twoColumnDetail('<fg=yellow>Resource</>', $resourceId);
        $this->components->twoColumnDetail('<fg=yellow>Action</>', $actionId);
        $this->newLine();

        if ($rules === []) {
            $this->warn('No rules found for this query');
            $this->newLine();
            $this->components->error('Decision: DENIED (no matching rules)');

            return self::FAILURE;
        }

        $this->info('Rule Evaluation:');
        $this->newLine();

        foreach ($rules as $index => $rule) {
            $matched = $this->ruleMatches($rule, $subjectId, $resourceId, $actionId);
            $effectLabel = $rule->effect === Effect::Allow ? '<fg=green>ALLOW</>' : '<fg=red>DENY</>';
            $matchIcon = $matched ? '<fg=green>✓</>' : '<fg=gray>✗</>';

            $this->line(sprintf('%s Step %d: %s (priority: %d)', $matchIcon, $index + 1, $effectLabel, $rule->priority->value));
            $this->line(sprintf('   Subject: %s', $rule->subject));
            $this->line(sprintf('   Resource: %s', $rule->resource ?? '*'));
            $this->line(sprintf('   Action: %s', $rule->action));

            if ($matched) {
                $this->line('   <fg=green>→ This rule matches the query</>');
            } else {
                $reason = $this->getMatchReason($rule, $subjectId, $resourceId, $actionId);
                $this->line(sprintf('   <fg=gray>→ %s</>', $reason));
            }

            $this->newLine();
        }

        $matchedCount = count(array_filter($rules, fn (object $rule): bool => $this->ruleMatches($rule, $subjectId, $resourceId, $actionId)));

        $this->components->twoColumnDetail('<fg=yellow>Rules Evaluated</>', (string) count($rules));
        $this->components->twoColumnDetail('<fg=yellow>Rules Matched</>', (string) $matchedCount);
        $this->newLine();

        if ($effect === Effect::Allow) {
            $this->components->success('Final Decision: GRANTED');

            return self::SUCCESS;
        }

        $this->components->error('Final Decision: DENIED');

        return self::FAILURE;
    }
}
