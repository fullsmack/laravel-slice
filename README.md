# Laravel Slice

Modular architecture for Laravel applications — organize your app into standalone "slices" that keep related code together (routes, views, translations, migrations, commands, config).

It can be used to structure modules, vertical slices, horizontal UI-slices, and other standalone or portable features.

**Overview**
- Purpose: Provide a lightweight convention to compose self-contained modules (slices) with a consistent namespace and resource resolution.
- Main idea: each slice registers a namespace `slice-identifier::` used for config, translations and views so everything related to the slice stays with the module.

**Requirements**

PHP 8.2+ and Laravel 11, 12 or 13 (Laravel 13 requires PHP 8.3+).

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
php artisan vendor:publish --tag=laravel-slice
```

This creates `config/laravel-slice.php` where you can customize:
- `root.folder`: The folder name where slices are stored (default: `'src'`)
- `root.namespace`: The root namespace for slices (default: `'slice'`)
- `root.namespace-mode`: `'prefix'` always prepends the root namespace (`Slice\Api\Posts`); `'fallback'` only uses it when the slice is not nested (`Api\Posts`) (default: `'prefix'`)
- `test.namespace`: The root namespace for slice tests (default: `'test'`, giving `Test\Api\Posts`)
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
To disable auto-discovery, set `discovery.type` to any other value (e.g., `'manual'`). Then register slice service providers yourself in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    Slice\Pizza\PizzaServiceProvider::class,
    Slice\Order\OrderServiceProvider::class,
];
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
        $slice->setIdentifier('pizza');
    }
}
```

Slices can be nested in subfolders. The identifier then uses dot notation:

```bash
php artisan make:slice api/posts     # or: make:slice posts --dir=api
# Creates src/api/posts/ with identifier 'api.posts' and namespace Slice\Api\Posts
```

**Slice Structure**

Slices are organized within your configured root folder (default: `src/`). Typical slice layout:

```
src/your-slice/
├── config/                 # slice config files (auto-registered under `slice-identifier::`)
├── resources/views/        # blade views, referenced as `slice-identifier::view.name`
├── lang/                   # translation files, referenced as `slice-identifier::file.key`
├── routes/                 # slice route definitions
├── database/migrations/    # slice-specific migrations
├── tests/                  # slice tests
└── src/                    # PSR-4 classes for the slice
```

**Configuring a Slice**

- `Slice` is the configuration object you receive in `configure(Slice $slice)`.
- Common fluent methods: `setIdentifier()`, `useViews()`, `useTranslations()`, `useMigrations()`, `useRoutes()`, `useConnection()`, `withCommands()`, `withExtension()`.

Short example (minimal):

```php
public function configure(Slice $slice): void
{
    $slice->setIdentifier('order')
        ->useRoutes()          // Load routes/*.php
        ->useViews()           // Load views from resources/views
        ->useTranslations()    // Load translations from lang/ (or resources/lang/)
        ->useMigrations()      // Load migrations from database/migrations
        ->withCommands([       // Register command classes
            \Slice\Order\Console\SyncOrders::class,
        ]);
}
```

Each `use*()` method expects its directory to exist and throws `SliceMisconfigured` otherwise. Only `.php` files directly inside `config/` and `routes/` are loaded. Route files are loaded as-is, so add middleware groups (e.g. `Route::middleware('web')->group(...)`) inside them.

Lifecycle hooks can be overridden in the service provider: `registeringSlice()`, `sliceRegistered()`, `bootingSlice()` and `sliceBooted()`.

**Custom Extensions**

Extensions provide an extensibility mechanism for slices, allowing you to add custom functionality that integrates with the slice lifecycle. Extensions can be implemented by other packages or created directly in your application.

### Creating an Extension

Implement the `Extension` interface:

```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Extension;

final class CustomExtension implements Extension
{
    public function register(Slice $slice): void
    {
        // Add custom functionality here
        // Access to slice configuration, paths, etc.

        // Example: Register Livewire or other frontend assets in your slice
    }
}
```

### Registering Extensions

Add extensions to your slice configuration:

```php
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class OrderServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setIdentifier('order')
            ->withExtension(new CustomExtension())
            ->withExtension(new AnotherExtension());
    }
}
```

Extensions are registered when the slice boots.

**Namespacing & Resources**

- Every slice registers a namespace `slice-identifier::` automatically. Use this namespace for config, translations and views:

```php
config('pizza::settings.default-timezone');
trans('pizza::messages.order-created');
view('pizza::emails.receipt');
```

- Config registration is automatic: you do not need to explicitly register slice config files inside `configure()` in order for `slice-identifier::` config resolution to work.

**Commands & Scaffolding**

| Command | Description |
|---------|-------------|
| `make:slice {identifier} [--dir=]` | Scaffold a new slice and register it in `composer.json` |
| `make:migration {name} --slice=` | Generate a migration in the slice's `database/migrations` |
| `make:component {name} --slice=` | Create a view component (class in `src/`, view as `slice::components.name`) |
| `make:test {name} --slice=` | Create a test in the slice's `tests/` directory |
| `migrate --slice=` | Run the migrations of one slice (on its connection, if it has one) |
| `migrate --all-slices` | Run the default migrations, then the migrations of every slice with its own connection |
| `slice:list-migrations` | List slices with their connections and migration paths |
| `slice:test {identifier}` | Run the tests of one slice (requires `nunomaduro/collision`) |

The `make:*` and `migrate` commands behave exactly like Laravel's own when `--slice` is not passed.

Common usage examples:

```bash
php artisan make:slice pizza
php artisan make:migration create_recipes_table --create=recipes --slice=pizza
php artisan migrate --slice=pizza
php artisan slice:test pizza --filter=RecipeTest
```

**Migrations & Connections**

- Slices can optionally use a dedicated database connection. The slice works with the app default connection when no slice connection is configured.
- `useConnection()` on `Slice` controls connection resolution. Resolution order:
  1. Explicit argument passed to `useConnection('name')`
  2. Config value from `{identifier}::database.default` when `useConnection()` is called without an argument
  3. Application default connection when `useConnection()` is not used
- Migrations of slices **without** their own connection are loaded into Laravel's regular `migrate`. Migrations of slices **with** their own connection only run via `migrate --slice=…` or `migrate --all-slices`.
- `make:migration --slice` writes `protected $connection = '…'` into the migration only when the slice passes an explicit name to `useConnection('name')`. Config-resolved connections are passed via `--database` at migrate time, so they can differ per environment.
- `UsesConnection` trait: models can opt in to be bound to slice connections.
- `SliceMigration` trait: migrations generated for slices use it, and its `schema()` helper targets the slice connection when present.

Connection example:

```php
    $slice->setIdentifier('cookbook')
        ->useMigrations()
        ->useConnection('cookbook');
```

If you want to bind the models to the connection at the slice configuration level:

```php
    $slice->setIdentifier('cookbook')
        ->useMigrations()
        ->useConnection('cookbook', [
            \Slice\Cookbook\Models\Recipe::class,
            \Slice\Cookbook\Models\Ingredient::class
        ]);
```

Every bound model must use the `UsesConnection` trait, otherwise booting the slice throws `SliceMisconfigured`.

**Testing**

Use the `RefreshAllDatabases` trait in place of Laravel's `RefreshDatabase`. It refreshes the default database as usual, migrates every slice that has its own connection, and wraps each test in a transaction on all of them:

```php
use FullSmack\LaravelSlice\Testing\RefreshAllDatabases;

final class RecipeTest extends TestCase
{
    use RefreshAllDatabases;

    // Optional: only refresh these slices (plus the default database)
    protected string|array|null $refreshSlice = 'cookbook';
}
```

- Slice migrations run once per test run; in-memory SQLite slice connections are kept alive between tests.
- Slices whose connection is the default test connection are migrated together with the default database.
- The trait adds slice paths to `migrateFreshUsing()`. If you override that method, merge in `$this->defaultConnectionSliceMigrationOptions()`.

Run a single slice's tests with `php artisan slice:test cookbook`.

**Exceptions**

All package exceptions implement `FullSmack\LaravelSlice\SliceException`:
- `SliceNotRegistered`: the slice is not in the registry, or has no identifier.
- `SliceMisconfigured`: a directory enabled by a `use*()` method is missing, the slice connection config is missing, or a bound model lacks the `UsesConnection` trait.

**Gotchas & Notes**

- The namespace `slice-identifier::` is the canonical way to reference a slice's resources (config, views, translations).
- Config files placed under a slice's `config/` directory are auto-registered and available via `config('slice-identifier::file.key')` — you don't need to call a registration helper in `configure()` to make them available.
- Models with a dedicated connection must use the `UsesConnection` trait for automatic connection binding via `useConnection()`.

**Upgrading**

See [CHANGELOG.md](CHANGELOG.md) for breaking changes between versions.

**Contributing**

- Run `composer test` and `composer phpstan`.
- See tests under [tests/](tests/) for examples of package integration and behavior.

**License**

MIT. See [LICENSE](LICENSE).
