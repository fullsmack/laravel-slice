# Laravel Slice

Modular architecture for Laravel applications — organize your app into standalone "slices" that keep related code together (routes, views, translations, migrations, commands, config).

It can be used to structure modules, vertical slices, horizontal UI-slices, and other standalone or portable features.

**Overview**
- Purpose: Provide a lightweight convention to compose self-contained modules (slices) with a consistent namespace and resource resolution.
- Main idea: each slice registers a namespace `slice-name::` used for config, translations and views so everything related to the slice stays with the module.

**Installation**

```bash
composer require fullsmack/laravel-slice
```

**Quick Start**

Make a slice by running the following command:
```bash
php artisan make:slice pizza
```

This scaffolds a slice with the full directory structure and adds a service provider to configure the slice. You need to make your own configurations in the service provider's `configure()` method:

```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class PizzaServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('pizza')
              ->useRoutes()
              ->useViews()
              ->useMigrations();
    }
}
```

**Slice Anatomy**

Typical slice layout inside a module:

- `config/` — slice config files (auto-registered under `slice-name::`)
- `resources/views/` — blade views, referenced as `slice-name::view.name`
- `lang/` — translation files, referenced as `slice-name::file.key`
- `routes/` — slice route definitions
- `database/migrations/` — slice-specific migrations
- `src/` — PSR-4 classes for the slice

**Configuring a Slice**

- `Slice` is the configuration object you receive in `configure(Slice $slice)`.
- Common fluent methods: `setName()`, `useViews()`, `useTranslations()`, `useMigrations()`, `useRoutes()`, `withCommands()`, `withFeature()`, `useConnection()`.

Short example (minimal):

```php
public function configure(Slice $slice): void
{
    $slice->setName('orders')
        ->useRoutes()
        ->useViews()
        ->withCommands([
            \Module\Orders\Console\SyncOrders::class,
        ]);
}
```

**Namespacing & Resources**

- Every slice registers a namespace `slice-name::` automatically. Use this namespace for config, translations and views:

```php
config('pizza::settings.default-timezone');
trans('pizza::messages.order-created');
view('pizza::emails.receipt');
```

- Config registration is automatic: you do not need to explicitly register slice config files inside `configure()` in order for `slice-name::` config resolution to work.

**Commands & Scaffolding**

Available scaffold and slice commands (located under `src/Command`):

- `MakeSlice` — scaffold a new slice
- `MakeComponent` — create a UI component inside a slice
- `MakeMigration` — generate a slice migration (`--slice=NAME` flag)
- `MakeTest` — scaffold slice tests
- `MigrateSlice` — run migrations for a specific slice
- `SliceDefinitions` — helpers for slice path/namespace logic in commands

Common usage examples:

```bash
php artisan make:slice pizza
php artisan make:migration create_recipes_table --create=recipes --slice=pizza
php artisan migrate --slice=pizza
```

**Migrations & Connections (secondary)**

- Slices can optionally use a dedicated database connection. The slice works with the app default connection when no slice connection is configured.
- `useConnection()` on `Slice` controls connection resolution. Resolution order:
  1. Explicit argument passed to `useConnection('name')`
 2. Config value from `{sliceName}::database.default` when `useConnection()` is called without an argument
 3. Application default connection when `useConnection()` is not used
- `UsesConnection` trait: models can opt in to be bound to slice connections.
- `SliceMigration` trait: migrations generated for slices will use the slice connection when present and provide a `schema()` helper bound to that connection.

Short example (connection):

```php
$slice->setName('cookbook')
      ->useMigrations()
      ->useConnection('cookbook');
```

**Testing**

- Use `RefreshSliceDatabase` (testing helper) to run and refresh migrations for slice-specific connections and to wrap tests in transactions. Typical usage:

```php
use FullSmack\LaravelSlice\Testing\RefreshSliceDatabase;

class RecipeTest extends TestCase
{
    use RefreshSliceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshSlice('pizza');
    }
}
```

**Gotchas & Notes**

- The namespace `slice-name::` is the canonical way to reference a slice's resources (config, views, translations).
- Config files placed under a slice's `config/` directory are auto-registered and available via `config('slice::key')` — you don't need to call a registration helper in `configure()` to make them available.
- Models with a dedicated connection must use `UsesConnection` for automatic connection binding via `bindModelsToConnection()`.

**Contributing**

- See tests under [tests/](tests/) for examples of package integration and behavior.
