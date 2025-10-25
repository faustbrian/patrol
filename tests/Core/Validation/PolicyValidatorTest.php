<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Validation\PolicyValidator;
use Patrol\Core\ValueObjects\Domain;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;

describe('PolicyValidator', function (): void {
    describe('Happy Paths', function (): void {
        test('validates policy with no errors returns valid', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow, new Priority(2)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureNoCycles()
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->hasErrors())->toBeFalse();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates policy with consistent priorities of same effect', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates policy with proper deny-override where deny has highest priority', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(100)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates policy with no conflicts when only allows present', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-2', 'read', Effect::Allow, new Priority(2)),
                new PolicyRule('user-2', 'doc-1', 'write', Effect::Allow, new Priority(3)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates policy with no conflicts when only denies present', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
                new PolicyRule('user-1', 'doc-2', 'read', Effect::Deny, new Priority(2)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates empty policy successfully', function (): void {
            $policy = new Policy([]);

            $validator = PolicyValidator::validate($policy)
                ->ensureNoCycles()
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates single rule policy successfully', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureNoCycles()
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('validates policy with all validation checks combined', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'documents:*', 'read', Effect::Allow, new Priority(50)),
                new PolicyRule('user-1', 'doc-1', 'write', Effect::Allow, new Priority(10)),
                new PolicyRule('*', 'secrets:*', 'read', Effect::Deny, new Priority(100)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureNoCycles()
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
            expect($validator->hasErrors())->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('detects inconsistent priorities with same priority but different effects for same subject resource action', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeFalse();
            expect($validator->hasErrors())->toBeTrue();
            expect($validator->getErrors())->toHaveCount(1);
            expect($validator->getErrors()[0])->toContain('Inconsistent priority');
            expect($validator->getErrors()[0])->toContain('user-1:doc-1:read');
            expect($validator->getErrors()[0])->toContain('priority 1');
        });

        test('detects conflict when allow and deny present but deny not highest priority', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(50)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(100)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator->isValid())->toBeFalse();
            expect($validator->hasErrors())->toBeTrue();
            expect($validator->getErrors())->toHaveCount(1);
            expect($validator->getErrors()[0])->toContain('Potential conflict');
            expect($validator->getErrors()[0])->toContain('user-1:doc-1:read');
            expect($validator->getErrors()[0])->toContain('highest priority is not Deny');
        });

        test('detects multiple inconsistent priorities for different rule groups', function (): void {
            $policy = new Policy([
                // First conflict: user-1:doc-1:read
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
                // Second conflict: user-2:doc-2:write
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow, new Priority(5)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Deny, new Priority(5)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeFalse();
            expect($validator->getErrors())->toHaveCount(2);
            expect($validator->getErrors()[0])->toContain('Inconsistent priority');
            expect($validator->getErrors()[1])->toContain('Inconsistent priority');
        });

        test('detects multiple inconsistencies detected simultaneously across different validation checks', function (): void {
            $policy = new Policy([
                // Inconsistent priority conflict (will also trigger conflict check)
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
                // Deny not highest priority conflict
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Deny, new Priority(10)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow, new Priority(50)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeFalse();
            expect($validator->hasErrors())->toBeTrue();
            // First group triggers both inconsistent priority AND conflict (3 total)
            expect($validator->getErrors())->toHaveCount(3);
        });

        test('getErrors returns all validation errors accumulated', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Allow, new Priority(2)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Deny, new Priority(2)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            $errors = $validator->getErrors();

            expect($errors)->toBeArray();
            expect($errors)->toHaveCount(2);
            expect($errors[0])->toBeString();
            expect($errors[1])->toBeString();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles null resource in validation correctly', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', null, 'create-document', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', null, 'create-document', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeTrue();
            expect($validator->getErrors())->toBe([]);
        });

        test('handles null resource with conflicting effects at same priority', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', null, 'create-document', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', null, 'create-document', Effect::Deny, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeFalse();
            expect($validator->getErrors())->toHaveCount(1);
            expect($validator->getErrors()[0])->toContain('user-1:null:create-document');
        });

        test('handles wildcard subjects in validation', function (): void {
            $policy = new Policy([
                new PolicyRule('*', 'doc-1', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('*', 'doc-1', 'read', Effect::Deny, new Priority(100)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
        });

        test('handles wildcard resources in validation', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', '*', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', '*', 'read', Effect::Deny, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator->isValid())->toBeFalse();
            expect($validator->getErrors()[0])->toContain('user-1:*:read');
        });

        test('handles very high priority values', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(999_999)),
                new PolicyRule('user-1', 'doc-2', 'read', Effect::Deny, new Priority(999_998)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
        });

        test('handles zero priority values', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(0)),
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Deny, new Priority(0)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
        });

        test('handles multiple rules with incrementing priorities', function (): void {
            $rules = [];

            for ($i = 1; $i <= 100; ++$i) {
                $rules[] = new PolicyRule('user-'.$i, 'doc-'.$i, 'read', Effect::Allow, new Priority($i));
            }

            $policy = new Policy($rules);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($validator->isValid())->toBeTrue();
        });

        test('validates domain-scoped rules correctly', function (): void {
            $domain1 = new Domain('org-1');
            $domain2 = new Domain('org-2');

            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1), $domain1),
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1), $domain2),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            // Note: Current implementation doesn't consider domain when grouping rules
            // This test documents the current behavior, not ideal behavior
            expect($validator->isValid())->toBeFalse();
            expect($validator->getErrors())->toHaveCount(1);
            expect($validator->getErrors()[0])->toContain('user-1:doc-1:read');
        });

        test('handles pattern-based resource identifiers', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'documents:*', 'read', Effect::Allow, new Priority(1)),
                new PolicyRule('user-1', 'documents:confidential:*', 'read', Effect::Deny, new Priority(100)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            // Different patterns are different resources, no conflict
            expect($validator->isValid())->toBeTrue();
        });

        test('handles conflict detection with single rule', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            // Single rule cannot conflict with itself
            expect($validator->isValid())->toBeTrue();
        });
    });

    describe('Regressions', function (): void {
        test('ensureConsistentPriorities handles uninitialized policy gracefully', function (): void {
            // Arrange
            $validator = new PolicyValidator();

            // Act
            $result = $validator->ensureConsistentPriorities();

            // Assert
            expect($result)->toBeInstanceOf(PolicyValidator::class);
            expect($result->isValid())->toBeTrue();
            expect($result->getErrors())->toBe([]);
        });

        test('checkForConflicts handles uninitialized policy gracefully', function (): void {
            // Arrange
            $validator = new PolicyValidator();

            // Act
            $result = $validator->checkForConflicts();

            // Assert
            expect($result)->toBeInstanceOf(PolicyValidator::class);
            expect($result->isValid())->toBeTrue();
            expect($result->getErrors())->toBe([]);
        });

        test('validation does not modify the original policy', function (): void {
            $rule1 = new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1));
            $rule2 = new PolicyRule('user-1', 'doc-1', 'read', Effect::Deny, new Priority(1));
            $policy = new Policy([$rule1, $rule2]);

            $originalRulesCount = count($policy->rules);

            PolicyValidator::validate($policy)
                ->ensureConsistentPriorities()
                ->checkForConflicts();

            expect($policy->rules)->toHaveCount($originalRulesCount);
            expect($policy->rules[0])->toBe($rule1);
            expect($policy->rules[1])->toBe($rule2);
        });

        test('can validate multiple policies with same validator instance', function (): void {
            $policy1 = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $policy2 = new Policy([
                new PolicyRule('user-2', 'doc-2', 'write', Effect::Deny, new Priority(2)),
            ]);

            $validator1 = PolicyValidator::validate($policy1)
                ->ensureConsistentPriorities();
            expect($validator1->isValid())->toBeTrue();

            $validator2 = PolicyValidator::validate($policy2)
                ->ensureConsistentPriorities();
            expect($validator2->isValid())->toBeTrue();

            // Validators should be independent
            expect($validator1->getErrors())->toBe([]);
            expect($validator2->getErrors())->toBe([]);
        });

        test('error messages are descriptive and specific for inconsistent priorities', function (): void {
            $policy = new Policy([
                new PolicyRule('admin', 'secrets:api-keys', 'read', Effect::Allow, new Priority(50)),
                new PolicyRule('admin', 'secrets:api-keys', 'read', Effect::Deny, new Priority(50)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            $errors = $validator->getErrors();

            expect($errors)->toHaveCount(1);
            expect($errors[0])->toContain('Inconsistent priority');
            expect($errors[0])->toContain('admin:secrets:api-keys:read');
            expect($errors[0])->toContain('priority 50');
            expect($errors[0])->toContain('conflicting effects');
        });

        test('error messages are descriptive and specific for conflicts', function (): void {
            $policy = new Policy([
                new PolicyRule('editor', 'articles:*', 'publish', Effect::Allow, new Priority(100)),
                new PolicyRule('editor', 'articles:*', 'publish', Effect::Deny, new Priority(50)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            $errors = $validator->getErrors();

            expect($errors)->toHaveCount(1);
            expect($errors[0])->toContain('Potential conflict');
            expect($errors[0])->toContain('editor:articles:*:publish');
            expect($errors[0])->toContain('Allow and Deny');
            expect($errors[0])->toContain('highest priority is not Deny');
        });

        test('ensureNoCycles returns validator instance for method chaining', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureNoCycles();

            expect($validator)->toBeInstanceOf(PolicyValidator::class);
        });

        test('ensureConsistentPriorities returns validator instance for method chaining', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->ensureConsistentPriorities();

            expect($validator)->toBeInstanceOf(PolicyValidator::class);
        });

        test('checkForConflicts returns validator instance for method chaining', function (): void {
            $policy = new Policy([
                new PolicyRule('user-1', 'doc-1', 'read', Effect::Allow, new Priority(1)),
            ]);

            $validator = PolicyValidator::validate($policy)
                ->checkForConflicts();

            expect($validator)->toBeInstanceOf(PolicyValidator::class);
        });
    });
});
