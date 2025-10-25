# Patrol Authorization Cookbook

A comprehensive guide to authorization patterns and models using Patrol.

## 🚀 Quick Start - Pick Your Path

```
┌─────────────────────────────────────────────────────────────┐
│  🎓 New to Patrol? Start here:                              │
│     → Beginner's Guide (./guides/GETTING-STARTED.md)        │
│       Learn ACL → RBAC → ABAC in 15 minutes                 │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  🎯 Know what you need? Choose by:                          │
│     • Use Case (#by-use-case) - SaaS, API, Enterprise...    │
│     • Feature (#by-feature) - Ownership, Time, Location...  │
│     • Decision Tree (#pattern-decision-tree)                │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  ⚡ Advanced? Jump to:                                       │
│     • Combining Models (#combining-models)                  │
│     • Guides (./guides/)                                    │
│     • Migration Path (#migration-path)                      │
└─────────────────────────────────────────────────────────────┘
```

**[Quick Reference →](./guides/QUICK-REFERENCE.md)** | **[Interactive Chooser →](./guides/CHOOSER.md)** | **[Beginner's Path →](./guides/GETTING-STARTED.md)**

---

## Introduction

This cookbook provides practical examples and patterns for implementing various authorization models with Patrol. Each recipe includes complete working examples, Laravel integration, testing strategies, and best practices.

Whether you're building a simple app or a complex multi-tenant system, you'll find patterns and examples to guide your implementation.

---

## Model Comparison

Choose the right model for your needs:

| Model | Complexity | Best For | When to Use | Example |
|-------|-----------|----------|-------------|---------|
| [ACL](./models/ACL.md) | ⭐ Simple | Small apps | < 10 users, direct permissions | `user-1 → doc-1 → read` |
| [ACL + Superuser](./models/ACL-Superuser.md) | ⭐ Simple | Small apps with admin | Admin bypass needed | `admin → * → *` |
| [RBAC](./models/RBAC.md) | ⭐⭐ Medium | Teams/Enterprise | Job functions, 10+ users | `editor → posts → edit` |
| [RBAC + Domains](./models/RBAC-Domains.md) | ⭐⭐⭐ Complex | Multi-tenant SaaS | Different roles per tenant | `tenant-1::admin` |
| [RBAC + Resource Roles](./models/RBAC-Resource-Roles.md) | ⭐⭐⭐ Complex | Clearance systems | Users AND resources have roles | `clearance-3 → classified-2` |
| [ABAC](./models/ABAC.md) | ⭐⭐⭐ Complex | Dynamic permissions | Ownership, time, conditions | `owner_id == user_id` |
| [RESTful](./models/RESTful.md) | ⭐⭐ Medium | APIs/Microservices | HTTP authorization | `GET /api/posts → allow` |
| [Deny-Override](./models/Deny-Override.md) | ⭐⭐ Medium | Security-critical | Explicit denials needed | `deny > allow` (always) |
| [Priority-Based](./models/Priority-Based.md) | ⭐⭐⭐ Complex | Firewall-style | Rule ordering matters | First match wins |
| [ACL without Users](./models/ACL-Without-Users.md) | ⭐ Simple | Public APIs | No authentication | `* → public-posts → read` |
| [ACL without Resources](./models/ACL-Without-Resources.md) | ⭐⭐ Medium | Feature flags | SaaS tiers, capabilities | `premium → export-pdf` |

**💡 Tip:** Most real-world apps combine multiple models (e.g., RBAC + ABAC + Deny-Override).

---

## Table of Contents

### Basic Models

#### [ACL (Access Control List)](./ACL.md)
Direct subject-resource-action permissions for explicit authorization. The simplest model for straightforward permission systems.

**Use when:** You have a small application with direct user-to-resource relationships.

---

#### [ACL with Superuser](./ACL-Superuser.md)
Special users with unrestricted access using wildcard permissions. Perfect for admin accounts.

**Use when:** You need system administrators who bypass normal permission checks.

---

#### [ACL without Users](./ACL-Without-Users.md)
Authorization for systems without authentication. Public APIs and anonymous access.

**Use when:** Building public APIs, documentation sites, or systems without user accounts.

---

#### [ACL without Resources](./ACL-Without-Resources.md)
Permission types instead of specific resources. Capability-based authorization (write-article, export-pdf).

**Use when:** Implementing feature flags, SaaS tiers, or system-wide capabilities.

---

### Role-Based Models

#### [RBAC (Role-Based Access Control)](./RBAC.md)
Assign permissions to roles, then assign roles to users. The most common enterprise authorization model.

**Use when:** You have clear job functions and 10+ users with similar permission patterns.

---

#### [RBAC with Resource Roles](./RBAC-Resource-Roles.md)
Both users and resources have roles. Sophisticated role interactions (user clearance level + document classification).

**Use when:** Implementing security clearances, subscription tiers, or document classification systems.

---

#### [RBAC with Domains](./RBAC-Domains.md)
Multi-tenant role sets where users have different roles in different organizations/workspaces.

**Use when:** Building multi-tenant SaaS, workspace-based apps, or multi-organization systems.

---

### Advanced Models

#### [ABAC (Attribute-Based Access Control)](./ABAC.md)
Dynamic authorization using attribute expressions. Evaluate conditions at runtime (ownership, time, location).

**Use when:** You need dynamic permissions based on attributes, relationships, or context.

---

#### [RESTful Authorization](./RESTful.md)
HTTP method and path-based authorization for API endpoints (GET /api/users → allow/deny).

**Use when:** Building REST APIs, microservices, or HTTP-based services.

---

### Security Patterns

#### [Deny-Override](./Deny-Override.md)
Explicit deny rules that override all allow rules. Security-critical access control.

**Use when:** You need security overrides, compliance requirements, or emergency lockdowns.

---

#### [Priority-Based](./Priority-Based.md)
Firewall-style rule ordering where rules are evaluated by priority, first match wins.

**Use when:** You need explicit control over rule evaluation order or complex override scenarios.

---

### Delegation Patterns

#### [Delegation (Current Implementation)](./Delegation.md)
Implement permission delegation using existing Patrol primitives. Allows users to temporarily grant their permissions to others.

**Use when:** You need vacation coverage, task handoff, temporary assistance, or collaborative access patterns.

**Note:** Native delegation support is planned (see [DELEGATION.md](../DELEGATION.md)). This guide shows how to implement it today.

---

### Guides & Tools

#### [Policy Builders](./guides/POLICY-BUILDERS.md)
Fluent API for building authorization policies without manual PolicyRule construction. Includes RbacPolicyBuilder, RestfulPolicyBuilder, CrudPolicyBuilder, and AclPolicyBuilder with complete examples and best practices.

**Use when:** You want a clean, expressive way to construct policies programmatically.

---

#### [CLI Tools](./guides/CLI-TOOLS.md)
Command-line utilities for testing, debugging, and managing Patrol policies. Includes `patrol:check`, `patrol:explain`, `patrol:list-rules`, `patrol:test-policy`, and more.

**Use when:** Debugging authorization issues, testing policies, CI/CD integration, or generating documentation.

---

#### [Persisting Policies](./guides/PERSISTING-POLICIES.md)
Learn how to save policy builder results to different storage backends (database, JSON, YAML, XML, TOML).

**Use when:** You need to persist built policies to storage.

---

### Patterns & Use Cases

#### [Feature Flags & Progressive Rollouts](./patterns/FEATURE-FLAGS.md)
Use Patrol for feature flags, beta access, A/B testing, and progressive rollouts. Authorization-first feature management with percentage rollouts, attribute-based access, and tier-based features.

**Use when:** Managing feature rollouts, SaaS tiers, beta programs, or A/B experiments.

---

#### [Getting Started](./guides/GETTING-STARTED.md)
Beginner's guide to Patrol with ACL → RBAC → ABAC learning path.

**Use when:** You're new to Patrol and want a guided introduction.

---

#### [Quick Reference](./guides/QUICK-REFERENCE.md)
Cheat sheet for choosing the right authorization model in 2 minutes.

**Use when:** You need a quick decision guide.

---

#### [API Reference](./guides/API-REFERENCE.md)
Complete reference for Patrol's core value objects, policy engine, and Laravel integration. Includes PolicyRule, Subject, Resource, Action, Effect, Priority, PolicyEvaluator, and all Laravel facade methods.

**Use when:** You need detailed API documentation for core components.

---

#### [Configuration](./guides/CONFIGURATION.md)
Complete guide to configuring Patrol for your Laravel application. Includes matcher selection, subject/tenant/resource resolvers, policy repositories, caching, and environment-specific configuration with real-world examples.

**Use when:** Setting up Patrol, customizing resolvers, or optimizing performance.

---

## Quick Navigation

### By Use Case

**Small Applications (< 10 users)**
- [ACL](./ACL.md)
- [ACL with Superuser](./ACL-Superuser.md)

**Enterprise Applications**
- [RBAC](./RBAC.md)
- [RBAC with Domains](./RBAC-Domains.md)
- [Deny-Override](./Deny-Override.md)

**Multi-Tenant SaaS**
- [RBAC with Domains](./RBAC-Domains.md)
- [ACL without Resources](./ACL-Without-Resources.md) (for feature tiers)

**Public APIs**
- [RESTful](./RESTful.md)
- [ACL without Users](./ACL-Without-Users.md)

**Complex Dynamic Permissions**
- [ABAC](./ABAC.md)
- [Priority-Based](./Priority-Based.md)

**Security-Critical Systems**
- [Deny-Override](./Deny-Override.md)
- [Priority-Based](./Priority-Based.md)
- [RBAC with Resource Roles](./RBAC-Resource-Roles.md)

**Delegation/Temporary Access**
- [Delegation](./Delegation.md)

### By Feature

**Ownership-Based**
- [ABAC](./ABAC.md) - resource.owner_id == subject.id

**Time-Based**
- [ABAC](./ABAC.md) - Business hours restrictions
- [Priority-Based](./Priority-Based.md) - Time-based priority rules

**Location-Based**
- [ABAC](./ABAC.md) - IP restrictions, geo-fencing
- [RESTful](./RESTful.md) - API endpoint access by location

**Hierarchy/Clearance**
- [RBAC with Resource Roles](./RBAC-Resource-Roles.md)
- [Priority-Based](./Priority-Based.md)

**Feature Flags/Tiers**
- [ACL without Resources](./ACL-Without-Resources.md)
- [RBAC with Resource Roles](./RBAC-Resource-Roles.md)

**Delegation/Temporary Permissions**
- [Delegation](./Delegation.md) - Vacation coverage, task handoff

## How to Use This Cookbook

### 1. Choose Your Model

Start by understanding your requirements:

- **How many users?** Small apps → ACL, Large apps → RBAC
- **Multi-tenant?** → RBAC with Domains
- **Dynamic permissions?** → ABAC
- **API authorization?** → RESTful
- **Security-critical?** → Deny-Override

### 2. Read the Recipe

Each cookbook entry follows the same structure:

1. **Overview** - What the model does
2. **Basic Concept** - Core idea in simple terms
3. **Use Cases** - When to use this model
4. **Core Example** - Working code example
5. **Patterns** - Common patterns (2-3 examples)
6. **Laravel Integration** - Subject/Resource resolvers, middleware, controllers
7. **Real-World Example** - Complete realistic scenario
8. **Database Storage** - Migrations and repository implementation
9. **Testing** - Pest test examples
10. **Best Practices** - Numbered list of recommendations
11. **When to Use** - ✅ Good for / ❌ Avoid for
12. **Related Models** - Links to related patterns

### 3. Implement

Copy the examples and adapt them to your needs:

```php
// Start with the core example
$policy = new Policy([
    new PolicyRule('user-1', 'article:*', 'read', Effect::Allow),
]);

// Add Laravel integration
Patrol::resolveSubject(function () {
    return new Subject(auth()->id());
});

// Add tests
it('allows user to read articles', function () {
    // ... test code from cookbook
});
```

### 4. Combine Patterns

You can combine multiple patterns:

```php
// RBAC + ABAC + Deny-Override
$policy = new Policy([
    // Role-based
    new PolicyRule('role:editor', 'article:*', 'edit', Effect::Allow),

    // Ownership-based (ABAC)
    new ConditionalPolicyRule(
        condition: 'resource.author_id == subject.id',
        resource: 'article:*',
        action: '*',
        effect: Effect::Allow
    ),

    // Deny override for suspended users
    new PolicyRule('status:suspended', '*', '*', Effect::Deny),
]);
```

## Common Patterns

### Combining Models

Most real-world applications combine multiple models:

#### SaaS Application
```php
// RBAC (organization roles) + ABAC (ownership) + ACL without Resources (features)
new PolicyRule('role:admin', 'organization:*', '*', Effect::Allow), // RBAC
new ConditionalPolicyRule('resource.owner_id == subject.id', 'project:*', '*', Effect::Allow), // ABAC
new PolicyRule('subscription:premium', '*', 'export-pdf', Effect::Allow), // Feature-based
```

#### Enterprise System
```php
// RBAC with Domains + Deny-Override + Priority
new PolicyRule('role:manager', 'team:*', 'manage', Effect::Allow, domain: 'department-1'), // Domain RBAC
new PolicyRule('status:suspended', '*', '*', Effect::Deny, priority: 900), // Deny override
new PolicyRule('clearance:secret', 'classified:*', 'read', Effect::Allow, priority: 500), // Priority
```

### Migration Path

Start simple, add complexity as needed:

1. **Start:** Basic ACL
2. **Growing:** Add RBAC when you have repeated permission patterns
3. **Multi-tenant:** Add Domains when you need organization isolation
4. **Dynamic:** Add ABAC for ownership and complex logic
5. **Security:** Add Deny-Override for critical restrictions

## Examples by Framework Integration

### Laravel

Every recipe includes:
- Subject/Resource resolvers
- Middleware examples
- Controller examples
- Blade directive usage (where applicable)

### Testing

Every recipe includes 3 Pest test examples:
- Happy path (allow)
- Sad path (deny)
- Edge case (pattern-specific)

### Database

Every recipe includes:
- Migration examples
- Repository implementation
- Model relationships (where applicable)

## Contributing

Found a pattern not covered here? Have a better example? Contributions are welcome!

## Quick Reference

### Core Components

- **Subject** - The user/entity requesting access
- **Resource** - The thing being accessed
- **Action** - What they want to do
- **Effect** - Allow or Deny
- **Policy** - Collection of rules
- **PolicyRule** - Basic rule (subject + resource + action → effect)
- **ConditionalPolicyRule** - ABAC rule with conditions
- **Priority** - For priority-based evaluation
- **Domain** - For multi-tenant scenarios

### Effect Resolvers

- **EffectResolver** - Standard (all allows required)
- **DenyOverrideEffectResolver** - Deny wins
- **PriorityEffectResolver** - First match wins

### Rule Matchers

- **AclRuleMatcher** - Basic ACL matching
- **RbacRuleMatcher** - Role-based matching
- **AbacRuleMatcher** - Attribute/condition evaluation
- **RestfulRuleMatcher** - HTTP path/method matching
- **PriorityRuleMatcher** - Priority-ordered matching

## Getting Help

1. **Check the cookbook** - Most patterns are covered
2. **Read the pattern's "Related Models"** - Find similar patterns
3. **Review tests** - Each recipe has test examples
4. **Combine patterns** - Mix and match as needed

## Pattern Decision Tree

```
Do you have user accounts?
├─ No → ACL without Users, RESTful (for APIs)
└─ Yes
   └─ Do you have < 10 users?
      ├─ Yes → ACL, ACL with Superuser
      └─ No
         └─ Do you need multi-tenant?
            ├─ Yes → RBAC with Domains
            └─ No
               └─ Do you need dynamic permissions?
                  ├─ Yes → ABAC
                  └─ No → RBAC
```

## Next Steps

1. Choose a pattern from the table of contents above
2. Read the full recipe
3. Implement the examples
4. Add tests
5. Combine with other patterns as needed

Happy authorizing! 🔐
