<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol\Core\Engine;

use Override;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;

use function array_any;
use function explode;
use function mb_strtoupper;
use function preg_match;
use function preg_quote;
use function preg_replace_callback;
use function str_replace;
use function str_starts_with;

/**
 * RESTful API rule matching engine with fallback support.
 *
 * Implements HTTP-aware pattern matching for RESTful API authorization by
 * evaluating HTTP methods (GET, POST, PUT, etc.) and URL path patterns with
 * support for wildcards and named parameters. Falls back to a decorated matcher
 * for non-HTTP actions, enabling hybrid authorization schemes.
 *
 * Pattern examples:
 * - "/api/documents/*" matches any document path
 * - "/api/documents/:id" matches paths with a document ID parameter
 * - "GET /api/documents" matches only GET requests to the documents endpoint
 *
 * @psalm-immutable
 *
 * @author Brian Faust <brian@cline.sh>
 */
final readonly class RestfulRuleMatcher implements RuleMatcherInterface
{
    /**
     * Create a new RESTful rule matcher with fallback support.
     *
     * @param RuleMatcherInterface $fallbackMatcher The matcher to delegate to when the action
     *                                              is not an HTTP method. Typically an RBAC or
     *                                              ABAC matcher for non-RESTful permissions.
     */
    public function __construct(
        private RuleMatcherInterface $fallbackMatcher,
    ) {}

    /**
     * Determine if a policy rule matches the given RESTful authorization request.
     *
     * Detects HTTP method actions (GET, POST, etc.) and applies RESTful pattern
     * matching. For non-HTTP actions, delegates to the fallback matcher to support
     * hybrid authorization models combining REST and traditional permissions.
     *
     * @param  PolicyRule $rule     The policy rule containing HTTP method and URL pattern
     * @param  Subject    $subject  The subject requesting API access
     * @param  resource   $resource The target API endpoint or resource path
     * @param  Action     $action   The HTTP method and optional path (e.g., "GET /api/documents")
     * @return bool       True if the rule matches the RESTful request or fallback matcher succeeds
     */
    #[Override()]
    public function matches(
        PolicyRule $rule,
        Subject $subject,
        Resource $resource,
        Action $action,
    ): bool {
        // Check if action contains HTTP method
        if ($this->isHttpMethod($action->name)) {
            return $this->matchesSubject($rule, $subject)
                && $this->matchesRestfulResource($rule, $resource)
                && $this->matchesHttpMethod($rule, $action);
        }

        // Fallback to decorated matcher
        return $this->fallbackMatcher->matches($rule, $subject, $resource, $action);
    }

    /**
     * Match the rule's subject against the requesting subject.
     *
     * Provides simple subject matching with wildcard support for public endpoints.
     * The wildcard (*) allows unauthenticated access or applies the rule to all users.
     *
     * @param  PolicyRule $rule    The policy rule containing the subject pattern
     * @param  Subject    $subject The requesting subject with a unique identifier
     * @return bool       True if the subject matches via wildcard or exact ID match
     */
    private function matchesSubject(PolicyRule $rule, Subject $subject): bool
    {
        if ($rule->subject === '*') {
            return true;
        }

        return $rule->subject === $subject->id;
    }

    /**
     * Match the rule's resource pattern against the RESTful API path.
     *
     * Converts URL patterns with wildcards and named parameters into regex patterns
     * for flexible path matching. Supports:
     * - Null/wildcard (*) for universal resource access
     * - Path wildcards (/api/documents/*) for prefix matching
     * - Named parameters (/api/documents/:id) for parameterized routes
     *
     * @param  PolicyRule $rule     The policy rule containing the URL pattern to match
     * @param  resource   $resource The target resource with the API path in its ID
     * @return bool       True if the resource path matches the rule's URL pattern
     */
    private function matchesRestfulResource(PolicyRule $rule, Resource $resource): bool
    {
        if ($rule->resource === null || $rule->resource === '*') {
            return true;
        }

        // Parse RESTful path patterns like "/api/documents/*" or "/api/documents/:id"
        $pattern = $this->convertPatternToRegex($rule->resource);
        $resourcePath = $resource->id;

        return preg_match($pattern, $resourcePath) === 1;
    }

    /**
     * Match the rule's HTTP method against the requested method.
     *
     * Extracts and compares HTTP methods in a case-insensitive manner, supporting
     * wildcard rules that grant access regardless of the HTTP verb used.
     *
     * @param  PolicyRule $rule   The policy rule containing the HTTP method pattern (e.g., "GET")
     * @param  Action     $action The requested action containing the HTTP method and optional path
     * @return bool       True if the HTTP methods match (case-insensitive) or rule uses wildcard
     */
    private function matchesHttpMethod(PolicyRule $rule, Action $action): bool
    {
        if ($rule->action === '*') {
            return true;
        }

        // Extract HTTP method from action (e.g., "GET /api/documents")
        $actionMethod = $this->extractHttpMethod($action->name);
        $ruleMethod = $this->extractHttpMethod($rule->action);

        return mb_strtoupper($actionMethod) === mb_strtoupper($ruleMethod);
    }

    /**
     * Determine if an action string represents an HTTP method.
     *
     * Checks if the action starts with a standard HTTP method verb, enabling
     * the matcher to distinguish RESTful actions from traditional permission names.
     *
     * @param  string $action The action string to evaluate (e.g., "GET /api/documents" or "read")
     * @return bool   True if the action starts with GET, POST, PUT, PATCH, DELETE, HEAD, or OPTIONS
     */
    private function isHttpMethod(string $action): bool
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        $actionUpper = mb_strtoupper($action);

        return array_any($methods, fn ($method): bool => str_starts_with($actionUpper, (string) $method));
    }

    /**
     * Extract the HTTP method from an action string.
     *
     * Parses action strings that may contain both HTTP method and path
     * (e.g., "GET /api/documents") and returns only the method component.
     *
     * @param  string $action The action string containing an HTTP method
     * @return string The uppercase HTTP method (e.g., "GET", "POST")
     */
    private function extractHttpMethod(string $action): string
    {
        $parts = explode(' ', $action, 2);

        return mb_strtoupper($parts[0]);
    }

    /**
     * Convert a URL pattern with wildcards and parameters to a regex pattern.
     *
     * Transforms RESTful URL patterns into regex for matching actual request paths:
     * - Escapes special regex characters to treat URL syntax literally
     * - Converts :parameter to named capture groups: (?P<parameter>[^/]+)
     * - Converts * wildcards to match any single path segment: [^/]+
     *
     * Pattern transformations:
     * - "/api/documents/*" → "#^/api/documents/[^/]+$#"
     * - "/api/documents/:id" → "#^/api/documents/(?P<id>[^/]+)$#"
     *
     * @param  string $pattern The URL pattern with :parameters and * wildcards
     * @return string The compiled regex pattern with anchors for exact matching
     */
    private function convertPatternToRegex(string $pattern): string
    {
        // Escape regex special characters except * and :
        $pattern = preg_quote($pattern, '#');

        // Convert :id to named regex group with unique counter to avoid duplicate names
        $counter = 0;
        $pattern = preg_replace_callback(
            '/\\\\:(\\w+)/',
            function (array $matches) use (&$counter): string {
                ++$counter;

                return '(?P<'.$matches[1].'_'.$counter.'>[^/]+)';
            },
            $pattern,
        ) ?? '';

        // Convert /* to match any path segment
        $pattern = str_replace('\*', '[^/]+', $pattern);

        return '#^'.$pattern.'$#';
    }
}
