# Interactive Model Chooser

Answer 3 questions to find the right authorization model for your application.

---

## Question 1: How many users do you have?

Choose the option that best matches your application:

### A) Less than 10 users
**→ Continue to [Question 2 (Small App Track)](#question-2-small-app-track)**

**Why this matters:** Small user bases can use simpler models like ACL where you directly assign permissions to individual users.

---

### B) 10-100 users
**→ Continue to [Question 2 (Medium App Track)](#question-2-medium-app-track)**

**Why this matters:** With more users, you'll want role-based permissions to avoid managing hundreds of individual permission entries.

---

### C) 100+ users
**→ Recommended: [RBAC](../models/RBAC.md) + [ABAC](../models/ABAC.md)**

**Why this matters:** Large user bases need scalable permission systems. Use RBAC for organizational roles and ABAC for ownership/dynamic rules.

**Next:** Answer [Question 2 (Enterprise Track)](#question-2-enterprise-track) to refine your choice.

---

## Question 2 (Small App Track)

**From Question 1A:** You have < 10 users.

### Do you need multi-tenant support?

**Multi-tenant** means users belong to different organizations/workspaces with separate data and permissions.

#### No - Single Organization
**→ Recommended: [ACL](../models/ACL.md)**

Simple direct permissions work best for small, single-tenant apps.

```php
new PolicyRule('alice', 'document-1', 'edit', Effect::Allow),
new PolicyRule('bob', 'document-2', 'read', Effect::Allow),
```

**Optional add-ons:**
- Need admin bypass? → Add [ACL with Superuser](../models/ACL-Superuser.md)
- Have dynamic rules? → Continue to [Question 3](#question-3-dynamic-permissions)

---

#### Yes - Multi-Tenant
**→ Recommended: [RBAC with Domains](../models/RBAC-Domains.md)**

Even small apps need proper tenant isolation.

```php
// Alice is admin in tenant-1, viewer in tenant-2
$subject = new Subject('alice', [
    'domain' => 'tenant-1',
    'domain_roles' => [
        'tenant-1' => ['admin'],
        'tenant-2' => ['viewer'],
    ],
]);
```

**Next:** Answer [Question 3](#question-3-dynamic-permissions) to check if you need additional models.

---

## Question 2 (Medium App Track)

**From Question 1B:** You have 10-100 users.

### Do you need multi-tenant support?

#### No - Single Organization
**→ Recommended: [RBAC](../models/RBAC.md)**

Group users by job function (editor, viewer, admin).

```php
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),
new PolicyRule('viewer', 'article:*', 'read', Effect::Allow),
```

**Next:** Answer [Question 3](#question-3-dynamic-permissions) to check if you need ABAC.

---

#### Yes - Multi-Tenant
**→ Recommended: [RBAC with Domains](../models/RBAC-Domains.md)**

Users have different roles in different tenants.

```php
new PolicyRule('tenant-1::admin', 'project:*', '*', Effect::Allow, domain: 'tenant-1'),
new PolicyRule('tenant-2::viewer', 'project:*', 'read', Effect::Allow, domain: 'tenant-2'),
```

**Next:** Answer [Question 3](#question-3-dynamic-permissions) to check if you need ABAC.

---

## Question 2 (Enterprise Track)

**From Question 1C:** You have 100+ users.

### What type of application are you building?

#### A) Multi-Tenant SaaS
**→ Recommended: [RBAC with Domains](../models/RBAC-Domains.md) + [ABAC](../models/ABAC.md) + [Deny-Override](../models/Deny-Override.md)**

**Why:**
- **RBAC with Domains** - Tenant isolation with role-based access
- **ABAC** - Ownership rules (users edit their own content)
- **Deny-Override** - Security overrides (suspended users, compliance)

```php
$policy = new Policy([
    // Domain RBAC
    new PolicyRule('tenant-1::admin', '*', '*', Effect::Allow, domain: 'tenant-1'),

    // ABAC ownership
    new PolicyRule('resource.owner_id == subject.id', 'project:*', '*', Effect::Allow),

    // Deny override
    new PolicyRule('status:suspended', '*', '*', Effect::Deny, priority: Priority::Critical),
]);
```

**→ [View full SaaS example](../models/RBAC-Domains.md#real-world-example-multi-tenant-saas)**

---

#### B) REST API / Microservices
**→ Recommended: [RESTful](../models/RESTful.md) + [RBAC](../models/RBAC.md)**

**Why:**
- **RESTful** - HTTP method and path matching
- **RBAC** - API key roles/tiers

```php
// Free tier: limited endpoints
new PolicyRule('api-tier:free', '/api/public/*', 'GET', Effect::Allow),

// Premium tier: full access
new PolicyRule('api-tier:premium', '/api/*', '*', Effect::Allow),
```

**→ [View full API example](../models/RESTful.md#real-world-example-api-gateway)**

---

#### C) Security-Critical / Compliance
**→ Recommended: [RBAC](../models/RBAC.md) + [ABAC](../models/ABAC.md) + [Deny-Override](../models/Deny-Override.md) + [Priority-Based](../models/Priority-Based.md)**

**Why:**
- **RBAC** - Organizational roles
- **ABAC** - Attribute-based rules (clearance levels, departments)
- **Deny-Override** - Explicit denials for compliance
- **Priority-Based** - Critical rules evaluated first

```php
// High priority: Block suspended users
new PolicyRule('status:suspended', '*', '*', Effect::Deny, priority: Priority::Critical),

// Normal: RBAC
new PolicyRule('security-officer', 'sensitive-data:*', 'read', Effect::Allow),

// ABAC: Clearance level
new PolicyRule('subject.clearance >= resource.classification', 'document:*', 'read', Effect::Allow),
```

**→ [View full security example](../models/Deny-Override.md#real-world-example-healthcare-records)**

---

#### D) Standard Enterprise Application
**→ Recommended: [RBAC](../models/RBAC.md) + [ABAC](../models/ABAC.md)**

**Why:**
- **RBAC** - Job function roles (manager, employee, contractor)
- **ABAC** - Ownership and department-based access

```php
$policy = new Policy([
    // RBAC
    new PolicyRule('manager', 'report:*', 'approve', Effect::Allow),

    // ABAC ownership
    new PolicyRule('resource.created_by == subject.id', 'report:*', 'edit', Effect::Allow),

    // ABAC department
    new PolicyRule('subject.department == resource.department', 'report:*', 'read', Effect::Allow),
]);
```

**→ [View full enterprise example](../models/RBAC.md#real-world-example-enterprise-application)**

---

## Question 3: Dynamic Permissions

**From any track above.** Do you need any of these features?

### ✅ Select all that apply:

- **Ownership-based access** (users edit their own content)
- **Time-based rules** (office hours, temporary access)
- **Location-based** (IP restrictions, geo-fencing)
- **Attribute matching** (same department, same team)
- **Resource state conditions** (status = draft, not archived)
- **Relationship-based** (manager approves subordinate requests)

### If you selected ANY:
**→ Add [ABAC](../models/ABAC.md) to your model**

ABAC complements other models with dynamic, condition-based rules:

```php
// Combine RBAC + ABAC
$policy = new Policy([
    // RBAC: Editors can edit articles
    new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),

    // ABAC: Anyone can edit their own articles
    new PolicyRule('resource.author_id == subject.id', 'article:*', 'edit', Effect::Allow),

    // ABAC: Don't allow editing archived articles
    new PolicyRule('resource.status != "archived"', 'article:*', 'edit', Effect::Allow),
]);
```

**→ [Learn ABAC](../models/ABAC.md)**

---

### If you selected NONE:
Your recommended model from Question 2 is sufficient.

---

## Special Cases

### Public API / No Authentication
**→ [ACL without Users](../models/ACL-Without-Users.md)**

```php
// Anyone can read public blog posts
new PolicyRule('*', 'blog-post:*', 'read', Effect::Allow),
```

---

### Feature Flags / SaaS Tiers / Capabilities
**→ [ACL without Resources](../models/ACL-Without-Resources.md)**

```php
// Premium users can export to PDF
new PolicyRule('subscription:premium', '*', 'export-pdf', Effect::Allow),

// Free users limited to 5 projects
new PolicyRule('subscription:free', '*', 'create-project', Effect::Allow),
```

---

### Admin Bypass
**→ [ACL with Superuser](../models/ACL-Superuser.md)**

```php
// Superuser has all permissions
new PolicyRule('superuser', '*', '*', Effect::Allow),
```

---

### Security Overrides
Need explicit denials that override all allows?

**→ [Deny-Override](../models/Deny-Override.md)**

```php
// Allow editors to edit
new PolicyRule('editor', 'article:*', 'edit', Effect::Allow),

// But deny suspended users (overrides above)
new PolicyRule('status:suspended', '*', '*', Effect::Deny),
```

---

### Firewall-Style Rule Ordering
Need explicit control over evaluation order?

**→ [Priority-Based](../models/Priority-Based.md)**

```php
// Critical: Block attackers
new PolicyRule('status:blocked', '*', '*', Effect::Deny, priority: Priority::Critical),

// Normal: Allow users
new PolicyRule('user:*', 'resource:*', 'read', Effect::Allow, priority: Priority::Normal),
```

---

## Quick Recommendations by Type

### E-commerce
**Models:** RBAC + ABAC
- **RBAC** for staff roles (admin, support, warehouse)
- **ABAC** for customers (can view/cancel their own orders)

**→ [Example in ABAC docs](../models/ABAC.md#pattern-3-e-commerce-platform)**

---

### Healthcare / HIPAA
**Models:** RBAC + ABAC + Deny-Override
- **RBAC** for clinical roles (doctor, nurse, admin)
- **ABAC** for patient assignments (assigned doctors only)
- **Deny-Override** for audit requirements

**→ [Example in Deny-Override docs](../models/Deny-Override.md#real-world-example-healthcare-records)**

---

### Project Management
**Models:** RBAC with Domains + ABAC
- **RBAC with Domains** for per-project roles
- **ABAC** for task assignees

**→ [Example in ABAC docs](../models/ABAC.md#pattern-7-project-management-tool)**

---

### Content Platform
**Models:** RBAC + ABAC + Deny-Override
- **RBAC** for content roles (author, editor, moderator)
- **ABAC** for ownership and state (draft, published)
- **Deny-Override** for flagged content

**→ [Example in ABAC docs](../models/ABAC.md#pattern-6-content-moderation-platform)**

---

### File Storage
**Models:** ACL + ABAC
- **ACL** for explicit shares
- **ABAC** for ownership

**→ [Example in ABAC docs](../models/ABAC.md#pattern-8-file-storage-service)**

---

## Summary: Your Recommendation

Based on your answers:

1. **User count** determines base model (ACL vs RBAC)
2. **Multi-tenant** adds Domains
3. **Dynamic needs** adds ABAC
4. **Security requirements** may add Deny-Override or Priority-Based

### Common Combinations

| App Type | Recommended Models |
|----------|-------------------|
| Small app | ACL |
| Small app + admin | ACL + Superuser |
| Team app | RBAC |
| Multi-tenant SaaS | RBAC with Domains + ABAC |
| Public API | RESTful or ACL without Users |
| Feature flags | ACL without Resources |
| Enterprise | RBAC + ABAC + Deny-Override |
| Security-critical | RBAC + ABAC + Deny-Override + Priority-Based |

---

## Next Steps

1. **Read your recommended model's full documentation**
2. **Review the real-world examples** in each guide
3. **Try the code examples** in `php artisan tinker`
4. **Implement tests** using the Pest examples
5. **Set up Laravel integration** with resolvers and middleware

---

## Still Unsure?

- **[Beginner's Path](./GETTING-STARTED.md)** - Learn ACL → RBAC → ABAC progressively
- **[Quick Reference](./QUICK-REFERENCE.md)** - Common patterns cheat sheet
- **[Full Cookbook](../README.md)** - Comprehensive documentation for all models
- **[Model Comparison Table](../README.md#model-comparison)** - Side-by-side comparison

---

Happy authorizing! 🔐
