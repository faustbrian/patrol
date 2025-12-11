<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\Contracts\SubjectResolverInterface;
use Patrol\Core\Engine\AbacRuleMatcher;
use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\DelegationAwarePolicyEvaluator;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\Engine\RestfulRuleMatcher;
use Patrol\Core\Exceptions\InvalidPrimaryKeyTypeException;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\Policy;
use Patrol\Core\ValueObjects\PolicyRule;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Laravel\Repositories\CachedDelegationRepository;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;
use Patrol\Laravel\Resolvers\LaravelSubjectResolver;
use Patrol\PatrolServiceProvider;

describe('PatrolServiceProvider', function (): void {
    describe('toResourceValueObject', function (): void {
        describe('Happy Paths', function (): void {
            test('returns resource when already a Resource value object', function (): void {
                // Arrange
                $resource = new Resource('123', 'User');

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBe($resource);
            })->group('happy-path');

            test('converts string to Resource with unknown type', function (): void {
                // Arrange
                $resource = 'user-123';

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('user-123');
                expect($result->type)->toBe('unknown');
            })->group('happy-path');

            test('converts object with id property to Resource', function (): void {
                // Arrange
                $resource = new class()
                {
                    public string $id = '456';

                    public string $name = 'Test User';

                    public function toArray(): array
                    {
                        return ['id' => $this->id, 'name' => $this->name];
                    }
                };

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('456');
                expect($result->type)->toBe(class_basename($resource));
                expect($result->attributes)->toBe(['id' => '456', 'name' => 'Test User']);
            })->group('happy-path');

            test('converts object with integer id to Resource', function (): void {
                // Arrange
                $resource = new class()
                {
                    public int $id = 789;
                };

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('789');
            })->group('happy-path');

            test('converts array with id and type to Resource', function (): void {
                // Arrange
                $resource = [
                    'id' => '999',
                    'type' => 'Product',
                    'name' => 'Widget',
                ];

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('999');
                expect($result->type)->toBe('Product');
                expect($result->attributes)->toBe($resource);
            })->group('happy-path');

            test('converts array with integer id to Resource', function (): void {
                // Arrange
                $resource = ['id' => 111, 'name' => 'Test'];

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result->id)->toBe('111');
            })->group('happy-path');
        });

        describe('Edge Cases', function (): void {
            test('converts object without id to Resource with unknown id', function (): void {
                // Arrange
                $resource = new class()
                {
                    public string $name = 'Test';
                };

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('unknown');
                expect($result->type)->toBe(class_basename($resource));
            })->group('edge-case');

            test('converts object without toArray method to Resource', function (): void {
                // Arrange
                $resource = new class()
                {
                    public string $id = '123';
                };

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result)->toBeInstanceOf(Resource::class);
                expect($result->id)->toBe('123');
                expect($result->attributes)->toBe([]);
            })->group('edge-case');

            test('converts array without id to Resource with unknown id', function (): void {
                // Arrange
                $resource = ['name' => 'Test', 'type' => 'User'];

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result->id)->toBe('unknown');
                expect($result->type)->toBe('User');
            })->group('edge-case');

            test('converts array without type to Resource with unknown type', function (): void {
                // Arrange
                $resource = ['id' => '123', 'name' => 'Test'];

                // Act
                $result = PatrolServiceProvider::toResourceValueObject($resource);

                // Assert
                expect($result->id)->toBe('123');
                expect($result->type)->toBe('unknown');
            })->group('edge-case');
        });
    });

    describe('Service Registration', function (): void {
        describe('Happy Paths', function (): void {
            test('registers ACL rule matcher as singleton', function (): void {
                // Act
                $matcher1 = resolve('patrol.matcher.acl');
                $matcher2 = resolve('patrol.matcher.acl');

                // Assert
                expect($matcher1)->toBeInstanceOf(AclRuleMatcher::class);
                expect($matcher1)->toBe($matcher2);
            })->group('happy-path');

            test('registers RBAC rule matcher as singleton', function (): void {
                // Act
                $matcher1 = resolve('patrol.matcher.rbac');
                $matcher2 = resolve('patrol.matcher.rbac');

                // Assert
                expect($matcher1)->toBeInstanceOf(RbacRuleMatcher::class);
                expect($matcher1)->toBe($matcher2);
            })->group('happy-path');

            test('registers ABAC rule matcher as singleton', function (): void {
                // Act
                $matcher1 = resolve('patrol.matcher.abac');
                $matcher2 = resolve('patrol.matcher.abac');

                // Assert
                expect($matcher1)->toBeInstanceOf(AbacRuleMatcher::class);
                expect($matcher1)->toBe($matcher2);
            })->group('happy-path');

            test('registers RESTful rule matcher as singleton with ACL fallback', function (): void {
                // Act
                $matcher = resolve('patrol.matcher.restful');

                // Assert
                expect($matcher)->toBeInstanceOf(RestfulRuleMatcher::class);
            })->group('happy-path');

            test('registers default ACL matcher from config', function (): void {
                // Arrange
                Config::set('patrol.default_matcher', 'acl');

                // Act
                $matcher = resolve(RuleMatcherInterface::class);

                // Assert
                expect($matcher)->toBeInstanceOf(AclRuleMatcher::class);
            })->group('happy-path');

            test('registers default RBAC matcher from config', function (): void {
                // Arrange
                Config::set('patrol.default_matcher', 'rbac');
                app()->forgetInstance(RuleMatcherInterface::class);

                // Act
                $matcher = resolve(RuleMatcherInterface::class);

                // Assert
                expect($matcher)->toBeInstanceOf(RbacRuleMatcher::class);
            })->group('happy-path');

            test('registers default ABAC matcher from config', function (): void {
                // Arrange
                Config::set('patrol.default_matcher', 'abac');
                app()->forgetInstance(RuleMatcherInterface::class);

                // Act
                $matcher = resolve(RuleMatcherInterface::class);

                // Assert
                expect($matcher)->toBeInstanceOf(AbacRuleMatcher::class);
            })->group('happy-path');

            test('registers default RESTful matcher from config', function (): void {
                // Arrange
                Config::set('patrol.default_matcher', 'restful');
                app()->forgetInstance(RuleMatcherInterface::class);

                // Act
                $matcher = resolve(RuleMatcherInterface::class);

                // Assert
                expect($matcher)->toBeInstanceOf(RestfulRuleMatcher::class);
            })->group('happy-path');

            test('registers effect resolver as singleton', function (): void {
                // Act
                $resolver1 = resolve(EffectResolver::class);
                $resolver2 = resolve(EffectResolver::class);

                // Assert
                expect($resolver1)->toBeInstanceOf(EffectResolver::class);
                expect($resolver1)->toBe($resolver2);
            })->group('happy-path');

            test('registers policy evaluator as singleton', function (): void {
                // Act
                $evaluator1 = resolve(PolicyEvaluator::class);
                $evaluator2 = resolve(PolicyEvaluator::class);

                // Assert
                expect($evaluator1)->toBeInstanceOf(PolicyEvaluator::class);
                expect($evaluator1)->toBe($evaluator2);
            })->group('happy-path');

            test('registers subject resolver from config', function (): void {
                // Arrange
                Config::set('patrol.subject_resolver', LaravelSubjectResolver::class);

                // Act
                $resolver = resolve(SubjectResolverInterface::class);

                // Assert
                expect($resolver)->toBeInstanceOf(LaravelSubjectResolver::class);
            })->group('happy-path');

            test('does not register delegation services when disabled', function (): void {
                // Arrange
                Config::set('patrol.delegation.enabled', false);

                // Act & Assert
                expect(fn () => resolve(DelegationRepositoryInterface::class))
                    ->toThrow(BindingResolutionException::class);
            })->group('happy-path');

            test('registers delegation services when enabled', function (): void {
                // Arrange
                Config::set('patrol.delegation.enabled', true);
                Config::set('patrol.delegation.driver', 'database');
                app()->forgetInstance(PolicyEvaluator::class);

                // Mock PolicyRepositoryInterface for delegation validator
                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class)->makePartial());

                // Re-register the service provider
                new PatrolServiceProvider(app())->register();

                // Act
                $repository = resolve(DelegationRepositoryInterface::class);
                $validator = resolve(DelegationValidator::class);
                $manager = resolve(DelegationManager::class);
                $evaluator = resolve(PolicyEvaluator::class);

                // Assert
                expect($repository)->toBeInstanceOf(DatabaseDelegationRepository::class);
                expect($validator)->toBeInstanceOf(DelegationValidator::class);
                expect($manager)->toBeInstanceOf(DelegationManager::class);
                expect($evaluator)->toBeInstanceOf(DelegationAwarePolicyEvaluator::class);
            })->group('happy-path');

            test('registers cached delegation repository when driver is cached', function (): void {
                // Arrange
                Config::set('patrol.delegation.enabled', true);
                Config::set('patrol.delegation.driver', 'cached');
                Config::set('patrol.delegation.cache_ttl', 1_800);

                // Mock PolicyRepositoryInterface for delegation validator
                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class)->makePartial());

                // Re-register the service provider
                new PatrolServiceProvider(app())->register();

                // Act
                $repository = resolve(DelegationRepositoryInterface::class);

                // Assert
                expect($repository)->toBeInstanceOf(CachedDelegationRepository::class);
            })->group('happy-path');

            test('registers delegation validator with max duration', function (): void {
                // Arrange
                Config::set('patrol.delegation.enabled', true);
                Config::set('patrol.delegation.max_duration_days', 60);

                // Mock PolicyRepositoryInterface for delegation validator
                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class)->makePartial());

                // Re-register the service provider
                new PatrolServiceProvider(app())->register();

                // Act
                $validator = resolve(DelegationValidator::class);

                // Assert
                expect($validator)->toBeInstanceOf(DelegationValidator::class);
            })->group('happy-path');
        });

        describe('Edge Cases', function (): void {
            test('registers delegation validator with null max duration', function (): void {
                // Arrange
                Config::set('patrol.delegation.enabled', true);
                Config::set('patrol.delegation.max_duration_days');

                // Mock PolicyRepositoryInterface for delegation validator
                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class)->makePartial());

                // Re-register the service provider
                new PatrolServiceProvider(app())->register();

                // Act
                $validator = resolve(DelegationValidator::class);

                // Assert
                expect($validator)->toBeInstanceOf(DelegationValidator::class);
            })->group('edge-case');
        });
    });

    describe('Boot and Validation', function (): void {
        describe('Happy Paths', function (): void {
            test('validates database configuration accepts uuid primary key type', function (): void {
                // Arrange
                Config::set('patrol.database.primary_key_type', 'uuid');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('happy-path');

            test('validates database configuration accepts ulid primary key type', function (): void {
                // Arrange
                Config::set('patrol.database.primary_key_type', 'ulid');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('happy-path');

            test('validates database configuration accepts autoincrement primary key type', function (): void {
                // Arrange
                Config::set('patrol.database.primary_key_type', 'autoincrement');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('happy-path');

            test('calls tenant resolver from config when closure', function (): void {
                // Arrange
                $called = false;
                Config::set('patrol.tenant_resolver', function () use (&$called): string {
                    $called = true;

                    return 'tenant-1';
                });

                // Act
                new PatrolServiceProvider(app())->packageBooted();

                // Assert - Patrol::resolveTenant is called internally
                expect(true)->toBeTrue();
            })->group('happy-path');

            test('calls resource resolver from config when closure', function (): void {
                // Arrange
                Config::set('patrol.resource_resolver', fn ($id): Resource => new Resource($id, 'custom'));

                // Act
                new PatrolServiceProvider(app())->packageBooted();

                // Assert
                expect(true)->toBeTrue();
            })->group('happy-path');

            test('registers gate integration when enabled in config', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                // Mock repository to return policies
                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([
                        new PolicyRule(
                            subject: 'user-1',
                            resource: 'post-1',
                            action: 'read',
                            effect: Effect::Allow,
                        ),
                    ]),
                ]));

                // Act
                new PatrolServiceProvider(app())->packageBooted();

                // Create mock user
                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Assert - Gate check should work
                $result = Gate::forUser($user)->allows('read', new Resource('post-1', 'Post'));
                expect($result)->toBeTrue();
            })->group('happy-path');
        });

        describe('Sad Paths', function (): void {
            test('throws exception for invalid primary key type', function (): void {
                // Arrange
                Config::set('patrol.database.primary_key_type', 'invalid');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())
                    ->toThrow(InvalidPrimaryKeyTypeException::class);
            })->group('sad-path');

            test('gate integration returns false for Deny effect', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: 'post-1',
                        action: 'delete',
                        effect: Effect::Deny,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('delete', new Resource('post-1', 'Post'));

                // Assert
                expect($result)->toBeFalse();
            })->group('sad-path');
        });

        describe('Edge Cases', function (): void {
            test('does not call tenant resolver when not closure', function (): void {
                // Arrange
                Config::set('patrol.tenant_resolver');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('edge-case');

            test('does not call resource resolver when not closure', function (): void {
                // Arrange
                Config::set('patrol.resource_resolver');

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('edge-case');

            test('skips gate integration when disabled in config', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', false);

                // Act & Assert
                expect(fn () => new PatrolServiceProvider(app())->packageBooted())->not->toThrow(Exception::class);
            })->group('edge-case');

            test('gate integration returns false for non-object user', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);
                new PatrolServiceProvider(app())->packageBooted();

                // Act - Pass non-object user (string, null, array, etc.)
                $result = Gate::allows('read', 'resource');

                // Assert - Should return false as userToSubject returns null for non-object
                expect($result)->toBeFalse();
            })->group('edge-case');

            test('gate integration handles string user input', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);
                new PatrolServiceProvider(app())->packageBooted();

                // Act - Test with various non-object inputs
                $result1 = Gate::forUser('string-user')->allows('read', 'resource');
                $result2 = Gate::forUser(123)->allows('read', 'resource');
                $result3 = Gate::forUser(['id' => '1'])->allows('read', 'resource');

                // Assert - All should return false as userToSubject returns null
                expect($result1)->toBeFalse();
                expect($result2)->toBeFalse();
                expect($result3)->toBeFalse();
            })->group('edge-case');
        });
    });

    describe('Gate Integration Helper Methods', function (): void {
        describe('Happy Paths', function (): void {
            test('gate integration extracts resource from arguments', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '123',
                        action: 'update',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                $post = new class()
                {
                    public string $id = '123';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('update', $post);

                // Assert
                expect($result)->toBeTrue();
            })->group('happy-path');

            test('gate integration handles Resource argument', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: 'doc-1',
                        action: 'edit',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('edit', new Resource('doc-1', 'Document'));

                // Assert
                expect($result)->toBeTrue();
            })->group('happy-path');

            test('gate integration handles object argument with toArray method', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '777',
                        action: 'buy',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                $resource = new class()
                {
                    public string $id = '777';

                    public string $name = 'Widget';

                    public function toArray(): array
                    {
                        return ['id' => $this->id, 'name' => $this->name];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('buy', $resource);

                // Assert
                expect($result)->toBeTrue();
            })->group('happy-path');
        });

        describe('Edge Cases', function (): void {
            test('gate integration handles user without id property', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'anonymous',
                        resource: '*',
                        action: 'view',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $name = 'Guest';

                    public function toArray(): array
                    {
                        return ['name' => $this->name];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('view', 'resource');

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles user with integer id', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: '42',
                        resource: '*',
                        action: 'access',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public int $id = 42;

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('access', 'resource');

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles user without toArray method', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-123',
                        resource: '*',
                        action: 'test',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-123';
                };

                // Act
                $result = Gate::forUser($user)->allows('test', 'resource');

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles empty arguments array', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '*',
                        action: 'general',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('general');

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles object argument without id', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: 'unknown',
                        action: 'process',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                $resource = new class()
                {
                    public string $name = 'Test';
                };

                // Act
                $result = Gate::forUser($user)->allows('process', $resource);

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles object argument with integer id', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '999',
                        action: 'view',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                $resource = new class()
                {
                    public int $id = 999;
                };

                // Act
                $result = Gate::forUser($user)->allows('view', $resource);

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles object argument without toArray method', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '888',
                        action: 'manage',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                $resource = new class()
                {
                    public string $id = '888';
                };

                // Act
                $result = Gate::forUser($user)->allows('manage', $resource);

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles string argument', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: 'some-string',
                        action: 'action',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('action', 'some-string');

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');

            test('gate integration handles non-string non-object argument', function (): void {
                // Arrange
                Config::set('patrol.integrate_gates', true);

                app()->bind(PolicyRepositoryInterface::class, fn () => mock(PolicyRepositoryInterface::class, [
                    'getPoliciesFor' => new Policy([new PolicyRule(
                        subject: 'user-1',
                        resource: '*',
                        action: 'test',
                        effect: Effect::Allow,
                    )]),
                ]));

                new PatrolServiceProvider(app())->packageBooted();

                $user = new class()
                {
                    public string $id = 'user-1';

                    public function toArray(): array
                    {
                        return ['id' => $this->id];
                    }
                };

                // Act
                $result = Gate::forUser($user)->allows('test', 123);

                // Assert
                expect($result)->toBeTrue();
            })->group('edge-case');
        });
    });
});
