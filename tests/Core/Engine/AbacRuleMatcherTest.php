<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Engine\AbacRuleMatcher;
use Patrol\Core\Engine\AttributeResolver;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PolicyRule;

describe('AbacRuleMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new AbacRuleMatcher(
            new AttributeResolver(),
        );
    });

    describe('Happy Paths', function (): void {
        test('matches when resource owner equals subject id', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.owner == subject.id',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document', ['owner' => 'user-1']);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches when subject department equals resource department', function (): void {
            $rule = new PolicyRule(
                subject: 'subject.department == resource.department',
                resource: 'document:*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['department' => 'engineering']);
            $resource = resource('doc-1', 'document', ['department' => 'engineering']);
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches with inequality condition', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.status != archived',
                resource: 'document:*',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['status' => 'draft']);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('falls back to direct subject ID match', function (): void {
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

        test('matches with wildcard resource', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.owner == subject.id',
                resource: '*',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('any-resource', 'any-type', ['owner' => 'user-1']);
            $action = patrol_action('delete');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('rejects when resource owner does not equal subject id', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.owner == subject.id',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document', ['owner' => 'user-2']);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when departments do not match', function (): void {
            $rule = new PolicyRule(
                subject: 'subject.department == resource.department',
                resource: 'document:*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1', ['department' => 'engineering']);
            $resource = resource('doc-1', 'document', ['department' => 'sales']);
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when inequality condition fails', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.status != archived',
                resource: 'document:*',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['status' => 'archived']);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when action does not match', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.owner == subject.id',
                resource: 'document-1',
                action: 'delete',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document', ['owner' => 'user-1']);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('handles missing attributes gracefully', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.owner == subject.id',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document'); // No owner attribute
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('handles null resource for type-based permissions', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: null,
                action: 'create-document',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('any-id', 'any-type');
            $action = patrol_action('create-document');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('handles complex attribute paths', function (): void {
            $rule = new PolicyRule(
                subject: 'resource.metadata.createdBy == subject.id',
                resource: 'document-1',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document', [
                'metadata.createdBy' => 'user-1',
            ]);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches when resource field contains attribute condition with equality', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'resource.protected == false',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['protected' => false]);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('matches when resource field contains attribute condition with inequality', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'resource.status != archived',
                action: 'view',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['status' => 'active']);
            $action = patrol_action('view');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeTrue();
        });

        test('rejects when resource field attribute condition fails', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'resource.protected == false',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('doc-1', 'document', ['protected' => true]);
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when resource does not match any condition', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document-999',
                action: 'edit',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('document-1', 'document');
            $action = patrol_action('edit');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });

        test('rejects when type wildcard does not match resource type', function (): void {
            $rule = new PolicyRule(
                subject: 'user-1',
                resource: 'document:*',
                action: 'read',
                effect: Effect::Allow,
            );

            $subject = subject('user-1');
            $resource = resource('file-1', 'file');
            $action = patrol_action('read');

            expect($this->matcher->matches($rule, $subject, $resource, $action))->toBeFalse();
        });
    });
});
