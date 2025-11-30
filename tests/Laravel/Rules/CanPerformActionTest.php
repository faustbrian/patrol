<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Priority;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Laravel\Patrol;
use Patrol\Laravel\Rules\CanPerformAction;

beforeEach(function (): void {
    $this->post = new class()
    {
        public string $id = 'post-999';

        public string $title = 'Test Post';

        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'title' => $this->title,
            ];
        }
    };
});

describe('CanPerformAction Validation Rule', function (): void {
    describe('Happy Paths', function (): void {
        test('validation passes when user is authorized', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-321']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-321', 'post-999', 'edit', Effect::Allow, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction($this->post, 'edit');

            $failCalled = false;

            // Act
            $rule->validate('action', 'edit', function () use (&$failCalled): void {
                $failCalled = true;
            });

            // Assert
            expect($failCalled)->toBeFalse();
        });

        test('uses field value as action when action not provided in constructor', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-444']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-444', 'post-999', 'publish', Effect::Allow, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction($this->post);

            $failCalled = false;

            // Act
            $rule->validate('action', 'publish', function () use (&$failCalled): void {
                $failCalled = true;
            });

            // Assert
            expect($failCalled)->toBeFalse();
        });

        test('works with string resource identifiers', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-555']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-555', 'document-888', 'view', Effect::Allow, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction('document-888', 'view');

            $failCalled = false;

            // Act
            $rule->validate('action', 'view', function () use (&$failCalled): void {
                $failCalled = true;
            });

            // Assert
            expect($failCalled)->toBeFalse();
        });

        test('works with array resources', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-666']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-666', 'file-777', 'download', Effect::Allow, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction(['id' => 'file-777', 'type' => 'file'], 'download');

            $failCalled = false;

            // Act
            $rule->validate('action', 'download', function () use (&$failCalled): void {
                $failCalled = true;
            });

            // Assert
            expect($failCalled)->toBeFalse();
        });

        test('works with Resource instance directly', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-888']);

            $resource = new Resource('doc-999', 'Document');

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-888', 'doc-999', 'read', Effect::Allow, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction($resource, 'read');

            $failCalled = false;

            // Act
            $rule->validate('action', 'read', function () use (&$failCalled): void {
                $failCalled = true;
            });

            // Assert
            expect($failCalled)->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('validation fails when user is not authorized', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-321']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-321', 'post-999', 'delete', Effect::Deny, new Priority(1)),
                    ]),
                );

            $rule = new CanPerformAction($this->post, 'delete');

            $failMessage = null;

            // Act
            $rule->validate('action', 'delete', function (string $message) use (&$failMessage): void {
                $failMessage = $message;
            });

            // Assert
            expect($failMessage)->toContain('You are not authorized to delete');
        });

        test('validation fails when user is not authenticated', function (): void {
            // Arrange
            Patrol::resolveSubject(fn (): null => null);

            $rule = new CanPerformAction($this->post, 'edit');

            $failMessage = null;

            // Act
            $rule->validate('action', 'edit', function (string $message) use (&$failMessage): void {
                $failMessage = $message;
            });

            // Assert
            expect($failMessage)->toBe('You must be authenticated to perform this action.');
        });

        test('validation fails with generic message for unknown resource type', function (): void {
            // Arrange
            Patrol::resolveSubject(fn () => (object) ['id' => 'user-777']);

            $this->mock(PolicyRepositoryInterface::class)
                ->shouldReceive('getPoliciesFor')
                ->andReturn(
                    new Policy([
                        new PolicyRule('user-777', 'unknown-resource', 'edit', Effect::Deny, new Priority(1)),
                    ]),
                );

            // Create a resource with unknown type by passing a string
            $rule = new CanPerformAction('unknown-resource', 'edit');

            $failMessage = null;

            // Act
            $rule->validate('action', 'edit', function (string $message) use (&$failMessage): void {
                $failMessage = $message;
            });

            // Assert
            expect($failMessage)->toBe('You are not authorized to edit this resource.');
        });
    });
});

afterEach(function (): void {
    Patrol::reset();
});
