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

**Project Structure**

Laravel Slice expects your application to be organized with slices in a dedicated folder (by default `src/`). This works alongside Laravel's default `app/` folder:

```
your-project/
├── app/                    # Default Laravel application code
├── src/                    # Slice modules (configurable)
│   ├── pizza/
│   │   ├── config/
│   │   ├── src/
│   │   ├── routes/
│   │   └── ...
│   └── order/
│       └── ...
├── config/
├── database/
└── ...
```

**Configuration**

The package can be configured by publishing the config file:

```bash
php artisan vendor:publish --provider="FullSmack\LaravelSlice\LaravelSliceServiceProvider" --tag="config"
```

This creates `config/laravel-slice.php` where you can customize:
- `root.folder`: The folder name where slices are stored (default: `'src'`)
- `root.namespace`: The root namespace for slices (default: `'slice'`)
- `discovery.type`: Service provider discovery method (default: `'composer'`)

**Service Provider Registration**

Slices can be auto-discovered or manually registered:

### Auto-Discovery (Default)
When `discovery.type` is set to `'composer'` (default), slice service providers are automatically registered in your `composer.json`:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Slice\\Pizza\\PizzaServiceProvider",
                "Slice\\Order\\OrderServiceProvider"
            ]
        }
    }
}
```

The `make:slice` command automatically adds new slices to this list.

### Manual Registration
To disable auto-discovery, set `discovery.type` to any other value (e.g., `'manual'`). Then register slice service providers manually in your `config/app.php`:

```php
'providers' => [
    // ... other providers
    \Slice\Pizza\PizzaServiceProvider::class,
    \Slice\Order\OrderServiceProvider::class,
],
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
        $slice->setName('pizza');
    }
}
```

**Slice Structure**

Slices are organized within your configured root folder (default: `src/`). Typical slice layout:

```
src/your-slice-name/
├── config/                 # slice config files (auto-registered under `slice-name::`)
├── resources/views/        # blade views, referenced as `slice-name::view.name`
├── lang/                   # translation files, referenced as `slice-name::file.key`
├── routes/                 # slice route definitions
├── database/migrations/    # slice-specific migrations
└── src/                    # PSR-4 classes for the slice
```

**Configuring a Slice**

- `Slice` is the configuration object you receive in `configure(Slice $slice)`.
- Common fluent methods: `setName()`, `useViews()`, `useTranslations()`, `useMigrations()`, `useRoutes()`, `withCommands()`, `withFeature()`, `useConnection()`.

Short example (minimal):

```php
public function configure(Slice $slice): void
{
    $slice->setName('order')
        ->useRoutes()          // Load routes from routes/
        ->useViews()           // Load views from resources/views
        ->useTranslations()    // Load translations from lang/
        ->useMigrations()      // Load migrations from database/migrations
        ->withCommands([       // Register command classes
            \Slice\Order\Console\SyncOrders::class,
        ])
}
```

**Custom Features**

Features provide an extensibility mechanism for slices, allowing you to add custom functionality that integrates with the slice lifecycle. Features can be implemented by other packages or created directly in your application.

### Creating a Feature

Implement the `Feature` interface:

```php
use FullSmack\LaravelSlice\Feature;
use FullSmack\LaravelSlice\Slice;

class CustomFeature implements Feature
{
    public function register(Slice $slice): void
    {
        // Add custom functionality here
        // Access to slice configuration, paths, etc.

        // Example: Register Livewire or other frontend assets in your slice
    }
}
```

### Registering Features

Add features to your slice configuration:

```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class OrderServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('order')
            ->withFeature(new CustomFeature())
            ->withFeature(new AnotherFeature());
    }
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

**Migrations & Connections**

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
        ->useConnection('cookbook')
        ->bindModelsToConnection([
            Recipe::class,
            Delivery::class,
        ]);
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
- Models with a dedicated connection must use `UsesConnection` trait for automatic connection binding via `bindModelsToConnection()`.

**Contributing**

- See tests under [tests/](tests/) for examples of package integration and behavior.
