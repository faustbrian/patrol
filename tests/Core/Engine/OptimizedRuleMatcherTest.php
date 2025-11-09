<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\OptimizedRuleMatcher;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

describe('OptimizedRuleMatcher', function (): void {
    beforeEach(function (): void {
        $this->underlyingMatcher = new RbacRuleMatcher();
        $this->matcher = new OptimizedRuleMatcher($this->underlyingMatcher, shortCircuit: true);
    });

    describe('Happy Paths', function (): void {
        test('constructs with underlying matcher and short-circuit enabled', function (): void {
            // Arrange & Act
            $matcher = new OptimizedRuleMatcher(
                new RbacRuleMatcher(),
                shortCircuit: true,
            );

            // Assert
            expect($matcher)->toBeInstanceOf(OptimizedRuleMatcher::class);
        });

        test('constructs with underlying matcher and short-circuit disabled', function (): void {
            // Arrange & Act
            $matcher = new OptimizedRuleMatcher(
                new RbacRuleMatcher(),
                shortCircuit: false,
            );

            // Assert
            expect($matcher)->toBeInstanceOf(OptimizedRuleMatcher::class);
        });
        test('indexes rules by subject, resource, and action', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('admin', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('editor', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('viewer', 'doc-3', 'read', Effect::Allow),
            ];

            // Act
            $this->matcher->indexRules($rules);
            $candidates = $this->matcher->getCandidateRules(
                subject('admin', ['roles' => ['admin']]),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('admin');
        });

        test('matches delegates to underlying matcher', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'edit',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            // Act
            $result = $this->matcher->matches($rule, $subject, $resource, $action);

            // Assert
            expect($result)->toBeTrue();
        });

        test('getCandidateRules filters by subject ID', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('user-3', 'doc-3', 'read', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('user-1');
        });

        test('getCandidateRules includes wildcard subject rules', function (): void {
            // Arrange
            $aclMatcher = new OptimizedRuleMatcher(
                new AclRuleMatcher(),
                shortCircuit: true,
            );
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('*', 'doc-1', 'read', Effect::Allow),
            ];
            $aclMatcher->indexRules($rules);

            // Act
            $candidates = $aclMatcher->getCandidateRules(
                subject('user-1', ['superuser' => true]),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(2);
        });

        test('matchesWithShortCircuit returns effect for matching rule', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'edit',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            // Act
            $effect = $this->matcher->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBe(Effect::Allow);
        });

        test('short-circuit immediately returns deny', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'delete',
                effect: Effect::Deny,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            // Act
            $effect = $this->matcher->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBe(Effect::Deny);
        });

        test('indexes handle null resources correctly', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', null, 'create-document', Effect::Allow),
                new PolicyRule('user-2', 'doc-1', 'edit', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('any-doc', 'document'),
                patrol_action('create-document'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->resource)->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('returns empty array when no candidates match subject', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('admin', 'doc-1', 'read', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1', ['roles' => ['editor']]),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toBeEmpty();
        });

        test('matchesWithShortCircuit returns null for non-matching rule', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'doc-1',
                action: 'delete',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            // Act
            $effect = $this->matcher->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBeNull();
        });

        test('matches returns false when underlying matcher returns false', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'admin',
                resource: 'doc-1',
                action: 'delete',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            // Act
            $result = $this->matcher->matches($rule, $subject, $resource, $action);

            // Assert
            expect($result)->toBeFalse();
        });

        test('short-circuit disabled still returns deny effect', function (): void {
            // Arrange
            $matcherNoShortCircuit = new OptimizedRuleMatcher($this->underlyingMatcher, shortCircuit: false);
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'delete',
                effect: Effect::Deny,
            );
            $matcherNoShortCircuit->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('delete');

            // Act
            $effect = $matcherNoShortCircuit->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBe(Effect::Deny);
        });
    });

    describe('Edge Cases', function (): void {
        test('empty rules array creates empty indexes', function (): void {
            // Arrange
            $this->matcher->indexRules([]);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1', ['roles' => ['admin']]),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toBeEmpty();
        });

        test('re-indexing clears previous indexes', function (): void {
            // Arrange
            $firstRules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
            ];
            $secondRules = [
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow),
            ];
            $this->matcher->indexRules($firstRules);

            // Act
            $this->matcher->indexRules($secondRules);

            $candidates = $this->matcher->getCandidateRules(
                subject('user-2'),
                resource('doc-2', 'document'),
                patrol_action('write'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('user-2');
        });

        test('multiple rules with same subject are all indexed', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-1', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('user-1', 'doc-3', 'delete', Effect::Allow),
                new PolicyRule('user-1', 'doc-4', 'read', Effect::Allow),
                new PolicyRule('user-1', 'doc-5', 'write', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->action)->toBe('read');
        });

        test('getCandidateRules applies precise matching filter', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-1', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('user-1', 'doc-3', 'delete', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->resource)->toBe('doc-1')
                ->and($candidates[0]->action)->toBe('read');
        });

        test('candidate filtering preserves rule order with sequential keys', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('user-1', 'doc-3', 'delete', Effect::Allow),
                new PolicyRule('user-3', 'doc-4', 'read', Effect::Allow),
                new PolicyRule('user-1', 'doc-5', 'write', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and(array_keys($candidates))->toBe([0]);
        });

        test('handles complex filtering with multiple rule attributes', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'edit', Effect::Allow),
                new PolicyRule('user-1', 'doc-2', 'edit', Effect::Allow),
                new PolicyRule('user-2', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('*', 'doc-3', 'read', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('edit'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('user-1')
                ->and($candidates[0]->resource)->toBe('doc-1')
                ->and($candidates[0]->action)->toBe('edit');
        });

        test('wildcard subject includes universal rules in candidates', function (): void {
            // Arrange
            $aclMatcher = new OptimizedRuleMatcher(
                new AclRuleMatcher(),
                shortCircuit: true,
            );
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'edit', Effect::Allow),
                new PolicyRule('*', 'doc-1', 'edit', Effect::Deny),
            ];
            $aclMatcher->indexRules($rules);

            // Act
            $candidates = $aclMatcher->getCandidateRules(
                subject('user-1', ['superuser' => true]),
                resource('doc-1', 'document'),
                patrol_action('edit'),
            );

            // Assert
            expect($candidates)->toHaveCount(2);
        });

        test('handles large rule sets efficiently with indexing', function (): void {
            // Arrange
            $rules = [];

            for ($i = 1; $i <= 100; ++$i) {
                $rules[] = new PolicyRule('user-'.$i, 'doc-'.$i, 'read', Effect::Allow);
            }

            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-50'),
                resource('doc-50', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('user-50');
        });

        test('matchesWithShortCircuit returns allow effect without short-circuiting', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'edit',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            // Act
            $effect = $this->matcher->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBe(Effect::Allow);
        });

        test('indexes maintain integrity with special characters in identifiers', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user:admin:1', 'doc-123-abc', 'read:write', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user:admin:1'),
                resource('doc-123-abc', 'document'),
                patrol_action('read:write'),
            );

            // Assert
            expect($candidates)->toHaveCount(1);
        });

        test('getCandidateRules returns empty when only wildcard rules exist but do not match', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('*', 'doc-1', 'read', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1', ['roles' => ['admin']]),
                resource('doc-2', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toBeEmpty();
        });

        test('indexes multiple null resource rules correctly', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', null, 'create', Effect::Allow),
                new PolicyRule('user-2', null, 'edit', Effect::Allow),
                new PolicyRule('user-3', null, 'read', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('any-resource', 'any-type'),
                patrol_action('create'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->subject)->toBe('user-1')
                ->and($candidates[0]->resource)->toBeNull();
        });

        test('short-circuit does not affect allow rules', function (): void {
            // Arrange
            $rule = new PolicyRule(
                subject: 'editor',
                resource: 'doc-1',
                action: 'edit',
                effect: Effect::Allow,
            );
            $this->matcher->indexRules([$rule]);
            $subject = subject('user-1', ['roles' => ['editor']]);
            $resource = resource('doc-1', 'document');
            $action = patrol_action('edit');

            // Act
            $effect = $this->matcher->matchesWithShortCircuit($rule, $subject, $resource, $action);

            // Assert
            expect($effect)->toBe(Effect::Allow);
        });

        test('combines direct subject ID and wildcard in candidate results', function (): void {
            // Arrange
            $aclMatcher = new OptimizedRuleMatcher(
                new AclRuleMatcher(),
                shortCircuit: true,
            );
            $rules = [
                new PolicyRule('user-123', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('*', 'doc-1', 'read', Effect::Allow),
            ];
            $aclMatcher->indexRules($rules);

            // Act
            $candidates = $aclMatcher->getCandidateRules(
                subject('user-123', ['superuser' => true]),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(2);
        });

        test('handles empty attributes in subjects and resources', function (): void {
            // Arrange
            $rule = new PolicyRule('admin', 'doc-1', 'read', Effect::Allow);
            $this->matcher->indexRules([$rule]);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1', []),
                resource('doc-1', 'document', []),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toBeEmpty();
        });

        test('filters out rules that match subject but not resource or action', function (): void {
            // Arrange
            $rules = [
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow),
                new PolicyRule('user-1', 'doc-2', 'write', Effect::Allow),
                new PolicyRule('user-1', 'doc-3', 'delete', Effect::Allow),
            ];
            $this->matcher->indexRules($rules);

            // Act
            $candidates = $this->matcher->getCandidateRules(
                subject('user-1'),
                resource('doc-1', 'document'),
                patrol_action('read'),
            );

            // Assert
            expect($candidates)->toHaveCount(1)
                ->and($candidates[0]->resource)->toBe('doc-1')
                ->and($candidates[0]->action)->toBe('read');
        });
    });
});
