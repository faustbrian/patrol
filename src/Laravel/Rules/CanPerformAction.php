<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Patrol;

use function app;
use function array_key_exists;
use function class_basename;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function sprintf;

/**
 * Laravel validation rule for checking authorization permissions.
 *
 * Validates that the current authenticated user has permission to perform
 * a specific action on a resource. Integrates Patrol's authorization system
 * into Laravel's validation framework for declarative access control.
 *
 * ```php
 * use Patrol\Laravel\Rules\CanPerformAction;
 *
 * // In a form request or controller:
 * $request->validate([
 *     'action' => ['required', new CanPerformAction($post)],
 * ]);
 *
 * // Or specify the action explicitly:
 * $request->validate([
 *     'resource_id' => ['required', new CanPerformAction($resource, 'edit')],
 * ]);
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class CanPerformAction implements ValidationRule
{
    /**
     * Create a new validation rule instance.
     *
     * @param array<string, mixed>|object|string $resource The resource to check permissions on. Accepts a model instance
     *                                                     with an 'id' property, a string resource identifier, or an array
     *                                                     with 'id' and 'type' keys. Model instances will extract their ID
     *                                                     and class basename for resource identification.
     * @param null|string                        $action   The specific action to validate against the resource (e.g., "edit",
     *                                                     "delete", "publish"). When null, the validation field's value will
     *                                                     be used as the action name, allowing dynamic action validation.
     */
    public function __construct(
        private object|array|string $resource,
        private ?string $action = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * Checks if the current authenticated user can perform the action on the resource.
     * Uses the field value as the action if no explicit action was provided in constructor.
     * Fails validation with a descriptive message if the user is unauthenticated or
     * lacks the required permission.
     *
     * @param string                                      $attribute The name of the field being validated
     * @param mixed                                       $value     The value of the field (used as action name if constructor
     *                                                               action is null). Expected to be a string when used as action.
     * @param Closure(string):PotentiallyTranslatedString $fail      Callback to invoke when validation fails, accepting a
     *                                                               translatable error message string
     */
    #[Override()]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subject = Patrol::currentSubject();

        if (!$subject instanceof Subject) {
            $fail('You must be authenticated to perform this action.');

            return;
        }

        // Use explicit action or field value as action
        $actionName = $this->action ?? (is_string($value) ? $value : 'access');
        $action = new Action($actionName);

        // Convert resource to Resource value object
        $resourceValue = $this->toPatrolResource($this->resource);

        // Load and evaluate policies
        $repository = app(PolicyRepositoryInterface::class);
        $evaluator = app(PolicyEvaluator::class);

        $policy = $repository->getPoliciesFor($subject, $resourceValue);
        $result = $evaluator->evaluate($policy, $subject, $resourceValue, $action);

        if ($result !== Effect::Allow) {
            $resourceLabel = $this->getResourceLabel($resourceValue);
            $fail(sprintf('You are not authorized to %s %s.', $actionName, $resourceLabel));
        }
    }

    /**
     * Convert various resource formats to a Patrol Resource value object.
     *
     * Handles conversion from string identifiers, model instances with 'id' properties,
     * arrays with 'id'/'type' keys, or existing Resource objects. Extracts resource ID,
     * type, and attributes to construct a normalized Resource value object suitable for
     * policy evaluation.
     *
     * @param  array<string, mixed>|object|string $resource The resource to convert. Can be a string ID, an object
     *                                                      with 'id' property and optional 'toArray()' method, an
     *                                                      array with 'id' and 'type' keys, or a Resource object.
     * @return resource                           The normalized Resource value object with ID, type, and attributes
     */
    private function toPatrolResource(object|array|string $resource): Resource
    {
        if ($resource instanceof Resource) {
            return $resource;
        }

        if (is_string($resource)) {
            return new Resource($resource, 'unknown');
        }

        // Extract ID and type from object or array
        $id = 'unknown';
        $type = 'unknown';
        $attributes = [];

        if (is_object($resource)) {
            if (property_exists($resource, 'id')) {
                $idValue = $resource->id;

                if (is_string($idValue) || is_int($idValue)) {
                    $id = (string) $idValue;
                }
            }

            $type = class_basename($resource);

            if (method_exists($resource, 'toArray')) {
                $result = $resource->toArray();

                if (is_array($result)) {
                    /** @var array<string, mixed> $attributes */
                    $attributes = $result;
                }
            }
        } elseif (is_array($resource)) {
            if (array_key_exists('id', $resource) && (is_string($resource['id']) || is_int($resource['id']))) {
                $id = (string) $resource['id'];
            }

            if (array_key_exists('type', $resource) && is_string($resource['type'])) {
                $type = $resource['type'];
            }

            /** @var array<string, mixed> $attributes */
            $attributes = $resource;
        }

        return new Resource($id, $type, $attributes);
    }

    /**
     * Get a human-readable label for the resource.
     *
     * Generates user-friendly resource descriptions for validation error messages.
     * Returns "this resource" for unknown types or "this {type}" for identified resources.
     *
     * @param  resource $resource The resource to generate a label for
     * @return string   Descriptive label suitable for use in error messages (e.g., "this Post")
     */
    private function getResourceLabel(Resource $resource): string
    {
        if ($resource->type === 'unknown') {
            return 'this resource';
        }

        return 'this '.$resource->type;
    }
}
