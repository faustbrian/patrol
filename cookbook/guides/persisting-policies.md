# Persisting Policies with Storage Drivers

Learn how to save policy builder results to different storage backends (database, JSON, YAML, XML, TOML, and serialized formats).

## Overview

After building policies with `RbacPolicyBuilder`, `AclPolicyBuilder`, or other builders, you need to persist them to storage. Patrol provides multiple storage drivers that implement a unified `save()` method for policy persistence.

## Quick Start

```php
use Patrol\Laravel\Builders\RbacPolicyBuilder;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

// Build your policy
$policy = RbacPolicyBuilder::make()
    ->role('admin')->can('*')->on('*')
    ->role('editor')->can(['read', 'write'])->on('posts')
    ->build();

// Save to database
$repository = new DatabasePolicyRepository();
$repository->save($policy);
```

## Storage Drivers

### Database Storage (Eloquent)

Store policies in your Laravel database with automatic timestamps and soft deletes.

```php
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

$repository = new DatabasePolicyRepository(
    connection: 'default' // Optional: specify connection
);

$repository->save($policy);
```

**Database Schema:**
- Table: `patrol_policies`
- Columns: `subject`, `resource`, `action`, `effect`, `priority`, `domain`, `created_at`, `updated_at`

**Benefits:**
- Transactional integrity
- Query optimization with indexes
- Automatic timestamps
- Supports soft deletes
- Multi-tenant via connections

### JSON Storage

Human-readable JSON files with pretty printing and versioning support.

```php
use Patrol\Core\Storage\JsonPolicyRepository;
use Patrol\Core\ValueObjects\FileMode;

$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single, // or FileMode::Multiple
    version: '1.0.0' // Optional
);

$repository->save($policy);
```

**Output Format:**
```json
[
  {
    "subject": "role:admin",
    "resource": "*",
    "action": "*",
    "effect": "Allow",
    "priority": 100
  },
  {
    "subject": "role:editor",
    "resource": "posts",
    "action": "read",
    "effect": "Allow",
    "priority": 1
  }
]
```

**Benefits:**
- Human-readable
- Version control friendly
- Easy to edit manually
- Supports semantic versioning

### YAML Storage

Clean, readable YAML format using Symfony YAML component.

```php
use Patrol\Core\Storage\YamlPolicyRepository;

$repository = new YamlPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);

$repository->save($policy);
```

**Output Format:**
```yaml
-
  subject: 'role:admin'
  resource: '*'
  action: '*'
  effect: Allow
  priority: 100
-
  subject: 'role:editor'
  resource: posts
  action: read
  effect: Allow
  priority: 1
```

**Benefits:**
- Most readable format
- Supports comments (manual editing)
- Complex nested structures
- Configuration-friendly

### XML Storage

Structured XML using Saloon XmlWrangler.

```php
use Patrol\Core\Storage\XmlPolicyRepository;

$repository = new XmlPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);

$repository->save($policy);
```

**Output Format:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<policies>
  <policy>
    <subject>role:admin</subject>
    <resource>*</resource>
    <action>*</action>
    <effect>Allow</effect>
    <priority>100</priority>
  </policy>
  <policy>
    <subject>role:editor</subject>
    <resource>posts</resource>
    <action>read</action>
    <effect>Allow</effect>
    <priority>1</priority>
  </policy>
</policies>
```

**Benefits:**
- Industry standard format
- Schema validation support
- XSLT transformation
- Enterprise integration

### TOML Storage

Configuration-friendly TOML format.

```php
use Patrol\Core\Storage\TomlPolicyRepository;

$repository = new TomlPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);

$repository->save($policy);
```

**Output Format:**
```toml
[[policies]]
subject = "role:admin"
resource = "*"
action = "*"
effect = "Allow"
priority = 100

[[policies]]
subject = "role:editor"
resource = "posts"
action = "read"
effect = "Allow"
priority = 1
```

**Benefits:**
- Type-safe configuration
- Clean syntax
- Comments support
- Popular for config files

### Serialized Storage (PHP)

Fast, native PHP serialization (not portable).

```php
use Patrol\Core\Storage\SerializedPolicyRepository;

$repository = new SerializedPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);

$repository->save($policy);
```

**Benefits:**
- Fastest file-based storage
- No parsing overhead
- PHP-native format

**Drawbacks:**
- Not human-readable
- PHP-only (not portable)
- Version fragility

## File Modes

All file-based repositories support two modes:

### Single File Mode

Store all policies in one file (`policies.[ext]`).

```php
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);

// Creates: storage/policies/policies.json
$repository->save($policy);
```

**Use when:**
- Small to medium policy sets
- Simple deployment
- Easy manual review

### Multiple File Mode

Store each policy rule in a separate file.

```php
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Multiple
);

// Creates: storage/policies/policy_0.json, policy_1.json, etc.
$repository->save($policy);
```

**Use when:**
- Large policy sets
- Granular version control
- Independent rule management

## Versioning

Enable semantic versioning for policy files:

```php
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single,
    version: '2.0.0'
);

// Creates: storage/policies/2.0.0/policies.json
$repository->save($policy);
```

**Version Management:**
```php
// Save to specific version
$repository->save($policy); // Saves to 2.0.0

// Load from version
$policies = $repository->getPoliciesFor($subject, $resource);

// Auto-detect latest
$latest = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    version: null // Finds highest version
);
```

## Storage Manager

Switch between storage drivers dynamically:

```php
use Patrol\Core\Storage\StorageManager;
use Patrol\Core\ValueObjects\StorageDriver;

$manager = new StorageManager(
    driver: StorageDriver::Json,
    config: ['path' => storage_path('policies')],
    factory: app(StorageFactoryInterface::class)
);

// Save to JSON
$manager->policy()->save($policy);

// Switch to database
$manager->driver(StorageDriver::Eloquent)
    ->policy()
    ->save($policy);

// Switch to YAML
$manager->driver(StorageDriver::Yaml)
    ->version('1.0.0')
    ->policy()
    ->save($policy);
```

## Caching with Persistence

Automatically invalidate cache when saving:

```php
use Patrol\Laravel\Repositories\CachedPolicyRepository;
use Patrol\Laravel\Repositories\DatabasePolicyRepository;

$cached = new CachedPolicyRepository(
    repository: new DatabasePolicyRepository(),
    cache: cache()->store('redis'),
    ttl: 3600
);

// Saves and flushes cache
$cached->save($policy);
```

## Real-World Examples

### Development to Production Pipeline

```php
// Development: Use JSON for easy editing
$devRepo = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    fileMode: FileMode::Single
);
$devRepo->save($policy);

// Production: Use database for performance
$prodRepo = new DatabasePolicyRepository(
    connection: 'production'
);
$prodRepo->save($policy);
```

### Multi-Tenant SaaS

```php
// Each tenant gets their own database connection
$tenant = Tenant::current();

$repository = new DatabasePolicyRepository(
    connection: "tenant_{$tenant->id}"
);

$policy = RbacPolicyBuilder::make()
    ->role('admin')->can('*')->on('*')
    ->build();

$repository->save($policy);
```

### Audit Trail with Versioning

```php
// Save to versioned JSON for audit trail
$version = now()->format('Y-m-d-H-i-s');

$auditRepo = new JsonPolicyRepository(
    basePath: storage_path('policy-audit'),
    version: $version
);

$auditRepo->save($policy);

// Also save to active database
app(DatabasePolicyRepository::class)->save($policy);
```

### Configuration Files

```php
// Export to YAML for config files
$configRepo = new YamlPolicyRepository(
    basePath: config_path('policies')
);

$policy = RbacPolicyBuilder::make()
    ->role('developer')->can(['read', 'debug'])->on('logs')
    ->build();

$configRepo->save($policy);
// Creates: config/policies/policies.yaml
```

## Best Practices

### Choose the Right Driver

- **Database**: Production, high-volume, transactional
- **JSON**: Development, version control, manual editing
- **YAML**: Configuration files, human readability
- **XML**: Enterprise integration, schema validation
- **TOML**: Type-safe configuration
- **Serialized**: Performance-critical, PHP-only

### File Organization

```php
// Good: Versioned with clear structure
storage/
  policies/
    1.0.0/
      policies.json
    2.0.0/
      policies.json

// Bad: Mixed versions, no structure
storage/
  policy1.json
  policy2.json
  old_policies.json
```

### Error Handling

```php
use Patrol\Core\Exceptions\InvalidPolicyFileFormatException;

try {
    $repository->save($policy);
} catch (InvalidPolicyFileFormatException $e) {
    Log::error('Failed to save policy', [
        'error' => $e->getMessage(),
        'policy' => $policy
    ]);
}
```

### Performance Optimization

```php
// Batch saves in single transaction (database)
DB::transaction(function () use ($policies, $repository) {
    foreach ($policies as $policy) {
        $repository->save($policy);
    }
});

// Use caching for read-heavy workloads
$cached = new CachedPolicyRepository(
    repository: $repository,
    cache: cache()->store('redis'),
    ttl: 3600
);
```

## Testing Persistence

```php
use Patrol\Laravel\Builders\RbacPolicyBuilder;

it('persists policies to database', function () {
    $policy = RbacPolicyBuilder::make()
        ->role('admin')->can('delete')->on('users')
        ->build();

    $repository = new DatabasePolicyRepository();
    $repository->save($policy);

    // Assert in database
    expect(DB::table('patrol_policies')->count())->toBe(1);
    expect(DB::table('patrol_policies')->first())
        ->subject->toBe('role:admin')
        ->action->toBe('delete');
});

it('persists policies to JSON file', function () {
    $policy = RbacPolicyBuilder::make()
        ->role('editor')->can('edit')->on('posts')
        ->build();

    $repository = new JsonPolicyRepository(
        basePath: storage_path('test-policies'),
        fileMode: FileMode::Single
    );

    $repository->save($policy);

    $file = storage_path('test-policies/policies/policies.json');
    expect($file)->toBeFile();

    $content = json_decode(file_get_contents($file), true);
    expect($content[0])
        ->toHaveKey('subject', 'role:editor')
        ->toHaveKey('action', 'edit');
});
```

## Troubleshooting

### File Permissions

```bash
# Ensure storage directory is writable
chmod -R 775 storage/policies
chown -R www-data:www-data storage/policies
```

### Version Conflicts

```php
// Clear old versions
$repository->clearVersion('1.0.0');

// Migrate between versions
$oldRepo = new JsonPolicyRepository(version: '1.0.0');
$newRepo = new JsonPolicyRepository(version: '2.0.0');

$policies = $oldRepo->getPoliciesFor($subject, $resource);
$newRepo->save($policies);
```

### Cache Invalidation

```php
// Manual cache flush after save
$repository->save($policy);
cache()->tags(['patrol:policies'])->flush();
```

## Related Documentation

- Storage Architecture (coming soon) - Detailed storage system design
- [RBAC Guide](../models/rbac.md) - Role-based policy patterns
- Testing Policies (see patterns/testing.md) - Policy testing strategies
