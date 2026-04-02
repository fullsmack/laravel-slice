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
├── config/                 # Auto-registered as slice-name::
├── resources/views/        # Referenced as slice-name::view.name
├── lang/                   # Referenced as slice-name::file.key
├── routes/                 # Route definitions
├── database/migrations/    # Slice migrations
└── src/                    # PSR-4 classes
```

**Namespace Pattern**: All slice resources use `slice-name::` prefix:
- Config: `config('pizza::settings.key')`
- Translations: `trans('pizza::messages.welcome')`
- Views: `view('pizza::emails.receipt')`

## Naming and Organization

### Nested Slices
Slices can be organized in subfolders for logical grouping (e.g., grouping UI slices):
```bash
php artisan make:slice posts --dir=api
# Creates: src/api/posts/
# Slice name: api.posts (auto-generated with dot notation)
```

Nested slices use **dot notation** for their names by default, making them distinct from path separators and compatible with Laravel's naming conventions.

### Slice Names
- **Must be unique** across the entire project
- **Can be set to any string** in the service provider's `configure()` method
- **Auto-generated** from filesystem path for nested slices (e.g., `api/posts` → `api.posts`)
- **Independent from filesystem location** - you can name a slice anything regardless of where it lives

```php
// Manually override the auto-generated name
public function configure(Slice $slice): void
{
    $slice->setName('custom-name'); // Any unique name
}
```

### PSR-4 Namespaces vs Slice Names
The **slice name** (used for Laravel services) is **completely independent** from the **PSR-4 namespace** (used for PHP class autoloading):

- **Slice name**: Used for `config('blog::...')`, `view('blog::...')`, CLI commands
- **PSR-4 namespace**: Derived from filesystem location and config settings (e.g., `Slice\Api\Posts`) when created but on the namespace of the location of each slice's service provider after the slice is configured.

They don't need to match - a slice at `src/api/posts/` with namespace `Slice\Api\Posts` can be named `news` or any other unique identifier.

## Available Commands

### Create a Slice
```bash
php artisan make:slice {sliceName} [--dir=subdirectory]
```
Creates full slice structure with service provider and auto-registers in `composer.json`.

### Create Slice Migration
```bash
php artisan make:migration {name} --slice={sliceName} [--create=table] [--table=table] [--dir=subdirectory]
```
Generates migration in slice's `database/migrations/` directory with connection support.

### Run Slice Migrations
```bash
php artisan migrate --slice={sliceName} [--dir=subdirectory]
```
Executes migrations for a specific slice. Automatically uses slice connection if configured.

### Create Slice Component
```bash
php artisan make:component {name} --slice={sliceName} [--dir=subdirectory]
```
Creates view component in slice namespace with proper view path resolution.

### Create Slice Test
```bash
php artisan make:test {name} --slice={sliceName} [--unit] [--pest] [--dir=subdirectory]
```
Scaffolds test in slice's `tests/` directory with correct namespace.

## Slice Configuration Patterns

### Minimal Configuration
```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class OrderServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('order')
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
    $slice->setName('order')
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
    $slice->setName('cookbook')
        ->useMigrations()
        ->withConnection('cookbook');
}
```

### With Connection and Model Binding
```php
public function configure(Slice $slice): void
{
    $slice->setName('cookbook')
        ->useMigrations()
        ->withConnection('cookbook', [
            \Slice\Cookbook\Models\Recipe::class,
            \Slice\Cookbook\Models\Ingredient::class,
        ]);
}
```

### With Custom Extensions
```php
public function configure(Slice $slice): void
{
    $slice->setName('order')
        ->useRoutes()
        ->withExtension(new CustomExtension())
        ->withExtension(new AnotherExtension());
}
```

## Database Connections

**Connection Resolution Order**:
1. Explicit argument: `withConnection('connection-name')`
2. Slice config: Value from `{sliceName}::database.default` when `withConnection()` called without argument
3. Application default when `withConnection()` not used

**Models**: Use `UsesConnection` trait for automatic connection binding:
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

Use `RefreshSliceDatabase` trait for slice-specific test database handling:

```php
use FullSmack\LaravelSlice\Testing\RefreshSliceDatabase;

final class RecipeTest extends TestCase
{
    use RefreshSliceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshSlice('pizza');
    }
}
```

## Quick Reference

| Task | Command | Notes |
|------|---------|-------|
| Create slice | `make:slice pizza` | Scaffolds structure + service provider |
| Slice migration | `make:migration create_orders --slice=pizza` | Uses slice path |
| Run slice migrations | `migrate --slice=pizza` | Uses slice connection if set |
| Slice component | `make:component Button --slice=pizza` | View: `pizza::components.button` |
| Slice test | `make:test OrderTest --slice=pizza` | Created in slice tests/ |
| Nested slice | `make:slice posts --dir=api` | Creates in `src/api/posts/` |

## Configuration Options

Published config (`config/laravel-slice.php`):
- `root.folder`: Where slices are stored (default: `'src'`)
- `root.namespace`: Root namespace for slices (default: `'slice'`)
- `discovery.type`: Provider discovery method (default: `'composer'`)

Publish config:
```bash
php artisan vendor:publish --provider="FullSmack\LaravelSlice\LaravelSliceServiceProvider" --tag="config"
```

## Key Reminders

- Config files in `slice/config/` are auto-registered; no manual registration needed
- Always use `slice-name::` prefix for all resource access
- Slice-specific connections require `UsesConnection` trait on models
- The `--dir` option creates nested slices (e.g., `src/api/posts/`)
- Service providers are auto-registered in `composer.json` by default
