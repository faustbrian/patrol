# Migration to Laravel Container Attributes

**Goal:** Replace manual `$this->app->singleton()` bindings in `PatrolServiceProvider` with Laravel container attributes.

## Current Manual Bindings

### DatabaseDelegationRepository
```php
$this->app->singleton(DelegationRepositoryInterface::class, function (Application $app): DelegationRepositoryInterface {
    $table = config('patrol.delegation.table', 'delegations');
    $connection = config('patrol.delegation.connection');

    return new DatabaseDelegationRepository($table, $connection);
});
```

**Issue:** Constructor has default parameters but needs config injection.

**Solution:**
- Add factory method to repository that reads config internally
- OR: Keep manual binding since config needs runtime resolution

### DelegationValidator
```php
$this->app->singleton(DelegationValidator::class, function (Application $app): DelegationValidator {
    return new DelegationValidator(
        $app->make(PolicyRepositoryInterface::class),
        $app->make(PolicyEvaluator::class),
        $app->make(DelegationRepositoryInterface::class),
    );
});
```

**Can use:** `#[Singleton]` attribute on class - all dependencies are interface contracts that Laravel auto-resolves.

### DelegationManager
```php
$this->app->singleton(DelegationManager::class, function (Application $app): DelegationManager {
    return new DelegationManager(
        $app->make(DelegationRepositoryInterface::class),
        $app->make(DelegationValidator::class),
    );
});
```

**Can use:** `#[Singleton]` attribute on class - all dependencies are auto-resolvable.

## Migration Steps

1. **Add Singleton attributes to:**
   - `DelegationValidator` class
   - `DelegationManager` class

2. **For DatabaseDelegationRepository:**
   - Option A: Create static factory method that reads config
   - Option B: Keep manual binding (config resolution requires runtime context)
   - **Recommended:** Option B - config values aren't available at class definition time

3. **Remove manual bindings** from `PatrolServiceProvider::registerDelegationServices()`

4. **Test** that container resolution still works with attributes

## Expected Outcome

**Before:**
```php
private function registerDelegationServices(): void
{
    $this->app->singleton(DelegationRepositoryInterface::class, fn() => ...);
    $this->app->singleton(DelegationValidator::class, fn() => ...);
    $this->app->singleton(DelegationManager::class, fn() => ...);
    $this->app->extend(PolicyEvaluator::class, fn() => ...);
}
```

**After:**
```php
private function registerDelegationServices(): void
{
    // Only repository needs manual binding for config injection
    $this->app->singleton(DelegationRepositoryInterface::class, function (Application $app): DelegationRepositoryInterface {
        return new DatabaseDelegationRepository(
            config('patrol.delegation.table', 'delegations'),
            config('patrol.delegation.connection')
        );
    });

    // DelegationValidator and DelegationManager auto-registered via #[Singleton]

    $this->app->extend(PolicyEvaluator::class, fn() => ...);
}
```

## Questions

1. Can we use a config-aware factory pattern to eliminate the repository binding?
2. Are there other services in the codebase using attributes we should follow?
3. Should we apply this pattern retroactively to existing services (PolicyEvaluator, matchers, etc.)?
