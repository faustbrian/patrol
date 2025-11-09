# Patrol - Laravel Access Control Package Implementation Plan

## Architecture Overview

### Core Layer (Framework-Agnostic)
Pure PHP value objects and services with zero Laravel dependencies.

#### Value Objects (`src/Core/ValueObjects/`)
```
- Subject (user/actor identity)
- Resource (target of access check)
- Action (operation being performed)
- Effect (Allow/Deny)
- Priority (rule ordering)
- Domain/Tenant (multi-tenancy support)
- Permission, Role, Attribute
- Policy (collection of rules)
- PolicyRule (single authorization rule)
```

#### Policy Engine (`src/Core/Engine/`)
```
- PolicyEvaluator (evaluates rules against request)
- RuleMatcherInterface (strategy for different rule types)
  - AclRuleMatcher
  - RbacRuleMatcher
  - AbacRuleMatcher
  - RestfulRuleMatcher
- EffectResolver (handles allow/deny/priority logic)
- AttributeResolver (evaluates ABAC expressions like resource.Owner)
```

#### Storage Contracts (`src/Core/Contracts/`)
```
- PolicyRepositoryInterface
- SubjectResolverInterface
- ResourceResolverInterface
- AttributeProviderInterface
```

### Laravel Integration Layer (`src/Laravel/`)

#### Resolvers (`src/Laravel/Resolvers/`)
```php
Patrol::resolveSubject(fn() => auth()->user())
Patrol::resolveTenant(fn() => auth()->user()->currentTenant)
Patrol::resolveResource(fn($id) => Model::find($id))
```

#### Middleware (`src/Laravel/Middleware/`)
```
- PatrolMiddleware (main authorization check)
- ResolveTenantMiddleware (sets tenant context)
```

#### Service Provider
Binds Laravel implementations to core contracts, registers middleware, publishes config.

## Supported Authorization Models

1. **ACL (Access Control List)**
   - ACL with superuser support
   - ACL without users (permission-only systems)
   - ACL without resources (type-based permissions like `write-article`, `read-log`)

2. **RBAC (Role-Based Access Control)**
   - RBAC with resource roles (both users and resources can have roles)
   - RBAC with domains/tenants (users have different role sets per domain)

3. **ABAC (Attribute-Based Access Control)**
   - Syntax sugar like `resource.Owner` for attribute access
   - Dynamic attribute evaluation

4. **RESTful**
   - Path matching (`/res/*`, `/res/:id`)
   - HTTP method support (GET, POST, PUT, DELETE)

5. **Advanced Features**
   - Deny-override: deny takes precedence over allow
   - Priority: firewall-style rule ordering

## Implementation Phases

### Phase 1: Core Foundation
1. Value objects (Subject, Resource, Action, Effect, Priority)
2. Policy and PolicyRule structures
3. Basic policy storage contract
4. **Testing:** Pure PHP unit tests with fixtures

### Phase 2: ACL Implementation
1. AclRuleMatcher with superuser support
2. User-less ACL mode (permission-only checks)
3. Resource-less ACL mode (type-based permissions)
4. **Testing:** ACL-specific rule matching tests

### Phase 3: RBAC Implementation
1. Role value objects
2. RbacRuleMatcher with resource roles
3. Domain/tenant-scoped role evaluation
4. **Testing:** RBAC scenarios with multi-tenancy

### Phase 4: ABAC & Advanced Features
1. AttributeResolver with expression parsing
2. AbacRuleMatcher (`resource.Owner` syntax)
3. RESTful path/method matching
4. Deny-override + priority logic in EffectResolver
5. **Testing:** ABAC attribute resolution, RESTful matching, priority/deny tests

### Phase 5: Laravel Integration
1. Service provider and config
2. Resolver bindings (Subject, Tenant, Resource)
3. Middleware implementation
4. Eloquent policy repository
5. **Testing:** Laravel integration tests with TestCase fixtures

### Phase 6: Testing & Polish
1. Comprehensive test coverage across all components
2. Documentation and usage examples
3. Performance benchmarks
4. Migration guides for common patterns

## Key Design Decisions

### Resolver Pattern
All framework coupling goes through closures registered via `Patrol::resolve*()`:
```php
// In application bootstrap
Patrol::resolveSubject(fn() => auth()->user());
Patrol::resolveTenant(fn() => auth()->user()->currentTenant);

// In tests
Patrol::resolveSubject(fn() => new SubjectFixture(['id' => 1]));
Patrol::resolveTenant(fn() => new TenantFixture(['id' => 'tenant-1']));
```

### Strategy Pattern
Different rule matchers implement `RuleMatcherInterface`, allowing new authorization models without changing the engine:
```php
interface RuleMatcherInterface
{
    public function matches(PolicyRule $rule, Subject $subject, Resource $resource, Action $action): bool;
}
```

### Effect Resolution
Explicit deny-override logic with priority ordering in `EffectResolver`:
1. Rules sorted by priority (highest first)
2. First explicit DENY wins immediately
3. First explicit ALLOW wins if no DENY found
4. Default DENY if no matches

### Immutable Value Objects
All value objects are immutable DTOs for predictable behavior and easy testing:
```php
final readonly class Subject
{
    public function __construct(
        public string $id,
        public array $attributes = [],
    ) {}
}
```

## Testing Strategy (Pest v4)

### Core Tests (Framework-Agnostic)
- Pure PHP tests using fixtures
- No Laravel dependencies
- Focus on business logic and rule matching

### Laravel Integration Tests
- Use Pest's Laravel plugin
- Test resolver bindings
- Test middleware behavior
- Test service provider registration

### Test Categories
- **Happy Path:** Expected authorization flows
- **Sad Path:** Denied access scenarios
- **Edge Cases:** Priority conflicts, attribute resolution edge cases
- **Regression:** Known bug fixes

## Directory Structure
```
patrol/
├── src/
│   ├── Core/
│   │   ├── ValueObjects/
│   │   ├── Engine/
│   │   └── Contracts/
│   └── Laravel/
│       ├── Resolvers/
│       ├── Middleware/
│       └── PatrolServiceProvider.php
├── tests/
│   ├── Core/          # Framework-agnostic tests
│   └── Laravel/       # Laravel integration tests
├── config/
│   └── patrol.php
└── PLAN.md
```
