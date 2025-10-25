<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\DelegationScope;

describe('DelegationScope', function (): void {
    describe('Happy Paths', function (): void {
        test('creates scope with resources and actions', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123', 'report:456'],
                actions: ['read', 'edit'],
            );

            expect($scope->resources)->toBe(['document:123', 'report:456']);
            expect($scope->actions)->toBe(['read', 'edit']);
            expect($scope->domain)->toBeNull();
        });

        test('creates scope with domain', function (): void {
            $scope = new DelegationScope(
                resources: ['document:*'],
                actions: ['read'],
                domain: 'tenant-1',
            );

            expect($scope->domain)->toBe('tenant-1');
        });

        test('matches exact resource and action', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
        });

        test('matches wildcard resource pattern', function (): void {
            $scope = new DelegationScope(
                resources: ['document:*'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:456', 'read'))->toBeTrue();
            expect($scope->matches('document:abc', 'read'))->toBeTrue();
        });

        test('matches wildcard action', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['*'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:123', 'write'))->toBeTrue();
            expect($scope->matches('document:123', 'delete'))->toBeTrue();
        });

        test('matches multiple resources', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123', 'document:456'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:456', 'read'))->toBeTrue();
        });

        test('matches multiple actions', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read', 'edit', 'delete'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:123', 'edit'))->toBeTrue();
            expect($scope->matches('document:123', 'delete'))->toBeTrue();
        });

        test('matches with complex wildcard patterns', function (): void {
            $scope = new DelegationScope(
                resources: ['document:project-*'],
                actions: ['read'],
            );

            expect($scope->matches('document:project-alpha', 'read'))->toBeTrue();
            expect($scope->matches('document:project-beta', 'read'))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects non-matching resource', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read'],
            );

            expect($scope->matches('document:456', 'read'))->toBeFalse();
            expect($scope->matches('report:123', 'read'))->toBeFalse();
        });

        test('rejects non-matching action', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'write'))->toBeFalse();
            expect($scope->matches('document:123', 'delete'))->toBeFalse();
        });

        test('rejects when resource matches but action does not', function (): void {
            $scope = new DelegationScope(
                resources: ['document:*'],
                actions: ['read', 'edit'],
            );

            expect($scope->matches('document:123', 'delete'))->toBeFalse();
        });

        test('rejects when action matches but resource does not', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read'],
            );

            expect($scope->matches('report:123', 'read'))->toBeFalse();
        });

        test('rejects when neither resource nor action match', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read'],
            );

            expect($scope->matches('report:456', 'delete'))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles global wildcard resource', function (): void {
            $scope = new DelegationScope(
                resources: ['*'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('report:456', 'read'))->toBeTrue();
            expect($scope->matches('anything:xyz', 'read'))->toBeTrue();
        });

        test('handles global wildcard action', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['*'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:123', 'custom_action'))->toBeTrue();
        });

        test('handles both resource and action wildcards', function (): void {
            $scope = new DelegationScope(
                resources: ['*'],
                actions: ['*'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('report:456', 'delete'))->toBeTrue();
            expect($scope->matches('anything', 'custom'))->toBeTrue();
        });

        test('handles empty resource list', function (): void {
            $scope = new DelegationScope(
                resources: [],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeFalse();
        });

        test('handles empty action list', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: [],
            );

            expect($scope->matches('document:123', 'read'))->toBeFalse();
        });

        test('handles wildcard in middle of pattern', function (): void {
            $scope = new DelegationScope(
                resources: ['document:*:active'],
                actions: ['read'],
            );

            expect($scope->matches('document:123:active', 'read'))->toBeTrue();
            expect($scope->matches('document:abc:active', 'read'))->toBeTrue();
            expect($scope->matches('document:123:inactive', 'read'))->toBeFalse();
        });

        test('handles question mark wildcard for single character', function (): void {
            $scope = new DelegationScope(
                resources: ['document:12?'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:12a', 'read'))->toBeTrue();
            expect($scope->matches('document:1234', 'read'))->toBeFalse();
        });

        test('handles character class patterns', function (): void {
            $scope = new DelegationScope(
                resources: ['document:[0-9]*'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:0', 'read'))->toBeTrue();
            expect($scope->matches('document:abc', 'read'))->toBeFalse();
        });

        test('case sensitive matching', function (): void {
            $scope = new DelegationScope(
                resources: ['Document:123'],
                actions: ['Read'],
            );

            expect($scope->matches('Document:123', 'Read'))->toBeTrue();
            expect($scope->matches('document:123', 'read'))->toBeFalse();
        });

        test('handles special characters in resource identifiers', function (): void {
            $scope = new DelegationScope(
                resources: ['document:user@example.com'],
                actions: ['read'],
            );

            expect($scope->matches('document:user@example.com', 'read'))->toBeTrue();
        });

        test('handles colon in resource patterns', function (): void {
            $scope = new DelegationScope(
                resources: ['s3:bucket:*'],
                actions: ['read'],
            );

            expect($scope->matches('s3:bucket:file.txt', 'read'))->toBeTrue();
            expect($scope->matches('s3:bucket:path/to/file.txt', 'read'))->toBeTrue();
        });

        test('matches first matching resource in list', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123', 'document:*', 'report:*'],
                actions: ['read'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:456', 'read'))->toBeTrue();
            expect($scope->matches('report:789', 'read'))->toBeTrue();
        });

        test('matches first matching action in list', function (): void {
            $scope = new DelegationScope(
                resources: ['document:123'],
                actions: ['read', 'write', '*'],
            );

            expect($scope->matches('document:123', 'read'))->toBeTrue();
            expect($scope->matches('document:123', 'delete'))->toBeTrue(); // Matches wildcard
        });

        test('scope is immutable', function (): void {
            $scope = new DelegationScope(
                resources: ['document:*'],
                actions: ['read'],
            );

            expect($scope)->toBeInstanceOf(DelegationScope::class);
            expect($scope->resources)->toBe(['document:*']);
        });

        test('handles very long resource patterns', function (): void {
            $longPattern = 'document:'.str_repeat('a', 1_000);
            $scope = new DelegationScope(
                resources: [$longPattern],
                actions: ['read'],
            );

            expect($scope->matches($longPattern, 'read'))->toBeTrue();
        });
    });
});
