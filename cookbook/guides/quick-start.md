# Quick Start

This guide will get you up and running with Patrol in minutes.

**💡 Migrating from Spatie Laravel Permission?** See the [Complete Migration Guide](./migrating-from-spatie.md) for automated migration.

---

## 1. Configure Resolvers

In your `AppServiceProvider` or `config/patrol.php`:

```php
use Patrol\Laravel\Patrol;

// Register subject resolver (current user)
Patrol::resolveSubject(fn() => auth()->user());

// Register tenant resolver (for multi-tenancy)
Patrol::resolveTenant(fn() => auth()->user()?->currentTenant);

// Register resource resolver
Patrol::resolveResource(fn($id) => Document::find($id));
```

## 2. Use Patrol in Your Application

### On Eloquent Models

```php
use Patrol\Laravel\Concerns\HasPatrolAuthorization;

class User extends Authenticatable
{
    use HasPatrolAuthorization;
}

// Check permissions
if ($user->can('edit', $post)) {
    // User can edit the post
}

// Throw on deny
$user->authorize('delete', $document);

// Check multiple permissions
if ($user->canAny(['read', 'edit'], $post)) {
    // User can read OR edit
}
```

### In Controllers

```php
// Using Laravel's native authorization (automatically uses Patrol via Gate::before)
public function update(Request $request, Post $post)
{
    $this->authorize('edit', $post); // Throws 403 if denied

    // Or check without throwing
    if ($request->user()->can('publish', $post)) {
        $post->publish();
    }
}

// Using validation rules
$request->validate([
    'action' => ['required', new CanPerformAction($post, 'edit')],
]);
```

### In Blade Templates

Patrol integrates with Laravel's Gate, so use standard `@can` directives:

```blade
@can('edit', $post)
    <button>Edit Post</button>
@endcan

@cannot('delete', $post)
    <p class="text-muted">You cannot delete this post</p>
@endcannot
```

**How it works:** Patrol registers a `Gate::before()` callback, so all Laravel authorization features work automatically.

### Route Middleware

```php
Route::middleware('patrol:posts,edit')->get('/posts/{post}/edit', ...);
```

## Next Steps

- **[Choose Your Authorization Model](quick-reference.md)** - Pick the right model for your use case
- **[Complete Cookbook](../README.md)** - Comprehensive guides for all models
- **[API Reference](api-reference.md)** - Complete API documentation
- **[Policy Builders](policy-builders.md)** - Fluent APIs for building policies
