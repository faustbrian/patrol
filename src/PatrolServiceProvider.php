<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Patrol;

use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Override;
use Patrol\Core\Contracts\DelegationRepositoryInterface;
use Patrol\Core\Contracts\PolicyRepositoryInterface;
use Patrol\Core\Contracts\RuleMatcherInterface;
use Patrol\Core\Contracts\SubjectResolverInterface;
use Patrol\Core\Engine\AbacRuleMatcher;
use Patrol\Core\Engine\AclRuleMatcher;
use Patrol\Core\Engine\AttributeResolver;
use Patrol\Core\Engine\DelegationAwarePolicyEvaluator;
use Patrol\Core\Engine\DelegationManager;
use Patrol\Core\Engine\DelegationValidator;
use Patrol\Core\Engine\EffectResolver;
use Patrol\Core\Engine\PolicyEvaluator;
use Patrol\Core\Engine\RbacRuleMatcher;
use Patrol\Core\Engine\RestfulRuleMatcher;
use Patrol\Core\Exceptions\InvalidPrimaryKeyTypeException;
use Patrol\Core\ValueObjects\Action;
use Patrol\Core\ValueObjects\Effect;
use Patrol\Core\ValueObjects\PrimaryKeyType;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Subject;
use Patrol\Laravel\Console\Commands\PatrolCheckCommand;
use Patrol\Laravel\Console\Commands\PatrolClearCacheCommand;
use Patrol\Laravel\Console\Commands\PatrolDelegationCleanupCommand;
use Patrol\Laravel\Console\Commands\PatrolDelegationListCommand;
use Patrol\Laravel\Console\Commands\PatrolDelegationRevokeCommand;
use Patrol\Laravel\Console\Commands\PatrolExplainCommand;
use Patrol\Laravel\Console\Commands\PatrolMigrateFromSpatieCommand;
use Patrol\Laravel\Console\Commands\PatrolPoliciesCommand;
use Patrol\Laravel\Middleware\PatrolMiddleware;
use Patrol\Laravel\Patrol;
use Patrol\Laravel\Repositories\CachedDelegationRepository;
use Patrol\Laravel\Repositories\DatabaseDelegationRepository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function array_key_exists;
use function assert;
use function class_basename;
use function config;
use function in_array;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function throw_unless;

/**
 * Laravel service provider for the Patrol authorization package.
 *
 * Registers all necessary components for the Patrol authorization system,
 * including rule matchers (ACL, RBAC, ABAC, RESTful), effect resolvers,
 * and the policy evaluator. Also publishes configuration files and sets up
 * custom resolvers from application configuration.
 *
 * This provider is auto-discovered by Laravel and handles the integration
 * of Patrol's authorization primitives with Laravel's service container.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PatrolServiceProvider extends PackageServiceProvider
{
    /**
     * Convert various resource formats to a Resource value object.
     *
     * @param  array<string, mixed>|object|string $resource Resource to convert
     * @return resource                           Resource value object
     */
    public static function toResourceValueObject(object|array|string $resource): Resource
    {
        if ($resource instanceof Resource) {
            return $resource;
        }

        if (is_string($resource)) {
            return new Resource($resource, 'unknown');
        }

        $id = 'unknown';
        $type = 'unknown';
        $attributes = [];

        if (is_object($resource)) {
            if (property_exists($resource, 'id')) {
                $idValue = $resource->id;

                if (is_string($idValue) || is_int($idValue)) {
                    $id = (string) $idValue;
                }
            }

            $type = class_basename($resource);

            if (method_exists($resource, 'toArray')) {
                $result = $resource->toArray();

                if (is_array($result)) {
                    /** @var array<string, mixed> $attributes */
                    $attributes = $result;
                }
            }
        } elseif (is_array($resource)) {
            if (array_key_exists('id', $resource) && (is_string($resource['id']) || is_int($resource['id']))) {
                $id = (string) $resource['id'];
            }

            if (array_key_exists('type', $resource) && is_string($resource['type'])) {
                $type = $resource['type'];
            }

            /** @var array<string, mixed> $attributes */
            $attributes = $resource;
        }

        return new Resource($id, $type, $attributes);
    }

    /**
     * Configure the Patrol package using Spatie's package tools.
     *
     * Defines package name, configuration file, migrations, and console commands.
     */
    #[Override()]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('patrol')
            ->hasConfigFile()
            ->hasMigrations(['create_patrol_delegations_table'])
            ->hasCommands([
                PatrolCheckCommand::class,
                PatrolExplainCommand::class,
                PatrolPoliciesCommand::class,
                PatrolClearCacheCommand::class,
                PatrolDelegationCleanupCommand::class,
                PatrolDelegationListCommand::class,
                PatrolDelegationRevokeCommand::class,
                PatrolMigrateFromSpatieCommand::class,
            ]);
    }

    /**
     * Register Patrol services in the Laravel service container.
     *
     * Binds all rule matcher implementations, the effect resolver, and the policy
     * evaluator as singletons. The default rule matcher is determined by configuration,
     * allowing applications to choose between ACL, RBAC, ABAC, or RESTful authorization
     * models without changing code.
     */
    #[Override()]
    public function register(): void
    {
        parent::register();

        // Register rule matchers for different authorization models
        $this->app->singleton('patrol.matcher.acl', fn (): AclRuleMatcher => new AclRuleMatcher());
        $this->app->singleton('patrol.matcher.rbac', fn (): RbacRuleMatcher => new RbacRuleMatcher());
        $this->app->singleton('patrol.matcher.abac', fn (Application $app): AbacRuleMatcher => new AbacRuleMatcher(
            new AttributeResolver(),
        ));
        $this->app->singleton('patrol.matcher.restful', function (Application $app): RestfulRuleMatcher {
            $fallback = $app->make('patrol.matcher.acl');
            assert($fallback instanceof RuleMatcherInterface);

            return new RestfulRuleMatcher(fallbackMatcher: $fallback);
        });

        // Register default matcher based on config (acl, rbac, abac, or restful)
        $this->app->singleton(function (Application $app): RuleMatcherInterface {
            $matcher = config('patrol.default_matcher', 'acl');
            assert(is_string($matcher));

            $instance = $app->make('patrol.matcher.'.$matcher);
            assert($instance instanceof RuleMatcherInterface);

            return $instance;
        });

        // Register effect resolver for combining Allow/Deny effects
        $this->app->singleton(EffectResolver::class);

        // Register policy evaluator that orchestrates rule matching and effect resolution
        $this->app->singleton(PolicyEvaluator::class, fn (Application $app): PolicyEvaluator => new PolicyEvaluator(
            ruleMatcher: $app->make(RuleMatcherInterface::class),
            effectResolver: $app->make(EffectResolver::class),
        ));

        // Register subject resolver from config
        $this->app->singleton(function (Application $app): SubjectResolverInterface {
            $resolver = config('patrol.subject_resolver');
            assert(is_string($resolver));

            $instance = $app->make($resolver);
            assert($instance instanceof SubjectResolverInterface);

            return $instance;
        });

        // Register delegation components if enabled
        if (config('patrol.delegation.enabled', false) === true) {
            $this->registerDelegationServices();
        }
    }

    /**
     * Bootstrap Patrol services after all providers are registered.
     *
     * Registers custom resolvers from the application's configuration.
     * This allows applications to customize how subjects, tenants, and
     * resources are resolved from the request context.
     */
    #[Override()]
    public function packageBooted(): void
    {
        // Validate database configuration
        $this->validateDatabaseConfiguration();

        // Register Gates integration if enabled
        if (Config::get('patrol.integrate_gates', true) === true) {
            $this->registerGateIntegration();
        }

        // Register middleware aliases
        $this->registerMiddlewareAliases();

        // Register legacy closure-based resolvers from config if provided
        $tenantResolver = config('patrol.tenant_resolver');

        if ($tenantResolver instanceof Closure) {
            Patrol::resolveTenant($tenantResolver);
        }

        $resourceResolver = config('patrol.resource_resolver');

        if ($resourceResolver instanceof Closure) {
            Patrol::resolveResource($resourceResolver);
        }
    }

    /**
     * Validate database configuration settings.
     *
     * Ensures that the primary key type is one of the supported values.
     *
     * @throws InvalidPrimaryKeyTypeException When an invalid primary key type is configured
     */
    private function validateDatabaseConfiguration(): void
    {
        $primaryKeyType = Config::get('patrol.database.primary_key_type', 'uuid');
        assert(is_string($primaryKeyType));

        $validTypes = PrimaryKeyType::values();

        throw_unless(in_array($primaryKeyType, $validTypes, true), InvalidPrimaryKeyTypeException::create($primaryKeyType, $validTypes));
    }

    /**
     * Convert a user model to a Patrol Subject.
     *
     * @param  mixed        $user The authenticated user
     * @return null|Subject The subject value object or null if conversion fails
     */
    private function userToSubject(mixed $user): ?Subject
    {
        if (!is_object($user)) {
            return null;
        }

        $id = 'anonymous';

        if (property_exists($user, 'id')) {
            $idValue = $user->id;

            if (is_string($idValue) || is_int($idValue)) {
                $id = (string) $idValue;
            }
        }

        $attributes = [];

        if (method_exists($user, 'toArray')) {
            $result = $user->toArray();

            if (is_array($result)) {
                /** @var array<string, mixed> $attributes */
                $attributes = $result;
            }
        }

        return new Subject($id, $attributes);
    }

    /**
     * Extract a Resource from gate arguments.
     *
     * @param  array<int, mixed> $arguments Gate arguments (typically model instances)
     * @return resource          The resource value object
     */
    private function extractResourceFromArguments(array $arguments): Resource
    {
        if ($arguments === []) {
            return new Resource('*', 'unknown');
        }

        $firstArg = $arguments[0];

        if ($firstArg instanceof Resource) {
            return $firstArg;
        }

        if (is_object($firstArg)) {
            $id = 'unknown';
            $type = class_basename($firstArg);
            $attributes = [];

            if (property_exists($firstArg, 'id')) {
                $idValue = $firstArg->id;

                if (is_string($idValue) || is_int($idValue)) {
                    $id = (string) $idValue;
                }
            }

            if (method_exists($firstArg, 'toArray')) {
                $result = $firstArg->toArray();

                if (is_array($result)) {
                    /** @var array<string, mixed> $attributes */
                    $attributes = $result;
                }
            }

            return new Resource($id, $type, $attributes);
        }

        if (is_string($firstArg)) {
            return new Resource($firstArg, 'unknown');
        }

        return new Resource('*', 'unknown');
    }

    /**
     * Register integration with Laravel's Gate system.
     *
     * Adds a Gate::before callback that checks Patrol policies before
     * Laravel's native authorization gates. This allows Patrol to work
     * alongside existing Laravel authorization code.
     */
    private function registerGateIntegration(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []): bool {
            // Convert user to Subject
            $subject = $this->userToSubject($user);

            if (!$subject instanceof Subject) {
                return false; // Let other gates handle it
            }

            // Determine resource from arguments
            /** @var array<int, mixed> $arguments */
            $resource = $this->extractResourceFromArguments($arguments);

            // Create Action from ability name
            $action = new Action($ability);

            // Load and evaluate policies
            $repository = $this->app->make(PolicyRepositoryInterface::class);
            $evaluator = $this->app->make(PolicyEvaluator::class);

            $policy = $repository->getPoliciesFor($subject, $resource);
            $result = $evaluator->evaluate($policy, $subject, $resource, $action);

            // Return true for Allow, false for Deny, null to continue to other gates
            return match ($result) {
                Effect::Allow => true,
                Effect::Deny => false,
            };
        });
    }

    /**
     * Register middleware aliases for easier route protection.
     *
     * Registers 'patrol' and 'can' aliases for the PatrolMiddleware.
     */
    private function registerMiddlewareAliases(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('patrol', PatrolMiddleware::class);
    }

    /**
     * Register delegation-related services.
     *
     * Sets up delegation repository, manager, validator, and wraps the policy
     * evaluator with delegation awareness when delegation features are enabled.
     */
    private function registerDelegationServices(): void
    {
        // Register delegation repository based on configured driver
        $this->app->singleton(function (Application $app): DelegationRepositoryInterface {
            $driver = config('patrol.delegation.driver', 'database');

            $base = new DatabaseDelegationRepository();

            $cacheTtl = config('patrol.delegation.cache_ttl', 3_600);
            assert(is_int($cacheTtl));

            return match ($driver) {
                'cached' => new CachedDelegationRepository(
                    repository: $base,
                    cache: $app->make(Factory::class)->store(),
                    ttl: $cacheTtl,
                ),
                default => $base,
            };
        });

        // Register delegation validator
        $this->app->singleton(function (Application $app): DelegationValidator {
            $maxDurationDays = config('patrol.delegation.max_duration_days');
            assert($maxDurationDays === null || is_int($maxDurationDays));

            return new DelegationValidator(
                policyRepository: $app->make(PolicyRepositoryInterface::class),
                policyEvaluator: new PolicyEvaluator(
                    ruleMatcher: $app->make(RuleMatcherInterface::class),
                    effectResolver: $app->make(EffectResolver::class),
                ),
                delegationRepository: $app->make(DelegationRepositoryInterface::class),
                maxDurationDays: $maxDurationDays,
            );
        });

        // Register delegation manager
        $this->app->singleton(fn (Application $app): DelegationManager => new DelegationManager(
            repository: $app->make(DelegationRepositoryInterface::class),
            validator: $app->make(DelegationValidator::class),
        ));

        // Wrap PolicyEvaluator with delegation awareness
        $this->app->extend(PolicyEvaluator::class, fn (PolicyEvaluator $evaluator, Application $app): DelegationAwarePolicyEvaluator => new DelegationAwarePolicyEvaluator(
            evaluator: $evaluator,
            delegationManager: $app->make(DelegationManager::class),
        ));
    }
}
