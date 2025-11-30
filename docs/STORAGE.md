# Storage Architecture

Comprehensive documentation for Patrol's storage system, covering drivers, persistence, versioning, and performance optimization.

## Table of Contents

- [Overview](#overview)
- [Storage Drivers](#storage-drivers)
- [Repository Interface](#repository-interface)
- [File Storage Architecture](#file-storage-architecture)
- [Database Storage](#database-storage)
- [Versioning System](#versioning-system)
- [Storage Manager](#storage-manager)
- [Caching Strategy](#caching-strategy)
- [Performance Optimization](#performance-optimization)
- [Migration Guide](#migration-guide)

## Overview

Patrol's storage system provides a unified interface for persisting and retrieving authorization policies across multiple backend storage options. The architecture follows the Repository pattern with specialized implementations for different storage mechanisms.

### Design Principles

1. **Unified Interface**: All storage drivers implement `PolicyRepositoryInterface` with consistent `getPoliciesFor()` and `save()` methods
2. **Format Flexibility**: Support for JSON, YAML, XML, TOML, CSV, INI, JSON5, serialized PHP, and database storage
3. **Versioning**: Built-in semantic versioning for policy files
4. **Mode Switching**: Runtime switching between single-file and multi-file modes
5. **Caching**: Decorator pattern for transparent caching layer

## Storage Drivers

### Available Drivers

| Driver | Format | Read/Write | Versioning | Use Case |
|--------|--------|-----------|------------|----------|
| **Eloquent** | Database | ✓/✓ | - | Production, high-volume |
| **JSON** | JSON | ✓/✓ | ✓ | Development, version control |
| **YAML** | YAML | ✓/✓ | ✓ | Configuration, readability |
| **XML** | XML | ✓/✓ | ✓ | Enterprise integration |
| **TOML** | TOML | ✓/✓ | ✓ | Type-safe configuration |
| **CSV** | CSV | ✓/✓ | ✓ | Spreadsheets, data analysis |
| **INI** | INI | ✓/✓ | ✓ | Legacy systems, zero dependencies |
| **JSON5** | JSON5 | ✓/✓ | ✓ | Human-friendly JSON with comments |
| **Serialized** | PHP | ✓/✓ | ✓ | Performance-critical |

### Driver Selection Matrix

**Choose Database when:**
- High transaction volume
- Multi-tenant architecture
- Real-time updates required
- Complex querying needed

**Choose JSON when:**
- Version control integration
- Manual policy editing
- Development/staging
- Human-readable format needed

**Choose YAML when:**
- Configuration management
- Maximum readability
- Comment support needed
- DevOps workflows

**Choose XML when:**
- Enterprise systems
- Schema validation required
- XSLT transformations
- Industry compliance

**Choose CSV when:**
- Spreadsheet integration
- Business intelligence tools
- Bulk import/export
- Non-technical user editing

**Choose INI when:**
- Legacy system integration
- Minimal dependencies required
- Simple flat configuration
- Traditional sysadmin familiarity

**Choose JSON5 when:**
- Human editing with comments
- JSON with better readability
- Team documentation in policies
- Configuration files needing annotations

## Repository Interface

All storage drivers implement this unified interface:

```php
interface PolicyRepositoryInterface
{
    /**
     * Retrieve policies matching subject-resource pair.
     */
    public function getPoliciesFor(Subject $subject, Resource $resource): Policy;

    /**
     * Persist policy to storage.
     */
    public function save(Policy $policy): void;
}
```

### Reading Policies

```php
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies')
);

$policy = $repository->getPoliciesFor($subject, $resource);
```

### Writing Policies

```php
$policy = RbacPolicyBuilder::make()
    ->role('admin')->can('*')->on('*')
    ->build();

$repository->save($policy);
```

## File Storage Architecture

File-based repositories share common infrastructure through `AbstractFilePolicyRepository`.

### Base Class Hierarchy

```
FileStorageBase (versioning, path management)
    ↓
AbstractFilePolicyRepository (load/save logic)
    ↓
JsonPolicyRepository, YamlPolicyRepository, CsvPolicyRepository, etc.
```

### File Modes

#### Single File Mode

All policies in one file for simple management.

```php
FileMode::Single

// Structure:
storage/policies/policies.json
storage/policies/1.0.0/policies.json (versioned)
```

**Advantages:**
- Simple deployment
- Easy backup
- Atomic updates
- Quick manual review

**Limitations:**
- Entire file loaded into memory
- No granular version control
- Lock contention on writes

#### Multiple File Mode

Each policy rule in separate file for granular control.

```php
FileMode::Multiple

// Structure:
storage/policies/policy_0.json
storage/policies/policy_1.json
storage/policies/1.0.0/policy_0.json (versioned)
```

**Advantages:**
- Granular version control
- Reduced memory footprint
- Parallel processing
- Independent rule management

**Limitations:**
- More files to manage
- Directory scanning overhead
- Complex backup

### Path Resolution

```php
// Without versioning
storage/policies/policies.json

// With versioning
storage/policies/1.0.0/policies.json
storage/policies/2.0.0/policies.json

// Auto-detect latest
storage/policies/2.1.0/policies.json (selected)
```

### Encoding/Decoding

Each driver implements format-specific encoding:

```php
abstract class AbstractFilePolicyRepository
{
    /**
     * Decode file contents to array.
     */
    abstract protected function decode(string $content): ?array;

    /**
     * Encode array to file contents.
     */
    abstract protected function encode(array $data): string;

    /**
     * Get file extension.
     */
    abstract protected function getExtension(): string;
}
```

**JSON Driver:**
```php
protected function encode(array $data): string
{
    return json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}
```

**YAML Driver:**
```php
protected function encode(array $data): string
{
    return Yaml::dump($data, 4, 2);
}
```

**XML Driver (Saloon XmlWrangler):**
```php
protected function encode(array $data): string
{
    $writer = new XmlWriter();
    return $writer->write('policies', ['policy' => $data]);
}
```

**CSV Driver (League CSV):**
```php
protected function encode(array $data): string
{
    $csv = Writer::createFromString();
    $csv->insertOne(['subject', 'resource', 'action', 'effect', 'priority', 'domain']);

    foreach ($data as $policy) {
        $csv->insertOne([
            $policy['subject'],
            $policy['resource'] ?? '',
            $policy['action'],
            $policy['effect'],
            isset($policy['priority']) ? (string) $policy['priority'] : '',
            $policy['domain'] ?? '',
        ]);
    }

    return $csv->toString();
}
```

**CSV Format Example:**
```csv
subject,resource,action,effect,priority,domain
user:alice,document:123,read,Allow,1,
user:bob,document:*,write,Allow,2,
admin:*,*,*,Allow,10,
user:alice,document:456,delete,Deny,5,engineering
```

**INI Driver (Native PHP):**
```php
protected function encode(array $data): string
{
    $ini = '';
    foreach ($data as $index => $policy) {
        $ini .= sprintf("[policy_%d]\n", $index);
        $ini .= sprintf("subject = \"%s\"\n", $policy['subject']);
        if (isset($policy['resource'])) {
            $ini .= sprintf("resource = \"%s\"\n", $policy['resource']);
        }
        $ini .= sprintf("action = \"%s\"\n", $policy['action']);
        $ini .= sprintf("effect = \"%s\"\n", $policy['effect']);
        if (isset($policy['priority'])) {
            $ini .= sprintf("priority = %d\n", $policy['priority']);
        }
        $ini .= "\n";
    }
    return $ini;
}
```

**INI Format Example:**
```ini
[policy_0]
subject = "user:alice"
resource = "document:123"
action = "read"
effect = "Allow"
priority = 1

[policy_1]
subject = "admin:*"
resource = "*"
action = "*"
effect = "Allow"
priority = 10
```

**JSON5 Driver (colinodell/json5):**
```php
protected function decode(string $content): ?array
{
    return Json5Decoder::decode($content, true);
}

protected function encode(array $data): string
{
    // Outputs standard JSON for compatibility
    return json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}
```

**JSON5 Format Example (input with comments):**
```json5
[
  {
    // Alice's read permissions
    subject: 'user:alice',
    resource: 'document:123',
    action: 'read',
    effect: 'Allow',
    priority: 1,
  },
  {
    // Admin wildcard - full access
    subject: 'admin:*',
    resource: '*',
    action: '*',
    effect: 'Allow',
    priority: 10,
  },
]
```

## Database Storage

### Schema

```php
Schema::create('patrol_policies', function (Blueprint $table) {
    $table->id();
    $table->string('subject');
    $table->string('resource')->nullable();
    $table->string('action');
    $table->string('effect');
    $table->integer('priority');
    $table->string('domain')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['subject', 'resource', 'action']);
    $table->index('domain');
    $table->index('priority');
});
```

### Repositories

#### DatabasePolicyRepository

Standard Eloquent-based repository with immediate persistence.

```php
$repository = new DatabasePolicyRepository(
    connection: 'default'
);

$repository->save($policy);
```

**Implementation:**
```php
public function save(Policy $policy): void
{
    foreach ($policy->rules as $rule) {
        PolicyModel::on($this->connection)->create([
            'subject' => $rule->subject,
            'resource' => $rule->resource,
            'action' => $rule->action,
            'effect' => $rule->effect,
            'priority' => $rule->priority->value,
            'domain' => $rule->domain?->id,
        ]);
    }
}
```

#### LazyPolicyRepository

Chunked processing for large policy sets.

```php
$repository = new LazyPolicyRepository(
    table: 'patrol_policies',
    connection: 'default',
    chunkSize: 100
);

$repository->save($policy);
```

**Implementation:**
```php
public function save(Policy $policy): void
{
    $data = [];

    foreach ($policy->rules as $rule) {
        $data[] = [/* mapped data */];
    }

    DB::connection($this->connection)
        ->table($this->table)
        ->insert($data);
}
```

### Multi-Tenant Support

```php
// Per-tenant connection
$repository = new DatabasePolicyRepository(
    connection: "tenant_{$tenantId}"
);

// Per-tenant schema
$repository = new LazyPolicyRepository(
    table: "tenant_{$tenantId}_policies"
);
```

## Versioning System

### Semantic Versioning

File-based repositories support semantic versioning for policy evolution.

```php
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    version: '2.0.0'
);

$repository->save($policy);
// Saves to: storage/policies/2.0.0/policies.json
```

### Version Resolution

```php
// Explicit version
version: '1.0.0' → storage/policies/1.0.0/

// Auto-detect latest
version: null → storage/policies/2.1.0/ (highest)

// Version constraints
version: '^1.0' → storage/policies/1.2.0/ (latest 1.x)
```

### Version Management

```php
// List available versions
$versions = FileStorageBase::getVersions(
    basePath: storage_path('policies'),
    type: 'policies'
);
// Returns: ['1.0.0', '1.1.0', '2.0.0']

// Bump version
$newVersion = FileStorageBase::bumpVersion(
    basePath: storage_path('policies'),
    type: 'policies',
    bumpType: 'major' // or 'minor', 'patch'
);
// Creates: 3.0.0/
```

## Storage Manager

Dynamic driver switching with unified configuration.

### Basic Usage

```php
use Patrol\Core\Storage\StorageManager;
use Patrol\Core\ValueObjects\StorageDriver;

$manager = new StorageManager(
    driver: StorageDriver::Json,
    config: [
        'path' => storage_path('policies'),
        'file_mode' => FileMode::Single,
        'version' => '1.0.0',
    ],
    factory: app(StorageFactoryInterface::class)
);
```

### Runtime Switching

```php
// Switch driver
$manager->driver(StorageDriver::Eloquent)
    ->policy()
    ->save($policy);

// Switch version
$manager->version('2.0.0')
    ->policy()
    ->save($policy);

// Switch file mode
$manager->fileMode(FileMode::Multiple)
    ->policy()
    ->save($policy);

// Chain operations
$manager
    ->driver(StorageDriver::Yaml)
    ->version('1.0.0')
    ->fileMode(FileMode::Single)
    ->policy()
    ->save($policy);
```

### Factory Pattern

```php
interface StorageFactoryInterface
{
    public function createPolicyRepository(
        StorageDriver $driver,
        array $config
    ): PolicyRepositoryInterface;
}
```

**Implementation:**
```php
final class StorageFactory implements StorageFactoryInterface
{
    public function createPolicyRepository(
        StorageDriver $driver,
        array $config,
    ): PolicyRepositoryInterface {
        return match ($driver) {
            StorageDriver::Eloquent => new DatabasePolicyRepository(
                connection: $config['connection'] ?? 'default',
            ),
            StorageDriver::Json => new JsonPolicyRepository(
                basePath: $config['path'] ?? self::getDefaultPath(),
                fileMode: $config['file_mode'] ?? FileMode::Multiple,
                version: $config['version'] ?? null,
            ),
            // ... other drivers
        };
    }
}
```

## Caching Strategy

### Decorator Pattern

```php
$cached = new CachedPolicyRepository(
    repository: new DatabasePolicyRepository(),
    cache: cache()->store('redis'),
    ttl: 3600 // 1 hour
);
```

### Cache Keys

```php
// Format: patrol:policies:{subject_id}:{resource_id}
'patrol:policies:user:123:document:456'
```

### Read Operations

```php
public function getPoliciesFor(Subject $subject, Resource $resource): Policy
{
    $key = self::getCacheKey($subject, $resource);

    return $this->cache->remember(
        $key,
        $this->ttl,
        fn() => $this->repository->getPoliciesFor($subject, $resource)
    );
}
```

### Write Operations

```php
public function save(Policy $policy): void
{
    $this->repository->save($policy);
    $this->cache->flush(); // Invalidate all caches
}
```

### Granular Invalidation

```php
// Invalidate specific subject-resource
$cached->invalidate($subject, $resource);

// Invalidate everything
$cached->invalidateAll();
```

## Performance Optimization

### Indexing Strategy

```php
// Database indexes
$table->index(['subject', 'resource', 'action']); // Compound
$table->index('domain');                          // Single
$table->index('priority');                        // Order by
```

### Query Optimization

```php
// Use chunk processing for large sets
$repository = new LazyPolicyRepository(
    chunkSize: 500 // Process 500 rules at a time
);

// Eager loading with relationships
PolicyModel::with(['domain', 'subject'])->get();
```

### File System Optimization

```php
// Reduce file I/O with caching
$repository = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    cache: true // Enable internal caching
);

// Use single file for small sets
fileMode: FileMode::Single // One read operation

// Use multiple files for large sets
fileMode: FileMode::Multiple // Parallel processing
```

### Memory Management

```php
// Chunk processing to limit memory
DB::table('patrol_policies')
    ->orderBy('id')
    ->chunk(100, function ($rules) {
        // Process in batches
    });

// Lazy collections
PolicyModel::cursor()
    ->each(function ($policy) {
        // Process one at a time
    });
```

## Migration Guide

### Database to File

```php
// Export from database
$dbRepo = new DatabasePolicyRepository();
$policies = $dbRepo->getPoliciesFor($subject, $resource);

// Import to file
$fileRepo = new JsonPolicyRepository(
    basePath: storage_path('policies')
);
$fileRepo->save($policies);
```

### File Format Migration

```php
// From JSON
$jsonRepo = new JsonPolicyRepository(
    basePath: storage_path('policies'),
    version: '1.0.0'
);
$policies = $jsonRepo->getPoliciesFor($subject, $resource);

// To YAML
$yamlRepo = new YamlPolicyRepository(
    basePath: config_path('policies'),
    version: '1.0.0'
);
$yamlRepo->save($policies);
```

### Version Migration

```php
// Load from old version
$oldRepo = new JsonPolicyRepository(
    version: '1.0.0'
);
$policies = $oldRepo->getPoliciesFor($subject, $resource);

// Save to new version
$newRepo = new JsonPolicyRepository(
    version: '2.0.0'
);
$newRepo->save($policies);
```

### Zero-Downtime Migration

```php
// 1. Dual-write phase
$oldRepo->save($policy);
$newRepo->save($policy);

// 2. Verification phase
$oldData = $oldRepo->getPoliciesFor($subject, $resource);
$newData = $newRepo->getPoliciesFor($subject, $resource);
assert($oldData->equals($newData));

// 3. Switch read traffic
config(['patrol.storage.driver' => 'new_driver']);

// 4. Stop dual-write
// (Only write to new repo)
```

## Advanced Patterns

### Hybrid Storage

```php
// Write to database, backup to file
$dbRepo = new DatabasePolicyRepository();
$fileRepo = new JsonPolicyRepository(
    basePath: storage_path('policy-backup')
);

$dbRepo->save($policy);
$fileRepo->save($policy); // Async backup
```

### Sharding

```php
// Shard by domain
$shard = match ($policy->domain) {
    'tenant-1' => new DatabasePolicyRepository('tenant_1_db'),
    'tenant-2' => new DatabasePolicyRepository('tenant_2_db'),
    default => new DatabasePolicyRepository('default'),
};

$shard->save($policy);
```

### Event Sourcing

```php
// Store policy changes as events
Event::listen(PolicySaved::class, function ($event) {
    $eventRepo = new JsonPolicyRepository(
        basePath: storage_path('policy-events'),
        version: now()->format('Y-m-d-H-i-s')
    );

    $eventRepo->save($event->policy);
});
```

## Troubleshooting

### Common Issues

**File Permission Errors:**
```bash
chmod -R 775 storage/policies
chown -R www-data:www-data storage/policies
```

**Memory Limit Exceeded:**
```php
// Use chunking
$repository = new LazyPolicyRepository(chunkSize: 100);

// Or increase memory
ini_set('memory_limit', '512M');
```

**Cache Inconsistency:**
```php
// Force cache refresh
$cached->invalidateAll();
$cached->getPoliciesFor($subject, $resource);
```

**Version Not Found:**
```php
// Fallback to latest
$repository = new JsonPolicyRepository(
    version: $version ?? null // null = auto-detect
);
```

## API Reference

See individual classes for detailed API documentation:

- `PolicyRepositoryInterface` - Base contract
- `AbstractFilePolicyRepository` - File storage base
- `DatabasePolicyRepository` - Eloquent storage
- `CachedPolicyRepository` - Caching decorator
- `StorageManager` - Driver manager
- `StorageFactory` - Repository factory

## Related Documentation

- [Persisting Policies Guide](../cookbook/guides/PERSISTING-POLICIES.md)
- [Configuration](./CONFIGURATION.md)
- [Testing Strategies](./TESTING.md)
