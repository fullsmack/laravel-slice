---
name: laravel-slice-usage
description: Guide for using the Laravel Slice package to create modular applications. Use when creating slices, migrations, components, or tests in projects using Laravel Slice, or when users mention modular Laravel architecture, vertical slices, or the fullsmack/laravel-slice package.
---

# Laravel Slice Usage

## Overview

Laravel Slice provides modular architecture for Laravel applications by organizing code into standalone "slices" with their own routes, views, translations, migrations, and commands. Each slice uses a namespaced identifier (e.g., `pizza::`) for resource resolution.

## When to Use This Skill

- Creating or scaffolding new slices
- Adding migrations, components, or tests to slices
- Configuring slice service providers
- Working with slice-specific database connections
- Organizing modular Laravel applications

## Core Concepts

**Slice Structure**: Each slice lives in the configured root folder (default: `src/`) with this layout:
```
src/slice-name/
├── config/                 # Auto-registered as slice-identifier::
├── resources/views/        # Referenced as slice-identifier::view.name
├── lang/                   # Referenced as slice-identifier::file.key
├── routes/                 # Route definitions (*.php)
├── database/migrations/    # Slice migrations
├── tests/                  # Slice tests
└── src/                    # PSR-4 classes
```

**Namespace Pattern**: All slice resources use the `slice-identifier::` prefix:
- Config: `config('pizza::settings.key')`
- Translations: `trans('pizza::messages.welcome')`
- Views: `view('pizza::emails.receipt')`

## Naming and Organization

### Nested Slices
Slices can be organized in subfolders for logical grouping (e.g., grouping UI slices):
```bash
php artisan make:slice api/posts
# or: php artisan make:slice posts --dir=api
# Creates: src/api/posts/
# Slice identifier: api.posts (auto-generated with dot notation)
```

Nested slices use **dot notation** for their identifiers, making them distinct from path separators and compatible with Laravel's naming conventions.

### Slice Identifiers
- **Must be unique** across the entire project
- **Can be set to any string** in the service provider's `configure()` method
- **Auto-generated** from filesystem path for nested slices (e.g., `api/posts` → `api.posts`)
- **Independent from filesystem location** - you can identify a slice by anything regardless of where it lives

```php
// Manually override the auto-generated identifier
public function configure(Slice $slice): void
{
    $slice->setIdentifier('custom-identifier'); // Any unique identifier
}
```

### PSR-4 Namespaces vs Slice Identifiers
The **slice identifier** (used for Laravel services) is **completely independent** from the **PSR-4 namespace** (used for PHP class autoloading):

- **Slice identifier**: Used for `config('blog::...')`, `view('blog::...')`, CLI commands
- **PSR-4 namespace**: Derived from filesystem location and config settings (e.g., `Slice\Api\Posts`) when created, but taken from the namespace of each slice's service provider after the slice is configured.

They don't need to match - a slice at `src/api/posts/` with namespace `Slice\Api\Posts` can be identified as `news` or any other unique identifier.

## Available Commands

### Create a Slice
```bash
php artisan make:slice {identifier} [--dir=subdirectory]
```
Creates full slice structure with service provider and auto-registers in `composer.json`.

### Create Slice Migration
```bash
php artisan make:migration {name} --slice={identifier} [--create=table] [--table=table]
```
Generates migration in slice's `database/migrations/` directory using the `SliceMigration` trait.

### Run Slice Migrations
```bash
php artisan migrate --slice={identifier}
php artisan migrate --all-slices
```
`--slice` executes migrations for one slice, using the slice connection if configured. `--all-slices` runs the default migrations and then every slice with its own connection.

### Create Slice Component
```bash
php artisan make:component {name} --slice={identifier}
```
Creates view component in slice namespace with proper view path resolution.

### Create Slice Test
```bash
php artisan make:test {name} --slice={identifier} [--unit] [--pest]
```
Scaffolds test in slice's `tests/` directory with correct namespace.

### Other
```bash
php artisan slice:list-migrations     # Slices, connections and migration paths
php artisan slice:test {identifier}   # Run one slice's tests (needs nunomaduro/collision)
```

## Slice Configuration Patterns

### Minimal Configuration
```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class OrderServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setIdentifier('order')
            ->useRoutes()
            ->useViews()
            ->useTranslations()
            ->useMigrations();
    }
}
```

### With Commands
```php
public function configure(Slice $slice): void
{
    $slice->setIdentifier('order')
        ->useRoutes()
        ->withCommands([
            \Slice\Order\Console\SyncOrders::class,
        ]);
}
```

### With Database Connection
```php
public function configure(Slice $slice): void
{
    $slice->setIdentifier('cookbook')
        ->useMigrations()
        ->useConnection('cookbook');
}
```

### With Connection and Model Binding
```php
public function configure(Slice $slice): void
{
    $slice->setIdentifier('cookbook')
        ->useMigrations()
        ->useConnection('cookbook', [
            \Slice\Cookbook\Models\Recipe::class,
            \Slice\Cookbook\Models\Ingredient::class,
        ]);
}
```

### With Custom Extensions
```php
public function configure(Slice $slice): void
{
    $slice->setIdentifier('order')
        ->useRoutes()
        ->withExtension(new CustomExtension())
        ->withExtension(new AnotherExtension());
}
```

## Database Connections

**Connection Resolution Order**:
1. Explicit argument: `useConnection('connection-name')`
2. Slice config: Value from `{identifier}::database.default` when `useConnection()` called without argument
3. Application default when `useConnection()` not used

**Models**: Bound models must use the `UsesConnection` trait (otherwise booting throws `SliceMisconfigured`):
```php
use FullSmack\LaravelSlice\Database\UsesConnection;

class Recipe extends Model
{
    use UsesConnection;
}
```

**Migrations**: Use `SliceMigration` trait for connection-aware migrations:
```php
use FullSmack\LaravelSlice\Database\SliceMigration;

return new class extends Migration
{
    use SliceMigration;

    public function up(): void
    {
        $this->schema()->create('recipes', function (Blueprint $table) {
            $table->id();
        });
    }
};
```

## Testing Slices

Use `RefreshAllDatabases` in place of Laravel's `RefreshDatabase`. It refreshes the default database and every slice connection, and wraps each test in transactions:

```php
use FullSmack\LaravelSlice\Testing\RefreshAllDatabases;

final class RecipeTest extends TestCase
{
    use RefreshAllDatabases;

    // Optional: only refresh these slices (plus the default database)
    protected string|array|null $refreshSlice = 'cookbook';
}
```

## Quick Reference

| Task | Command | Notes |
|------|---------|-------|
| Create slice | `make:slice pizza` | Scaffolds structure + service provider |
| Slice migration | `make:migration create_orders --slice=pizza` | Uses slice path |
| Run slice migrations | `migrate --slice=pizza` | Uses slice connection if set |
| Run all slice migrations | `migrate --all-slices` | Default + every slice connection |
| Slice component | `make:component Button --slice=pizza` | View: `pizza::components.button` |
| Slice test | `make:test OrderTest --slice=pizza` | Created in slice tests/ |
| Nested slice | `make:slice api/posts` | Creates in `src/api/posts/`, identifier `api.posts` |

## Configuration Options

Published config (`config/laravel-slice.php`):
- `root.folder`: Where slices are stored (default: `'src'`)
- `root.namespace`: Root namespace for slices (default: `'slice'`)
- `root.namespace-mode`: `'prefix'` or `'fallback'` (default: `'prefix'`)
- `test.namespace`: Root namespace for slice tests (default: `'test'`)
- `discovery.type`: Provider discovery method (default: `'composer'`)

Publish config:
```bash
php artisan vendor:publish --tag=laravel-slice
```

## Key Reminders

- Config files in `slice/config/` are auto-registered; no manual registration needed
- Always use the `slice-identifier::` prefix for all resource access
- Slice-specific connections require `UsesConnection` trait on bound models
- Nested slices are created with `make:slice api/posts` or `--dir=api` (only `make:slice` has `--dir`)
- Service providers are auto-registered in `composer.json` by default
