<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Patrol;
use Symfony\Component\HttpFoundation\Response;

use function abort;
use function response;

/**
 * Laravel HTTP middleware for enforcing policy-based authorization.
 *
 * This middleware integrates the Patrol authorization engine into Laravel's
 * request lifecycle, evaluating policies before allowing requests to proceed.
 * It resolves the current subject (user), resource, and action from the request
 * context and evaluates them against configured policies.
 *
 * Usage:
 * ```php
 * // In routes/api.php
 * Route::middleware('patrol:users,read')->get('/users', ...);
 * Route::middleware('patrol')->get('/posts', ...); // Auto-detects resource/action
 * ```
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class PatrolMiddleware
{
    /**
     * Create a new patrol middleware instance.
     *
     * @param PolicyEvaluator           $evaluator  The policy evaluation engine responsible for determining
     *                                              whether the current subject is authorized to perform the
     *                                              requested action on the specified resource
     * @param PolicyRepositoryInterface $repository The repository for loading policies from storage
     */
    public function __construct(
        private PolicyEvaluator $evaluator,
        private PolicyRepositoryInterface $repository,
    ) {}

    /**
     * Handle an incoming HTTP request and enforce authorization policies.
     *
     * Evaluates whether the current subject is authorized to perform the requested
     * action on the specified resource. Returns 403 Forbidden if authorization fails,
     * otherwise passes the request to the next middleware in the pipeline.
     *
     * @param  Request                    $request  The incoming HTTP request to authorize
     * @param  Closure(Request): Response $next     The next middleware in the request handling pipeline
     * @param  null|string                $resource Optional resource identifier. If not provided, defaults to
     *                                              the request path. Can be a resource ID that will be resolved
     *                                              using the configured resource resolver.
     * @param  null|string                $action   Optional action identifier. If not provided, defaults to
     *                                              the HTTP method (GET, POST, etc.). Actions are matched against
     *                                              policy statements to determine authorization.
     * @return Response                   Returns the next middleware's response if authorized, or a 403 Forbidden
     *                                    response (JSON for API requests, HTTP redirect for web requests) if denied
     */
    public function handle(Request $request, Closure $next, ?string $resource = null, ?string $action = null): Response
    {
        $subject = Patrol::currentSubject();

        if (!$subject instanceof Subject) {
            return $this->unauthorized($request);
        }

        // Build resource from parameter or request path
        $resourceValue = $this->buildResource($request, $resource);

        // Build action from parameter or HTTP method
        $actionValue = $this->buildAction($request, $action);

        // Load applicable policies from repository
        $policy = $this->repository->getPoliciesFor($subject, $resourceValue);

        $result = $this->evaluator->evaluate($policy, $subject, $resourceValue, $actionValue);

        if ($result === Effect::Deny) {
            return $this->unauthorized($request);
        }

        return $next($request);
    }

    /**
     * Build a Resource value object from the middleware parameter or request context.
     *
     * Attempts to resolve the resource through the configured resource resolver if
     * a resource identifier is provided. Falls back to using the request path as
     * the resource identifier for automatic resource detection.
     *
     * @param  Request     $request  The incoming HTTP request
     * @param  null|string $resource Optional resource identifier from middleware parameters
     * @return resource    The resolved resource value object containing the resource ID,
     *                     type, and any associated attributes from the resolver
     */
    private function buildResource(Request $request, ?string $resource): Resource
    {
        if ($resource !== null) {
            // Use provided resource identifier
            if (($resolved = Patrol::resolveResourceById($resource)) instanceof Resource) {
                return $resolved;
            }

            return new Resource($resource, 'unknown');
        }

        // Default: use request path as resource
        return new Resource($request->path(), 'api');
    }

    /**
     * Build an Action value object from the middleware parameter or request method.
     *
     * Uses the explicitly provided action if available, otherwise defaults to
     * the HTTP method (GET, POST, PUT, DELETE, etc.) for RESTful resource access.
     *
     * @param  Request     $request The incoming HTTP request
     * @param  null|string $action  Optional action identifier from middleware parameters
     * @return Action      The action value object representing the operation being attempted
     */
    private function buildAction(Request $request, ?string $action): Action
    {
        if ($action !== null) {
            return new Action($action);
        }

        // Default: use HTTP method
        return new Action($request->method());
    }

    /**
     * Return an unauthorized response appropriate for the request type.
     *
     * Returns a JSON response with 403 status for API requests that expect JSON,
     * or triggers Laravel's abort helper for web requests to display the standard
     * 403 error page.
     *
     * @param  Request  $request The incoming HTTP request being denied
     * @return Response The 403 Forbidden response (JSON format for API requests)
     */
    private function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        abort(403);
    }
}
