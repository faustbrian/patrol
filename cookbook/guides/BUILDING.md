# Building the Complete Cookbook

The cookbook is organized as individual markdown files for easy navigation and maintenance. However, you can concatenate them into a single document for offline use, PDF generation, or comprehensive searching.

## Quick Build

```bash
php cookbook/build.php
```

This generates:
- `COMPLETE-COOKBOOK.md` - All cookbook entries in one file (~174 KB)
- `TABLE-OF-CONTENTS.md` - Quick reference index

## Custom Output

```bash
php cookbook/build.php --output=my-custom-name.md
```

## File Structure

Individual cookbook files:
- `ACL.md` - Basic Access Control Lists
- `ACL-Superuser.md` - Superuser patterns
- `ACL-Without-Users.md` - Anonymous/public access
- `ACL-Without-Resources.md` - Permission types/features
- `RBAC.md` - Role-Based Access Control
- `RBAC-Resource-Roles.md` - Dual-role systems
- `RBAC-Domains.md` - Multi-tenant roles
- `ABAC.md` - Attribute-Based Access Control
- `RESTful.md` - HTTP authorization
- `Deny-Override.md` - Deny precedence patterns
- `Priority-Based.md` - Firewall-style rules

## Use Cases

### Generate PDF

```bash
php cookbook/build.php --output=patrol-cookbook.md
pandoc patrol-cookbook.md -o patrol-cookbook.pdf --toc
```

### Search Across All Patterns

```bash
php cookbook/build.php
grep -n "ownership" cookbook/COMPLETE-COOKBOOK.md
```

### Offline Documentation

The complete cookbook can be read offline or converted to other formats.

## Git Ignore

The generated files (`COMPLETE-COOKBOOK.md`, `TABLE-OF-CONTENTS.md`) are git-ignored by default since they're build artifacts. Run `build.php` whenever you need them.
