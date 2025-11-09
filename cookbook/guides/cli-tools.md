# CLI Tools

Command-line utilities for testing, debugging, and managing Patrol authorization policies.

## Overview

Patrol provides powerful CLI commands via Laravel Artisan to help you:
- Test authorization decisions interactively
- Debug policy rules and understand why decisions are made
- Verify policy configurations
- Generate policy templates
- Inspect loaded policies

These tools are essential for development, debugging, and troubleshooting authorization issues.

---

## Commands

### `patrol:check`

Check if a subject can perform an action on a resource.

**Usage:**
```bash
php artisan patrol:check <subject> <resource> <action> [options]
```

**Arguments:**
- `subject` - Subject identifier (e.g., `user:123`, `role:editor`, `alice@example.com`)
- `resource` - Resource identifier (e.g., `post:456`, `document:789`, `/api/users`)
- `action` - Action to check (e.g., `read`, `write`, `delete`, `GET`, `POST`)

**Options:**
- `--json` - Output result as JSON
- `--verbose` - Show detailed policy evaluation trace
- `--domain=DOMAIN` - Specify domain/tenant context
- `--attributes=JSON` - Pass custom subject/resource attributes

**Examples:**

```bash
# Basic authorization check
php artisan patrol:check user:123 post:456 edit

# Output:
# ✅ ALLOWED
# Subject 'user:123' CAN perform 'edit' on 'post:456'

# Check with role prefix
php artisan patrol:check role:editor post:456 publish

# Output:
# ✅ ALLOWED
# Subject 'role:editor' CAN perform 'publish' on 'post:456'

# Check that should fail
php artisan patrol:check user:999 post:456 delete

# Output:
# ❌ DENIED
# Subject 'user:999' CANNOT perform 'delete' on 'post:456'

# JSON output for scripting
php artisan patrol:check user:123 post:456 edit --json

# Output:
# {"allowed":true,"subject":"user:123","resource":"post:456","action":"edit"}

# Verbose output with rule matching details
php artisan patrol:check user:123 post:456 edit --verbose

# Output:
# 🔍 Evaluating authorization...
#
# Subject: user:123
# Resource: post:456 (type: post)
# Action: edit
#
# Policy Rules Evaluated:
# ✓ Rule 1: ALLOW (priority: 100)
#    Subject: role:editor | Resource: post:* | Action: edit
#    → This rule matches the query
#
# ✗ Rule 2: DENY (priority: 50)
#    Subject: * | Resource: * | Action: delete
#    → Does not match: action mismatch (expected: delete, got: edit)
#
# Final Decision: ✅ ALLOWED

# Check with domain context (multi-tenant)
php artisan patrol:check user:123 project:789 edit --domain=tenant-1

# Check with custom attributes (ABAC)
php artisan patrol:check user:123 document:456 edit --attributes='{"subject":{"department":"engineering"},"resource":{"department":"engineering"}}'
```

**Exit Codes:**
- `0` - Allowed
- `1` - Denied
- `2` - Error (invalid arguments, policy not found, etc.)

**Use Cases:**

1. **Manual Testing During Development**
   ```bash
   # Test if new role permissions work
   php artisan patrol:check role:moderator comment:123 approve
   ```

2. **CI/CD Integration**
   ```bash
   # Verify critical permissions in tests
   php artisan patrol:check admin@example.com * * --json || exit 1
   ```

3. **Debugging Authorization Issues**
   ```bash
   # Find out why user can't access resource
   php artisan patrol:check user:456 document:789 read --verbose
   ```

4. **Shell Scripts**
   ```bash
   #!/bin/bash
   result=$(php artisan patrol:check user:$USER_ID post:$POST_ID edit --json)
   if [ $? -eq 0 ]; then
       echo "User can edit post"
   else
       echo "User cannot edit post"
   fi
   ```

---

### `patrol:explain`

Get a detailed explanation of why an authorization decision was made, with step-by-step rule evaluation.

**Usage:**
```bash
php artisan patrol:explain <subject> <resource> <action> [options]
```

**Arguments:**
- `subject` - Subject identifier
- `resource` - Resource identifier
- `action` - Action to explain

**Options:**
- `--domain=DOMAIN` - Specify domain/tenant context
- `--attributes=JSON` - Pass custom subject/resource attributes
- `--show-all-rules` - Show all rules, not just matching ones
- `--format=FORMAT` - Output format: `text` (default), `json`, `markdown`

**Examples:**

```bash
# Explain why user can edit a post
php artisan patrol:explain user:123 post:456 edit

# Output:
# 🔍 Authorization Decision Explanation
# =====================================
#
# Query:
#   Subject:  user:123
#   Resource: post:456 (type: post)
#   Action:   edit
#
# Subject Attributes:
#   - id: 123
#   - roles: [editor, author]
#   - department: engineering
#
# Resource Attributes:
#   - id: 456
#   - type: post
#   - author_id: 123
#   - status: draft
#
# Policy Evaluation:
# ==================
#
# ✓ Rule 1: ALLOW (priority: 100)
#    Subject: resource.author_id == subject.id
#    Resource: post:*
#    Action: edit
#    → ✅ MATCH - Condition evaluates to TRUE (123 == 123)
#    → This is an ABAC rule checking ownership
#
# ✓ Rule 2: ALLOW (priority: 90)
#    Subject: role:editor
#    Resource: post:*
#    Action: edit
#    → ✅ MATCH - Subject has role 'editor'
#
# ✗ Rule 3: DENY (priority: 80)
#    Subject: *
#    Resource: post:archived:*
#    Action: edit
#    → ❌ NO MATCH - Resource pattern doesn't match 'post:456'
#
# ✗ Rule 4: ALLOW (priority: 1)
#    Subject: role:viewer
#    Resource: post:*
#    Action: read
#    → ❌ NO MATCH - Action mismatch (expected: read, got: edit)
#
# Effect Resolution:
# ==================
#   Matching Rules: 2
#   Allow Rules: 2
#   Deny Rules: 0
#   Resolver: Standard (all allows required)
#
# 🎯 Final Decision: ✅ ALLOWED
#
# Reason: Subject 'user:123' has 'editor' role AND is the author (user:123) of post:456

# Show all rules, including non-matching
php artisan patrol:explain user:123 post:456 edit --show-all-rules

# JSON format for programmatic access
php artisan patrol:explain user:123 post:456 edit --format=json

# Output:
# {
#   "decision": "allow",
#   "subject": "user:123",
#   "resource": "post:456",
#   "action": "edit",
#   "matchingRules": [
#     {"subject": "resource.author_id == subject.id", "effect": "allow", "priority": 100, "matched": true},
#     {"subject": "role:editor", "resource": "post:*", "action": "edit", "effect": "allow", "priority": 90, "matched": true}
#   ],
#   "nonMatchingRules": [
#     {"subject": "*", "resource": "post:archived:*", "action": "edit", "effect": "deny", "priority": 80, "matched": false}
#   ]
# }

# Markdown format for documentation
php artisan patrol:explain user:123 post:456 edit --format=markdown > explanation.md
```

**Use Cases:**

1. **Debugging Complex Policies**
   ```bash
   # Understand why a permission is denied
   php artisan patrol:explain user:789 document:123 delete
   ```

2. **Documenting Authorization Logic**
   ```bash
   # Generate markdown documentation
   php artisan patrol:explain admin@example.com project:456 manage --format=markdown > docs/permissions.md
   ```

3. **Training & Onboarding**
   ```bash
   # Show team how ABAC ownership rules work
   php artisan patrol:explain user:123 post:456 edit
   ```

4. **Auditing Authorization Decisions**
   ```bash
   # Log detailed explanation for compliance
   php artisan patrol:explain user:123 sensitive-data:789 access --format=json >> audit.log
   ```

---

### `patrol:list-rules`

List all policy rules currently loaded.

**Usage:**
```bash
php artisan patrol:list-rules [options]
```

**Options:**
- `--subject=PATTERN` - Filter by subject pattern
- `--resource=PATTERN` - Filter by resource pattern
- `--action=PATTERN` - Filter by action pattern
- `--effect=EFFECT` - Filter by effect (allow/deny)
- `--domain=DOMAIN` - Filter by domain
- `--priority=PRIORITY` - Filter by priority
- `--format=FORMAT` - Output format: `table` (default), `json`, `csv`

**Examples:**

```bash
# List all rules
php artisan patrol:list-rules

# Output:
# ┌─────────────────┬──────────────┬────────┬────────┬──────────┬────────┐
# │ Subject         │ Resource     │ Action │ Effect │ Priority │ Domain │
# ├─────────────────┼──────────────┼────────┼────────┼──────────┼────────┤
# │ role:admin      │ *            │ *      │ allow  │ 100      │        │
# │ role:editor     │ post:*       │ edit   │ allow  │ 90       │        │
# │ role:viewer     │ post:*       │ read   │ allow  │ 80       │        │
# │ status:banned   │ *            │ *      │ deny   │ 200      │        │
# └─────────────────┴──────────────┴────────┴────────┴──────────┴────────┘

# Filter by resource
php artisan patrol:list-rules --resource='post:*'

# Filter by effect (show only denials)
php artisan patrol:list-rules --effect=deny

# Filter by subject (show admin rules)
php artisan patrol:list-rules --subject='role:admin'

# JSON output
php artisan patrol:list-rules --format=json

# CSV export
php artisan patrol:list-rules --format=csv > policies.csv
```

**Use Cases:**

1. **Policy Auditing**
   ```bash
   # Export all policies for review
   php artisan patrol:list-rules --format=csv > audit-$(date +%Y%m%d).csv
   ```

2. **Finding Conflicts**
   ```bash
   # Check what rules affect a resource
   php artisan patrol:list-rules --resource='user:*'
   ```

3. **Documentation Generation**
   ```bash
   # Generate policy documentation
   php artisan patrol:list-rules --format=json | jq '.' > docs/current-policies.json
   ```

---

### `patrol:test-policy`

Test a policy against a batch of authorization scenarios from a file.

**Usage:**
```bash
php artisan patrol:test-policy <test-file> [options]
```

**Arguments:**
- `test-file` - Path to YAML/JSON file with test scenarios

**Options:**
- `--verbose` - Show detailed output for each test
- `--stop-on-failure` - Stop at first failed test
- `--format=FORMAT` - Output format: `text`, `json`, `junit`

**Test File Format (YAML):**

```yaml
# tests/policies/permissions.yml
scenarios:
  - name: "Admin can do anything"
    subject: "role:admin"
    resource: "post:123"
    action: "delete"
    expected: allow

  - name: "Editor can edit posts"
    subject: "role:editor"
    resource: "post:123"
    action: "edit"
    expected: allow

  - name: "Viewer cannot edit posts"
    subject: "role:viewer"
    resource: "post:123"
    action: "edit"
    expected: deny

  - name: "Banned user denied everything"
    subject: "status:banned"
    resource: "post:123"
    action: "read"
    expected: deny

  - name: "Owner can edit their own post"
    subject: "user:123"
    resource: "post:456"
    action: "edit"
    attributes:
      subject:
        id: 123
      resource:
        author_id: 123
    expected: allow
```

**Examples:**

```bash
# Run policy tests
php artisan patrol:test-policy tests/policies/permissions.yml

# Output:
# 🧪 Running Policy Tests
# =======================
#
# ✅ Admin can do anything - PASSED
# ✅ Editor can edit posts - PASSED
# ❌ Viewer cannot edit posts - FAILED
#    Expected: deny
#    Actual: allow
#    Reason: Rule 'role:viewer can read post:*' incorrectly matches
# ✅ Banned user denied everything - PASSED
# ✅ Owner can edit their own post - PASSED
#
# Results: 4/5 tests passed (80%)

# Verbose output
php artisan patrol:test-policy tests/policies/permissions.yml --verbose

# JUnit XML output for CI/CD
php artisan patrol:test-policy tests/policies/permissions.yml --format=junit > test-results.xml

# Stop on first failure
php artisan patrol:test-policy tests/policies/permissions.yml --stop-on-failure
```

**Use Cases:**

1. **Automated Testing**
   ```bash
   # In CI/CD pipeline
   php artisan patrol:test-policy tests/policies/*.yml --format=junit
   ```

2. **Regression Testing**
   ```bash
   # Ensure policies still work after changes
   php artisan patrol:test-policy tests/policies/regression.yml
   ```

3. **Policy Validation**
   ```bash
   # Verify new policy rules work correctly
   php artisan patrol:test-policy tests/policies/new-feature.yml --verbose
   ```

---

### `patrol:generate-policy`

Generate policy template files from existing roles/resources.

**Usage:**
```bash
php artisan patrol:generate-policy <type> [options]
```

**Arguments:**
- `type` - Policy type: `rbac`, `acl`, `restful`, `crud`

**Options:**
- `--output=PATH` - Output file path
- `--format=FORMAT` - Output format: `php`, `json`, `yaml`
- `--roles=ROLES` - Comma-separated role list (for RBAC)
- `--resources=RESOURCES` - Comma-separated resource list

**Examples:**

```bash
# Generate RBAC policy template
php artisan patrol:generate-policy rbac --roles=admin,editor,viewer --resources=posts,comments,users

# Output file: storage/policies/rbac-template.php
# use Patrol\Laravel\Builders\RbacPolicyBuilder;
#
# $policy = RbacPolicyBuilder::make()
#     ->role('admin')
#         ->fullAccess()
#         ->on('posts')
#     ->role('admin')
#         ->fullAccess()
#         ->on('comments')
#     ->role('admin')
#         ->fullAccess()
#         ->on('users')
#     ->role('editor')
#         ->can(['read', 'write'])
#         ->on('posts')
#     // ... etc
#     ->build();

# Generate RESTful policy in JSON
php artisan patrol:generate-policy restful --resources=/api/posts,/api/users --format=json --output=storage/api-policy.json

# Generate ACL template
php artisan patrol:generate-policy acl --output=storage/policies/acl-template.php
```

**Use Cases:**

1. **Quick Scaffolding**
   ```bash
   # Generate starting point for new project
   php artisan patrol:generate-policy rbac --roles=admin,user
   ```

2. **Migration from Other Systems**
   ```bash
   # Create template to port existing permissions
   php artisan patrol:generate-policy acl --format=yaml
   ```

---

### `patrol:validate`

Validate policy configuration and check for conflicts.

**Usage:**
```bash
php artisan patrol:validate [options]
```

**Options:**
- `--check-conflicts` - Check for conflicting rules
- `--check-coverage` - Check if all resources/actions are covered
- `--strict` - Fail on warnings (not just errors)

**Examples:**

```bash
# Validate current policy
php artisan patrol:validate

# Output:
# ✅ Policy validation passed
#
# Summary:
#   - Total rules: 47
#   - No syntax errors
#   - No conflicts detected

# Check for conflicts
php artisan patrol:validate --check-conflicts

# Output:
# ⚠️  Warning: Potential conflicts detected
#
# Conflict 1:
#   Rule 12: ALLOW role:editor → post:* → edit (priority: 90)
#   Rule 34: DENY status:suspended → post:* → edit (priority: 100)
#   → Recommendation: Suspended editors will be denied (higher priority deny)
#
# Conflict 2:
#   Rule 5: ALLOW * → public:* → read (priority: 1)
#   Rule 23: DENY * → public:secret:* → read (priority: 50)
#   → Recommendation: Secret public resources will be denied (deny override)

# Strict validation (fail on warnings)
php artisan patrol:validate --strict
```

**Use Cases:**

1. **Pre-Deployment Checks**
   ```bash
   # In CI/CD before deploying
   php artisan patrol:validate --strict || exit 1
   ```

2. **Policy Review**
   ```bash
   # Periodic audits
   php artisan patrol:validate --check-conflicts
   ```

---

### `patrol:migrate-from-spatie`

Migrate roles and permissions from spatie/laravel-permission to Patrol.

**Usage:**
```bash
php artisan patrol:migrate-from-spatie [options]
```

**Options:**
- `--dry-run` - Show what would be migrated without persisting changes
- `--connection=CONNECTION` - Database connection to use for Patrol policies
- `--spatie-connection=CONNECTION` - Database connection for Spatie tables (defaults to --connection)
- `--priority=PRIORITY` - Default priority for migrated rules (default: 10)

**Examples:**

```bash
# Migrate from Spatie to Patrol
php artisan patrol:migrate-from-spatie

# Output:
# Migrating Spatie permissions to Patrol...
#
# ✓ Migrating role permissions
# Skipping user role assignments (use Subject attributes at runtime)
# ✓ Migrating direct user permissions
#
# Found 15 permission(s) to migrate
#
# Sample of rules to be created:
# ┌────────────┬──────────┬────────┬────────┬──────────┐
# │ Subject    │ Resource │ Action │ Effect │ Priority │
# ├────────────┼──────────┼────────┼────────┼──────────┤
# │ role:editor│ posts    │ edit   │ ALLOW  │ 10       │
# │ role:editor│ posts    │ delete │ ALLOW  │ 10       │
# │ role:admin │ users    │ manage │ ALLOW  │ 10       │
# │ user:123   │ settings │ view   │ ALLOW  │ 10       │
# └────────────┴──────────┴────────┴────────┴──────────┘
#
# ✓ Persisting policy rules
#
# ✅ Migration completed successfully!

# Dry-run to preview changes
php artisan patrol:migrate-from-spatie --dry-run

# Output:
# ⚠️  DRY RUN MODE - No changes will be persisted
#
# [... migration preview ...]
#
# ⚠️  Dry run complete - no changes were persisted

# Migrate with custom priority
php artisan patrol:migrate-from-spatie --priority=50

# Migrate from different database connection
php artisan patrol:migrate-from-spatie --connection=tenant_db
```

**Migration Strategy:**

The command migrates Spatie permissions to Patrol using an RBAC (Role-Based Access Control) approach:

1. **Role Permissions** - Migrated as `role:{name}` subjects
   ```
   Spatie: editor role has "edit posts" permission
   Patrol: subject="role:editor", resource="posts", action="edit", effect=ALLOW
   ```

2. **Direct User Permissions** - Migrated as `user:{id}` subjects
   ```
   Spatie: user 123 has direct "view analytics" permission
   Patrol: subject="user:123", resource="analytics", action="view", effect=ALLOW
   ```

3. **User Role Assignments** - NOT denormalized into user-specific rules
   ```
   Spatie: user 123 has "editor" role
   Patrol: NO migration (handled at runtime via Subject attributes)
   ```

**Runtime Role Evaluation:**

After migration, users receive permissions through their roles by passing role information in the Subject when authorizing:

```php
use Patrol\Core\ValueObjects\Subject;
use Patrol\Core\ValueObjects\Resource;
use Patrol\Core\ValueObjects\Action;

// Get user's roles from your User model
$user = User::find(123);
$userRoles = $user->roles->pluck('name')->map(fn($r) => "role:$r")->toArray();
// Example: ['role:editor', 'role:author']

// Create Subject with roles in attributes
$subject = new Subject(
    id: "user:{$user->id}",
    attributes: ['roles' => $userRoles]
);

// Authorize - the RbacRuleMatcher will check if any of the user's roles
// match the policy rule's subject pattern
$patrol->authorize(
    $subject,
    new Resource('posts', 'Post'),
    new Action('edit')
);
```

This approach:
- ✅ Avoids denormalization overhead (no user-specific rules for every role permission)
- ✅ Supports dynamic role changes (add/remove roles without recreating policies)
- ✅ Matches Spatie's behavior (users get permissions through roles)
- ✅ Scales efficiently (one rule per role, not per user-role combination)

**Permission Name Parsing:**

The migration automatically parses various Spatie permission naming conventions:

```
"edit posts"     → action="edit", resource="posts"
"posts.edit"     → action="edit", resource="posts"
"posts-edit"     → action="edit", resource="posts"
"admin"          → action="admin", resource="*"
```

**Use Cases:**

1. **Initial Migration**
   ```bash
   # Test first with dry-run
   php artisan patrol:migrate-from-spatie --dry-run

   # Then execute migration
   php artisan patrol:migrate-from-spatie
   ```

2. **Multi-Tenant Applications**
   ```bash
   # Migrate each tenant's permissions
   foreach ($tenants as $tenant) {
       DB::setDefaultConnection("tenant_{$tenant->id}");
       Artisan::call('patrol:migrate-from-spatie', [
           '--connection' => "tenant_{$tenant->id}"
       ]);
   }
   ```

3. **Incremental Migration**
   ```bash
   # Migrate from main database, store in separate connection
   php artisan patrol:migrate-from-spatie \
       --spatie-connection=mysql \
       --connection=patrol_db
   ```

---

## Integration Examples

### CI/CD Pipeline

```yaml
# .github/workflows/test.yml
name: Test Authorization Policies

on: [push, pull_request]

jobs:
  test-policies:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Validate policies
        run: php artisan patrol:validate --strict

      - name: Run policy tests
        run: php artisan patrol:test-policy tests/policies/*.yml --format=junit

      - name: Check critical permissions
        run: |
          php artisan patrol:check role:admin '*' '*' || exit 1
          php artisan patrol:check role:guest sensitive:* delete --json | grep -q '"allowed":false' || exit 1
```

### Monitoring Script

```bash
#!/bin/bash
# scripts/check-permissions.sh

# Check if banned users are properly denied
php artisan patrol:check status:banned '*' '*' --json | \
  jq -e '.allowed == false' || \
  echo "ERROR: Banned users are not being denied!"

# Check if admins have full access
php artisan patrol:check role:admin '*' '*' --json | \
  jq -e '.allowed == true' || \
  echo "ERROR: Admins don't have full access!"

# Log current policy state
php artisan patrol:list-rules --format=json > logs/policies-$(date +%Y%m%d).json
```

### Development Helpers

```bash
# .bash_aliases or .zshrc

# Quick permission check
alias pcheck='php artisan patrol:check'

# Explain permission
alias pexplain='php artisan patrol:explain'

# List rules
alias prules='php artisan patrol:list-rules'

# Usage:
# pcheck user:123 post:456 edit
# pexplain user:123 post:456 edit
# prules --resource='post:*'
```

---

## Troubleshooting

### Command Not Found

```bash
# Error: Command "patrol:check" is not defined
```

**Solution:** Ensure Patrol service provider is registered:
```php
// config/app.php
'providers' => [
    // ...
    Patrol\Laravel\PatrolServiceProvider::class,
],
```

### "No Policy Loaded"

```bash
# Error: No policy repository configured
```

**Solution:** Configure policy repository in `config/patrol.php`:
```php
'repository' => \Patrol\Laravel\Repositories\DatabasePolicyRepository::class,
```

### Attributes Not Working

```bash
php artisan patrol:check user:123 post:456 edit --attributes='...'
# Still being denied
```

**Solution:** Check subject/resource resolvers are configured:
```php
// In AppServiceProvider
Patrol::resolveSubject(function () {
    return new Subject(auth()->id(), [
        'id' => auth()->id(),
        'roles' => auth()->user()?->roles->pluck('name')->all(),
    ]);
});
```

---

## Best Practices

### 1. Use JSON Output for Scripting

```bash
# ✅ GOOD - Parse JSON in scripts
result=$(php artisan patrol:check user:$ID post:$PID edit --json)
allowed=$(echo $result | jq '.allowed')

# ❌ BAD - Parse human-readable text
result=$(php artisan patrol:check user:$ID post:$PID edit)
```

### 2. Create Test Files for Regression Testing

```yaml
# tests/policies/critical-permissions.yml
scenarios:
  - name: "Admins have full access"
    subject: "role:admin"
    resource: "*"
    action: "*"
    expected: allow

  - name: "Banned users denied"
    subject: "status:banned"
    resource: "*"
    action: "*"
    expected: deny
```

Run in CI:
```bash
php artisan patrol:test-policy tests/policies/critical-permissions.yml --format=junit
```

### 3. Use `--verbose` for Debugging

```bash
# When something doesn't work as expected
php artisan patrol:check user:123 post:456 edit --verbose
```

### 4. Document Expected Behavior

```bash
# Generate explanations for documentation
php artisan patrol:explain admin user:* delete --format=markdown > docs/admin-permissions.md
```

### 5. Validate Before Deploying

```bash
# In deployment script
php artisan patrol:validate --strict || {
    echo "Policy validation failed!"
    exit 1
}
```

---

## Related Guides

- [Policy Builders](./policy-builders.md) - Build policies using fluent API
- [Persisting Policies](./persisting-policies.md) - Save policies to storage
- [Getting Started](./getting-started.md) - Learn Patrol basics
- [Quick Reference](./quick-reference.md) - Cheat sheet
