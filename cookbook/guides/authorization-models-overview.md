# Authorization Models Overview

Patrol supports 11+ authorization models. Pick one or combine multiple.

## All 11 Models

### Basic Models
- ✅ **[ACL (Access Control List)](../models/acl.md)** - Direct user/resource permissions
- ✅ **[ACL with Superuser](../models/acl-superuser.md)** - Special users with all permissions
- ✅ **[ACL without Users](../models/acl-without-users.md)** - Systems without authentication
- ✅ **[ACL without Resources](../models/acl-without-resources.md)** - Permission types instead of specific resources

### Role-Based Models
- ✅ **[RBAC (Role-Based Access Control)](../models/rbac.md)** - Role-based permissions
- ✅ **[RBAC with Resource Roles](../models/rbac-resource-roles.md)** - Both users and resources have roles
- ✅ **[RBAC with Domains/Tenants)](../models/rbac-domains.md)** - Multi-tenant role sets

### Advanced Models
- ✅ **[ABAC (Attribute-Based Access Control)](../models/abac.md)** - Attribute-based rules
- ✅ **[RESTful](../models/restful.md)** - HTTP path/method authorization

### Security Patterns
- ✅ **[Deny-Override](../models/deny-override.md)** - Explicit deny overrides allow
- ✅ **[Priority-Based](../models/priority-based.md)** - Firewall-style rule ordering

## Quick Examples

### ACL (Access Control List)
Direct permissions:

```php
new PolicyRule('user-1', 'document-1', 'read', Effect::Allow);
```

[Full ACL Guide →](../models/acl.md)

### RBAC (Role-Based)
Permission through roles:

```php
new PolicyRule('role:editor', 'document:*', 'edit', Effect::Allow);
```

[Full RBAC Guide →](../models/rbac.md)

### ABAC (Attribute-Based)
Dynamic conditions:

```php
new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow);
```

[Full ABAC Guide →](../models/abac.md)

### RESTful
HTTP authorization:

```php
new PolicyRule('user-1', '/api/documents/:id', 'GET', Effect::Allow);
```

[Full RESTful Guide →](../models/restful.md)

## Choose by Use Case

- **Small app (< 10 users)** → [ACL](../models/acl.md)
- **Enterprise/Teams** → [RBAC](../models/rbac.md)
- **Multi-tenant SaaS** → [RBAC with Domains](../models/rbac-domains.md)
- **Dynamic ownership** → [ABAC](../models/abac.md)
- **API authorization** → [RESTful](../models/restful.md)
- **Security-critical** → [Deny-Override](../models/deny-override.md)

## See Also

- **[Quick Reference](quick-reference.md)** - Choose your model in 2 minutes
- **[Complete Cookbook](../README.md)** - Comprehensive guides for all models
- **[Beginner's Path](getting-started.md)** - ACL → RBAC → ABAC learning path
