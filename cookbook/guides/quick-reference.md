# Patrol Cookbook - Quick Reference

## Choose Your Model

### By Application Size
- **< 10 users** → [ACL](./acl.md)
- **10-100 users** → [RBAC](./rbac.md)
- **100+ users** → [RBAC](./rbac.md) + [ABAC](./abac.md)

### By Architecture
- **Multi-tenant SaaS** → [RBAC with Domains](./rbac-domains.md)
- **Public API** → [RESTful](./restful.md) or [ACL without Users](./acl-without-users.md)
- **Microservices** → [RESTful](./restful.md)
- **Enterprise** → [RBAC](./rbac.md) + [Deny-Override](./deny-override.md)

### By Permission Type
- **Direct permissions** → [ACL](./acl.md)
- **Role-based** → [RBAC](./rbac.md)
- **Feature flags** → [ACL without Resources](./acl-without-resources.md)
- **Ownership** → [ABAC](./abac.md)
- **Time-based** → [ABAC](./abac.md)
- **Security levels** → [RBAC with Resource Roles](./rbac-resource-roles.md)

### By Security Needs
- **Admin bypass** → [ACL with Superuser](./acl-superuser.md)
- **Explicit denials** → [Deny-Override](./deny-override.md)
- **Rule ordering** → [Priority-Based](./priority-based.md)
- **Compliance** → [Deny-Override](./deny-override.md)

## Common Patterns

### Basic Authorization
```php
new PolicyRule('user-1', 'document-1', 'read', Effect::Allow)
```

### Role-Based
```php
new PolicyRule('role:editor', 'document:*', 'edit', Effect::Allow)
```

### Ownership
```php
new PolicyRule('resource.owner_id == subject.id', 'document:*', '*', Effect::Allow)
```

### Multi-Tenant
```php
new PolicyRule('admin', '*', '*', Effect::Allow, domain: 'tenant-1')
```

### HTTP Authorization
```php
new PolicyRule('user-1', '/api/documents/*', 'GET', Effect::Allow)
```

## Laravel Integration

### Subject Resolver
```php
Patrol::resolveSubject(fn() => new Subject(auth()->id()));
```

### Middleware
```php
Route::middleware(['patrol:document:read'])->get('/documents', ...);
```

### Controller Check
```php
if (!Patrol::check($document, 'edit')) abort(403);
```

### Batch Filter
```php
$authorized = Patrol::filter($documents, 'read');
```

## Testing Pattern
```php
it('allows authorized access', function () {
    $policy = new Policy([/* rules */]);
    $evaluator = new PolicyEvaluator(new AclRuleMatcher(), new EffectResolver());
    
    $result = $evaluator->evaluate($policy, $subject, $resource, $action);
    
    expect($result)->toBe(Effect::Allow);
});
```

## Need More Detail?

📖 See [Full Cookbook](./README.md) for comprehensive guides with complete examples, patterns, and best practices.
