<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\RestfulRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

describe('RestfulRuleMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new RestfulRuleMatcher(
            new AclRuleMatcher(),
        );
    });

    describe('Happy Paths', function (): void {
        test('matches GET request to specific path', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/documents', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches POST request with path parameter', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents/:id',
                action: 'POST',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/documents/123', 'api');
            $action = patrol_action('POST');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches DELETE request with wildcard', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents/*',
                action: 'DELETE',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/documents/456', 'api');
            $action = patrol_action('DELETE');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches any HTTP method with wildcard action', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/users',
                action: '*',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/users', 'api');
            $action = patrol_action('PUT');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches PUT request case-insensitively', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/settings',
                action: 'put',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/settings', 'api');
            $action = patrol_action('PUT');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('falls back to ACL matcher for non-HTTP actions', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-1',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects when HTTP method does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/documents', 'api');
            $action = patrol_action('POST');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when path does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents/:id',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/users/123', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when subject does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-2');
            $resource = resource('/api/documents', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles complex path patterns with multiple parameters', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/users/:userId/documents/:docId',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/users/123/documents/456', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles wildcard subject', function (): void {
            $rule = new PolicyRule(
                subject: '*',
                resource: '/api/public/*',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('anyone');
            $resource = resource('/api/public/data', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles null resource', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: null,
                action: 'POST',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/any/path', 'api');
            $action = patrol_action('POST');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('distinguishes between similar paths', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/documents',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/documents/123', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles resource wildcard pattern', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '*',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/anything', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles path with special regex characters', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/search?query=:term',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/search?query=test', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles path with dots and hyphens', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/files/:filename',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/files/document-v1.0.pdf', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles empty pattern edge case', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles all supported HTTP methods', function (string $method): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/test',
                action: $method,
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/test', 'api');
            $action = patrol_action($method);

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        })->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']);

        test('handles pattern with many parameters', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/:p1/:p2/:p3/:p4/:p5/:p6/:p7/:p8/:p9/:p10',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/a/b/c/d/e/f/g/h/i/j', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles mixed wildcards and parameters', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/*/users/:id/*/profile',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/v1/users/123/settings/profile', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles extremely long path patterns without PCRE errors', function (): void {
            // This test exercises the preg_replace null handling path by using
            // a very long pattern with many parameters. While it doesn't typically
            // trigger PCRE errors with default settings, it tests the code path.
            $params = array_map(fn (int $i): string => ':param'.$i, range(1, 50));
            $pattern = '/api/'.implode('/', $params);
            $values = array_map(fn (int $i): string => 'value'.$i, range(1, 50));
            $path = '/api/'.implode('/', $values);

            $rule = new PolicyRule(
                subject: 'user-1',
                resource: $pattern,
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource($path, 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles patterns with consecutive parameters', function (): void {
            // Test pattern: /:id:action - this creates escaped pattern \\:id\\:action
            // which when processed becomes (?P<id>[^/]+)(?P<action>[^/]+)
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: '/api/:id:type',
                action: 'GET',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('/api/123document', 'api');
            $action = patrol_action('GET');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles PCRE error gracefully with complex pattern', function (): void {
            // This test triggers the preg_replace null handling (line 214) by
            // temporarily reducing PCRE backtrack limit to force a PCRE error
            $originalLimit = ini_get('pcre.backtrack_limit');

            try {
                // Create a pattern with many consecutive parameter-like segments
                // This will cause preg_replace to exceed backtrack limit when set very low
                $pattern = str_repeat(':a', 1_000);

                // Set backtrack limit to 1 to force PCRE_BACKTRACK_LIMIT_ERROR
                ini_set('pcre.backtrack_limit', '1');

                $rule = new PolicyRule(
                    subject: 'user-1',
                    resource: $pattern,
                    action: 'GET',
                    effect: Effect::Allow,
                );

                $subject = subject('user-1');
                // When preg_replace fails and returns null, convertPatternToRegex sets $pattern = ''
                // which becomes '#^$#' regex pattern that only matches empty strings
                $resource = resource('', 'api');
                $action = patrol_action('GET');

                // This explicitly tests that line 214 ($pattern = '') executes when preg_replace returns null
                // The matcher should handle the PCRE error gracefully without throwing an exception
                $result = $this->matcher->matches($rule, $subject, $resource, $action);

                // The result will be false because preg_match also fails with the low backtrack limit
                // The important part is that the code handles the null gracefully (line 214)
                expect($result)->toBeFalse();
            } finally {
                // Restore original limit
                ini_set('pcre.backtrack_limit', $originalLimit);
            }
        });

        test('handles PCRE backtrack limit error in preg_replace', function (): void {
            // This test explicitly covers line 214: if ($pattern === null) { $pattern = ''; }
            // It ensures the code path where preg_replace returns null is executed
            $originalLimit = ini_get('pcre.backtrack_limit');

            try {
                // Set a very low backtrack limit to force preg_replace to fail and return null
                ini_set('pcre.backtrack_limit', '1');

                // Create a pattern that will trigger backtrack limit when processing named parameters
                // Using str_repeat with :param creates a pattern that causes preg_replace to hit backtrack limit
                $complexPattern = '/api/'.str_repeat(':param/', 100).'end';

                $rule = new PolicyRule(
                    subject: 'user-1',
                    resource: $complexPattern,
                    action: 'GET',
                    effect: Effect::Allow,
                );

                $subject = subject('user-1');
                $resource = resource('', 'api');
                $action = patrol_action('GET');

                // Execute the match which will trigger line 214 when preg_replace returns null
                // The important part is that no exception is thrown despite the PCRE error
                // This verifies that line 214 ($pattern = '') handles the null gracefully
                $result = $this->matcher->matches($rule, $subject, $resource, $action);

                // With backtrack limit = 1, even preg_match fails, so result is false
                // The critical test is that NO exception was thrown (line 214 worked)
                expect($result)->toBeFalse();
            } finally {
                // Restore original limit
                ini_set('pcre.backtrack_limit', $originalLimit);
            }
        });

        test('preg_replace null handling allows matcher to continue without exception', function (): void {
            // Explicit test to verify line 214 prevents null propagation errors
            $originalLimit = ini_get('pcre.backtrack_limit');

            try {
                // Force PCRE backtrack limit error
                ini_set('pcre.backtrack_limit', '1');

                // Pattern with lots of parameters that will exceed backtrack limit
                $pattern = str_repeat(':x', 500);

                $rule = new PolicyRule(
                    subject: 'user-1',
                    resource: $pattern,
                    action: 'GET',
                    effect: Effect::Allow,
                );

                $subject = subject('user-1');
                $resource = resource('/', 'api');
                $action = patrol_action('GET');

                // This should not throw a TypeError or any exception
                // because line 214 handles the null from preg_replace
                expect(fn () => $this->matcher->matches($rule, $subject, $resource, $action))
                    ->not->toThrow(TypeError::class);
            } finally {
                ini_set('pcre.backtrack_limit', $originalLimit);
            }
        });
    });
});
