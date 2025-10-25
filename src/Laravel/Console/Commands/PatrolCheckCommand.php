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
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use function assert;
use function count;
use function explode;
use function is_string;
use function json_encode;
use function sprintf;
use function str_contains;

/**
 * Artisan command to check authorization for a subject-resource-action tuple.
 *
 * Evaluates whether a subject has permission to perform an action on a resource
 * by loading applicable policies and running them through the policy engine.
 * Useful for debugging authorization issues and testing policy configurations.
 *
 * Usage:
 *   php artisan patrol:check user:123 document:456 read
 *   php artisan patrol:check editor * write-article
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolCheckCommand extends Command
{
    /**
     * The command signature with arguments and options.
     *
     * @var string
     */
    protected $signature = 'patrol:check
                            {subject : The subject identifier (e.g., user:123 or role:editor)}
                            {resource : The resource identifier (e.g., document:456 or *)}
                            {action : The action to check (e.g., read, write, delete)}
                            {--json : Output result as JSON}';

    /**
     * The command description shown in artisan list.
     *
     * @var string
     */
    protected $description = 'Check if a subject can perform an action on a resource';

    /**
     * Execute the authorization check command.
     *
     * Retrieves policies for the given subject-resource pair, evaluates them using
     * the policy engine, and displays the result. Supports both human-readable and
     * JSON output formats. Returns SUCCESS (0) if access is granted, FAILURE (1)
     * if denied.
     *
     * @param  PolicyRepositoryInterface $repository The policy repository for loading policies
     * @param  PolicyEvaluator           $evaluator  The policy evaluation engine
     * @return int                       Command exit code (SUCCESS or FAILURE)
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

        $this->info('Checking authorization...');
        $this->line('  Subject:  '.$subjectId);
        $this->line('  Resource: '.$resourceId);
        $this->line('  Action:   '.$actionId);
        $this->newLine();

        $policy = $repository->getPoliciesFor($subject, $resource);
        $effect = $evaluator->evaluate($policy, $subject, $resource, $action);

        // JSON output mode
        if ($this->option('json') === true) {
            $rules = [];

            foreach ($policy->rules as $rule) {
                $rules[] = [
                    'subject' => $rule->subject,
                    'resource' => $rule->resource,
                    'action' => $rule->action,
                    'effect' => $rule->effect === Effect::Allow ? 'allow' : 'deny',
                    'priority' => $rule->priority->value,
                ];
            }

            $output = [
                'subject' => $subjectId,
                'resource' => $resourceId,
                'action' => $actionId,
                'result' => $effect === Effect::Allow ? 'granted' : 'denied',
                'rules_matched' => count($policy->rules),
                'rules' => $rules,
            ];

            $encoded = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            assert($encoded !== false, 'JSON encoding failed');
            $this->line($encoded);

            return $effect === Effect::Allow ? self::SUCCESS : self::FAILURE;
        }

        // Human-readable output
        $this->info('Found '.count($policy->rules).' applicable rule(s):');

        $lineNumber = 1;

        foreach ($policy->rules as $rule) {
            $effectLabel = $rule->effect === Effect::Allow ? '<fg=green>ALLOW</>' : '<fg=red>DENY</>';
            $this->line(sprintf('  %d. %s (priority: %d)', $lineNumber, $effectLabel, $rule->priority->value));
            $this->line(sprintf('     Subject: %s | Resource: %s | Action: %s', $rule->subject, $rule->resource ?? '*', $rule->action));
            ++$lineNumber;
        }

        $this->newLine();

        if ($effect === Effect::Allow) {
            $this->components->success('Access GRANTED');

            return self::SUCCESS;
        }

        $this->components->error('Access DENIED');

        return self::FAILURE;
    }
}
