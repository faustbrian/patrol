# Policy Inheritance - Hierarchical Resource Permissions

Implement hierarchical permission structures where child resources inherit permissions from parent resources, similar to filesystem permissions.

## Concept

```
folder:123 (parent)
  ├─ document:456 (child)
  └─ document:789 (child)

Rule on parent → Automatically applies to children
```

## Basic Usage

```php
use Patrol\Core\Engine\PolicyInheritance;

$inheritance = new PolicyInheritance();

// Policy with parent resource rule
$policy = new Policy([
    new PolicyRule('role:editor', 'folder:123', 'read', Effect::Allow),
]);

// Target child resource
$childResource = new Resource('folder:123/document:456', 'Document');

// Expand policy with inherited rules
$expandedPolicy = $inheritance->expandInheritedRules($policy, $childResource);

// Now includes BOTH:
// 1. Original: role:editor → folder:123 → read
// 2. Inherited: role:editor → folder:123/document:456 → read
```

## Path-Based Hierarchies

Uses slash-delimited paths for parent-child relationships:

```php
// Parent-child relationships
'folder:123' is parent of 'folder:123/document:456'         // ✅ Match
'folder:123' is parent of 'folder:123/subfolder:78/doc:99' // ✅ Match
'folder:123' is parent of 'folder:456/document:789'         // ❌ No match
```

## Examples

### File System Permissions

```php
$policy = new Policy([
    // Grant folder access
    new PolicyRule('role:manager', 'folder:sales', 'read', Effect::Allow),
]);

$childDoc = new Resource('folder:sales/document:Q4-report', 'Document');

// Inherit folder permissions to documents
$expanded = $inheritance->expandInheritedRules($policy, $childDoc);

// Manager can now read both folder AND its documents
```

### Nested Org Structure

```php
// Department → Team → Project hierarchy
$policy = new Policy([
    new PolicyRule('role:director', 'dept:engineering', 'manage', Effect::Allow),
]);

$teamResource = new Resource('dept:engineering/team:backend', 'Team');
$projectResource = new Resource('dept:engineering/team:backend/project:api-v2', 'Project');

// Director permissions cascade down hierarchy
$inheritedPolicy = $inheritance->expandInheritedRules($policy, $projectResource);
```

---

## Wildcards and Inheritance

Wildcard (`*`) and null resources are excluded from inheritance:

```php
$policy = new Policy([
    new PolicyRule('admin', '*', 'delete', Effect::Allow),  // Wildcard
    new PolicyRule('user', null, 'view', Effect::Allow),    // Null
]);

$resource = new Resource('folder:123/document:456', 'Document');

// Neither rule inherits (wildcards/nulls excluded)
$expanded = $inheritance->expandInheritedRules($policy, $resource);
// Only contains original rules
```

---

## Testing

```php
test('child resource inherits parent permissions', function () {
    $inheritance = new PolicyInheritance();

    $policy = new Policy([
        new PolicyRule('admin', 'folder:root', 'read', Effect::Allow),
    ]);

    $childResource = new Resource('folder:root/document:child', 'Document');

    $expanded = $inheritance->expandInheritedRules($policy, $childResource);

    // Original + inherited = 2 rules
    expect($expanded->rules)->toHaveCount(2);

    // Inherited rule targets child resource
    $inheritedRule = $expanded->rules[1];
    expect($inheritedRule->resource)->toBe('folder:root/document:child');
});
```

---

## Combined with Policy Extends

Use with policy inheritance (name/extends):

```php
// Base policy for all documents
$basePolicy = new Policy(
    rules: [
        new PolicyRule('*', 'document:*', 'read', Effect::Allow),
    ],
    name: 'base-document-policy'
);

// Specific policy extending base
$specificPolicy = new Policy(
    rules: [
        new PolicyRule('role:editor', 'folder:restricted', 'edit', Effect::Allow),
    ],
    name: 'restricted-editor-policy',
    extends: 'base-document-policy'
);

// Inherit base rules
$merged = $specificPolicy->inheritFrom($basePolicy);

// Apply hierarchical inheritance
$childResource = new Resource('folder:restricted/document:secret', 'Document');
$final = $inheritance->expandInheritedRules($merged, $childResource);
```

---

## Related Documentation

- **[RBAC](../models/rbac.md)** - Role-based policies
- **[ABAC](../models/abac.md)** - Attribute-based conditions
- **[Policy Building](../guides/policy-builders.md)** - Construct policies

---

**Organizational tip:** Policy inheritance reduces duplication when managing hierarchical resources like folders, departments, or nested categories. Define permissions once at the parent level.
