# laravel-slice

A Laravel package for organizing your application into modular slices — each with their own routes, views, migrations, and optionally separate database connections.

## Installation

```bash
composer require fullsmack/laravel-slice
```

## Creating a Slice

Create a service provider that extends `SliceServiceProvider`:

```php
<?php

namespace Module\Pizza;

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

## Database Connections

Slices can use a separate database connection for their models and migrations.

### Configuring a Slice Connection

Enable a separate connection when configuring your slice:

```php
public function configure(Slice $slice): void
{
    $slice->setName('pizza')
        ->useMigrations()
        ->useConnection('cookbook'); // Explicit connection name
}
```

Or let the connection be resolved from config:

```php
public function configure(Slice $slice): void
{
    $slice->setName('pizza')
          ->useMigrations()
          ->useConnection(); // Reads from 'pizza::database.default' config
}
```

**Connection resolution order:**
1. Explicit connection passed to `useConnection('connection-name')`
2. Config value from `{sliceName}::database.default` (if `useConnection()` was called without a value)
3. App default connection (if `useConnection()` was NOT called)

### Binding Models to a Connection

Models can define their connection directly:

```php
use FullSmack\LaravelSlice\Database\UsesConnection;

class Recipe extends Model
{
    use UsesConnection;

    protected $connection = 'cookbook';
}
```

Or bind multiple models to the slice's connection in your service provider:

```php
use Module\Pizza\Models\Recipe;
use Module\Pizza\Models\Ingredient;

public function sliceBooted(): void
{
    $this->bindModelsToConnection(
        Recipe::class,
        Ingredient::class,
    );
}
```

Models must use the `UsesConnection` trait for `bindModelsToConnection()` to work.

### Writing Migrations

Create a migration for a slice:

```bash
php artisan make:migration create_recipes_table --create=recipes --slice=pizza
```

This generates a migration in the slice's `database/migrations` directory with the `SliceMigration` trait. If the slice uses a connection, the `$connection` property is automatically added:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use FullSmack\LaravelSlice\Database\SliceMigration;

return new class extends Migration
{
    use SliceMigration;

    protected $connection = 'cookbook';

    public function up(): void
    {
        $this->schema()->create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('baking_minutes')->default(12);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('recipes');
    }
};
```

For slices without a connection, the migration uses the app default:

```php
return new class extends Migration
{
    use SliceMigration;

    public function up(): void
    {
        $this->schema()->create('recipes', function (Blueprint $table) {
            // ...
        });
    }
};
```

The `schema()` method returns a schema builder for the migration's connection (or default if none).

### Running Slice Migrations

Run migrations for a specific slice:

```bash
php artisan migrate --slice=pizza
```

This automatically uses the slice's configured connection and migration path.

## Testing

### Refreshing Slice Databases in Tests

Use the `RefreshSliceDatabase` trait to refresh slice-specific database connections in your tests:

```php
use FullSmack\LaravelSlice\Testing\RefreshSliceDatabase;

class PizzaTest extends TestCase
{
    use RefreshSliceDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshSlice('pizza');
    }

    public function it_can_create_a_margherita_recipe(): void
    {
        $recipe = Recipe::create([
            'name' => 'Margherita',
            'description' => 'Fresh tomatoes, mozzarella, and basil',
        ]);

        $this->assertDatabaseHas('recipes', ['name' => 'Margherita'], 'cookbook');
    }
}
```

Refresh multiple slices at once:

```php
$this->refreshSlice('pizza', 'orders');
```

The trait mirrors Laravel's `LazilyRefreshDatabase` approach:
- Migrations run once per test run (tracked per connection)
- Each test is wrapped in a transaction that gets rolled back
- Tables are dropped and rebuilt on first run

### Additional Test Methods

```php
// Manually rollback all slice transactions
$this->rollbackSliceTransactions();

// Reset migration state to force re-migration
RefreshSliceDatabase::resetSliceMigrationState();
```
