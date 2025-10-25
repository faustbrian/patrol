# Patrol Cookbook - Quick Reference

## Choose Your Model

### By Application Size
- **< 10 users** → [ACL](./ACL.md)
- **10-100 users** → [RBAC](./RBAC.md)
- **100+ users** → [RBAC](./RBAC.md) + [ABAC](./ABAC.md)

### By Architecture
- **Multi-tenant SaaS** → [RBAC with Domains](./RBAC-Domains.md)
- **Public API** → [RESTful](./RESTful.md) or [ACL without Users](./ACL-Without-Users.md)
- **Microservices** → [RESTful](./RESTful.md)
- **Enterprise** → [RBAC](./RBAC.md) + [Deny-Override](./Deny-Override.md)

### By Permission Type
- **Direct permissions** → [ACL](./ACL.md)
- **Role-based** → [RBAC](./RBAC.md)
- **Feature flags** → [ACL without Resources](./ACL-Without-Resources.md)
- **Ownership** → [ABAC](./ABAC.md)
- **Time-based** → [ABAC](./ABAC.md)
- **Security levels** → [RBAC with Resource Roles](./RBAC-Resource-Roles.md)

### By Security Needs
- **Admin bypass** → [ACL with Superuser](./ACL-Superuser.md)
- **Explicit denials** → [Deny-Override](./Deny-Override.md)
- **Rule ordering** → [Priority-Based](./Priority-Based.md)
- **Compliance** → [Deny-Override](./Deny-Override.md)

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
