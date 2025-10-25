# Authorization Models Overview

Patrol supports 11+ authorization models. Pick one or combine multiple.

## All 11 Models

### Basic Models
- ✅ **[ACL (Access Control List)](../models/ACL.md)** - Direct user/resource permissions
- ✅ **[ACL with Superuser](../models/ACL-Superuser.md)** - Special users with all permissions
- ✅ **[ACL without Users](../models/ACL-Without-Users.md)** - Systems without authentication
- ✅ **[ACL without Resources](../models/ACL-Without-Resources.md)** - Permission types instead of specific resources

### Role-Based Models
- ✅ **[RBAC (Role-Based Access Control)](../models/RBAC.md)** - Role-based permissions
- ✅ **[RBAC with Resource Roles](../models/RBAC-Resource-Roles.md)** - Both users and resources have roles
- ✅ **[RBAC with Domains/Tenants)](../models/RBAC-Domains.md)** - Multi-tenant role sets

### Advanced Models
- ✅ **[ABAC (Attribute-Based Access Control)](../models/ABAC.md)** - Attribute-based rules
- ✅ **[RESTful](../models/RESTful.md)** - HTTP path/method authorization

### Security Patterns
- ✅ **[Deny-Override](../models/Deny-Override.md)** - Explicit deny overrides allow
- ✅ **[Priority-Based](../models/Priority-Based.md)** - Firewall-style rule ordering

## Quick Examples

### ACL (Access Control List)
Direct permissions:

```php
new PolicyRule('user-1', 'document-1', 'read', Effect::Allow);
```

[Full ACL Guide →](../models/ACL.md)

### RBAC (Role-Based)
Permission through roles:

```php
new PolicyRule('role:editor', 'document:*', 'edit', Effect::Allow);
```

[Full RBAC Guide →](../models/RBAC.md)

### ABAC (Attribute-Based)
Dynamic conditions:

```php
new PolicyRule('resource.owner == subject.id', 'document:*', 'edit', Effect::Allow);
```

[Full ABAC Guide →](../models/ABAC.md)

### RESTful
HTTP authorization:

```php
new PolicyRule('user-1', '/api/documents/:id', 'GET', Effect::Allow);
```

[Full RESTful Guide →](../models/RESTful.md)

## Choose by Use Case

- **Small app (< 10 users)** → [ACL](../models/ACL.md)
- **Enterprise/Teams** → [RBAC](../models/RBAC.md)
- **Multi-tenant SaaS** → [RBAC with Domains](../models/RBAC-Domains.md)
- **Dynamic ownership** → [ABAC](../models/ABAC.md)
- **API authorization** → [RESTful](../models/RESTful.md)
- **Security-critical** → [Deny-Override](../models/Deny-Override.md)

## See Also

- **[Quick Reference](QUICK-REFERENCE.md)** - Choose your model in 2 minutes
- **[Complete Cookbook](../README.md)** - Comprehensive guides for all models
- **[Beginner's Path](GETTING-STARTED.md)** - ACL → RBAC → ABAC learning path
