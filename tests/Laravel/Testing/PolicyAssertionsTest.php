<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Testing\PolicyAssertions;
use PHPUnit\Framework\AssertionFailedError;

uses(PolicyAssertions::class);

describe('PolicyAssertions', function (): void {
    describe('Happy Paths', function (): void {
        test('assertCanAccess passes when policy allows access with objects', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);

            $subject = new Subject('user:1');
            $resource = new Resource('doc:1', 'document');
            $action = new Action('read');

            $this->assertCanAccess($subject, $resource, $action, $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('assertCanAccess passes when policy allows access with strings', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);

            $this->assertCanAccess('user:1', 'doc:1', 'read', $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('assertCannotAccess passes when policy denies access with objects', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Deny),
            ]);

            $subject = new Subject('user:1');
            $resource = new Resource('doc:1', 'document');
            $action = new Action('read');

            $this->assertCannotAccess($subject, $resource, $action, $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('assertCannotAccess passes when policy denies access with strings', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Deny),
            ]);

            $this->assertCannotAccess('user:1', 'doc:1', 'read', $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('assertPolicyAllows passes when policy allows access', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'documents:123', 'write', Effect::Allow),
            ]);

            $this->assertPolicyAllows('admin', 'documents:123', 'write', $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('assertPolicyDenies passes when policy denies access', function (): void {
            $policy = new Policy([
                new PolicyRule('guest', 'admin:*', 'access', Effect::Deny),
            ]);

            $this->assertPolicyDenies('guest', 'admin:panel', 'access', $policy);

            expect(true)->toBeTrue(); // If we get here, assertion passed
        });

        test('accepts wildcard subjects with superuser attribute', function (): void {
            $policy = new Policy([
                new PolicyRule('*', 'public:page', 'read', Effect::Allow),
            ]);

            $subject = new Subject('user:123', ['superuser' => true]);
            $resource = new Resource('public:page', 'page');
            $action = new Action('read');

            $this->assertCanAccess($subject, $resource, $action, $policy);

            expect(true)->toBeTrue();
        });

        test('accepts wildcard resources', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', '*', 'manage', Effect::Allow),
            ]);

            $this->assertCanAccess('admin', 'anything', 'manage', $policy);

            expect(true)->toBeTrue();
        });

        test('handles multiple rules with different priorities', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:*', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:1', 'doc:secret', 'read', Effect::Deny, new Priority(100)),
            ]);

            // High priority deny should override low priority allow
            $this->assertCannotAccess('user:1', 'doc:secret', 'read', $policy);

            expect(true)->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('assertCanAccess fails when policy denies access', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Deny),
            ]);

            expect(fn () => $this->assertCanAccess('user:1', 'doc:1', 'read', $policy))
                ->toThrow(AssertionFailedError::class);
        });

        test('assertCanAccess fails when no matching rules exist', function (): void {
            $policy = new Policy([
                new PolicyRule('user:2', 'doc:1', 'read', Effect::Allow),
            ]);

            expect(fn () => $this->assertCanAccess('user:1', 'doc:1', 'read', $policy))
                ->toThrow(AssertionFailedError::class);
        });

        test('assertCannotAccess fails when policy allows access', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);

            expect(fn () => $this->assertCannotAccess('user:1', 'doc:1', 'read', $policy))
                ->toThrow(AssertionFailedError::class);
        });

        test('assertPolicyAllows fails when policy denies access', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'delete', Effect::Deny),
            ]);

            expect(fn () => $this->assertPolicyAllows('user:1', 'doc:1', 'delete', $policy))
                ->toThrow(AssertionFailedError::class);
        });

        test('assertPolicyDenies fails when policy allows access', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);

            expect(fn () => $this->assertPolicyDenies('user:1', 'doc:1', 'read', $policy))
                ->toThrow(AssertionFailedError::class);
        });
    });

    describe('Edge Cases', function (): void {
        test('custom error messages are used in assertCanAccess', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Deny),
            ]);

            try {
                $this->assertCanAccess('user:1', 'doc:1', 'read', $policy, 'Custom error message');
                expect(false)->toBeTrue(); // Should not reach here
            } catch (AssertionFailedError $assertionFailedError) {
                expect($assertionFailedError->getMessage())->toContain('Custom error message');
            }
        });

        test('custom error messages are used in assertCannotAccess', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', 'doc:1', 'read', Effect::Allow),
            ]);

            try {
                $this->assertCannotAccess('user:1', 'doc:1', 'read', $policy, 'Access should be denied');
                expect(false)->toBeTrue(); // Should not reach here
            } catch (AssertionFailedError $assertionFailedError) {
                expect($assertionFailedError->getMessage())->toContain('Access should be denied');
            }
        });

        test('default error message includes subject resource and action for assertCanAccess', function (): void {
            $policy = new Policy([]);

            try {
                $this->assertCanAccess('user:123', 'doc:456', 'write', $policy);
                expect(false)->toBeTrue(); // Should not reach here
            } catch (AssertionFailedError $assertionFailedError) {
                expect($assertionFailedError->getMessage())
                    ->toContain('user:123')
                    ->toContain('doc:456')
                    ->toContain('write');
            }
        });

        test('default error message includes subject resource and action for assertCannotAccess', function (): void {
            $policy = new Policy([
                new PolicyRule('editor', 'article:789', 'publish', Effect::Allow),
            ]);

            try {
                $this->assertCannotAccess('editor', 'article:789', 'publish', $policy);
                expect(false)->toBeTrue(); // Should not reach here
            } catch (AssertionFailedError $assertionFailedError) {
                expect($assertionFailedError->getMessage())
                    ->toContain('editor')
                    ->toContain('article:789')
                    ->toContain('publish');
            }
        });

        test('handles empty policy with assertCanAccess', function (): void {
            $policy = new Policy([]);

            expect(fn () => $this->assertCanAccess('user:1', 'doc:1', 'read', $policy))
                ->toThrow(AssertionFailedError::class);
        });

        test('handles empty policy with assertCannotAccess', function (): void {
            $policy = new Policy([]);

            // Empty policy should deny by default
            $this->assertCannotAccess('user:1', 'doc:1', 'read', $policy);

            expect(true)->toBeTrue();
        });

        test('handles null resource in policy rules', function (): void {
            $policy = new Policy([
                new PolicyRule('user:1', null, 'special-action', Effect::Allow),
            ]);

            $this->assertCanAccess('user:1', 'any-resource', 'special-action', $policy);

            expect(true)->toBeTrue();
        });

        test('handles domain-scoped rules', function (): void {
            $policy = new Policy([
                new PolicyRule(
                    'user:1',
                    'doc:1',
                    'read',
                    Effect::Allow,
                    new Priority(1),
                    new Domain('tenant-123'),
                ),
            ]);

            $this->assertCanAccess('user:1', 'doc:1', 'read', $policy);

            expect(true)->toBeTrue();
        });

        test('handles very complex rule combinations', function (): void {
            $policy = new Policy([
                // Base permissions
                new PolicyRule('user:1', 'doc:normal', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user:1', 'doc:normal', 'write', Effect::Allow, new Priority(1)),
                // Specific denials
                new PolicyRule('user:1', 'doc:secret', 'read', Effect::Deny, new Priority(50)),
                // Wildcard allow for superusers
                new PolicyRule('*', 'doc:public', 'read', Effect::Allow, new Priority(10)),
            ]);

            // Should deny secret doc (priority 50 deny overrides priority 1 allow)
            $this->assertCannotAccess('user:1', 'doc:secret', 'read', $policy);

            // Should allow public doc for superuser
            $superuser = new Subject('user:999', ['superuser' => true]);
            $this->assertCanAccess($superuser, new Resource('doc:public', 'document'), new Action('read'), $policy);

            expect(true)->toBeTrue();
        });
    });
});
