<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Testing;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Trait for refreshing the default database and all slice databases in tests.
 *
 * This trait combines Laravel's RefreshDatabase behavior with slice-specific refreshes.
 * It runs default connection migrations first (for User/auth tables), then runs
 * slice migrations for custom connections. Each test runs inside a transaction
 * on every refreshed connection, which is rolled back afterwards.
 *
 * Usage:
 *   use RefreshAllDatabases;
 *
 *   // Optional: limit to one or more slices (plus the default connection)
 *   protected string|array|null $refreshSlice = 'accounting.bookkeeping';
 *
 * Slices whose connection is the default test connection are migrated together
 * with the default database by `migrate:fresh` (see migrateFreshUsing()).
 *
 * @phpstan-ignore trait.unused (trait is meant to be used by package consumers in their tests)
 */
trait RefreshAllDatabases
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDefaultDatabase;
        migrateFreshUsing as protected defaultMigrateFreshUsing;
    }

    /**
     * Track which (connection, slice) pairs have been migrated.
     * Key format: "{connection}:{sliceIdentifier}"
     *
     * Static so it persists across tests (migrations run once per test suite run).
     *
     * @var array<string, bool>
     */
    protected static array $slicesMigrated = [];

    /**
     * Track which connections have had their tables dropped.
     *
     * Static so it persists across tests (drop happens once before first migration).
     *
     * @var array<string, bool>
     */
    protected static array $sliceConnectionsDropped = [];

    /**
     * PDO handles of in-memory slice connections, kept so the migrated
     * database survives the application being recreated between tests.
     *
     * @var array<string, PDO>
     */
    protected static array $sliceInMemoryConnections = [];

    /**
     * Track which connections have an active transaction in the current test.
     * Reset at the start of each refreshSliceDatabases() call.
     *
     * @var array<string, bool>
     */
    protected array $activeSliceTransactions = [];

    protected function refreshDatabase(): void
    {
        $this->refreshDefaultDatabase();

        $this->refreshSliceDatabases();
    }

    /**
     * The identifiers of the slices to refresh: those listed in the test class's
     * $refreshSlice property, or every registered slice with its own connection.
     *
     * @return array<int, string>
     */
    protected function slicesToRefresh(): array
    {
        // Read the optional property dynamically: declaring it in the trait would
        // make a redeclaration with a different default a fatal error.
        /** @var string|array<int, string>|null $refreshSlice */
        $refreshSlice = get_object_vars($this)['refreshSlice'] ?? null;

        if ($refreshSlice !== null)
        {
            return (array) $refreshSlice;
        }

        return SliceRegistry::slicesWithConnections()
            ->map(static fn (Slice $slice): string => $slice->identifier())
            ->values()
            ->all();
    }

    protected function refreshSliceDatabases(): void
    {
        $this->activeSliceTransactions = [];

        foreach ($this->slicesToRefresh() as $identifier)
        {
            $this->refreshSingleSliceDatabase($identifier);
        }
    }

    /**
     * @throws \FullSmack\LaravelSlice\SliceNotRegistered when the slice is not registered
     * @throws \FullSmack\LaravelSlice\SliceMisconfigured when the slice connection cannot be resolved
     */
    protected function refreshSingleSliceDatabase(string $identifier): void
    {
        $slice = SliceRegistry::get($identifier);

        if (!$slice->usesConnection())
        {
            // Slice uses default connection, already handled by refreshDefaultDatabase
            return;
        }

        /** @var string $connection usesConnection() guarantees a string or an exception */
        $connection = $slice->connection();

        if ($connection === $this->defaultConnectionName())
        {
            // Migrated by migrate:fresh and wrapped in RefreshDatabase's transaction
            return;
        }

        $database = $this->app->make('db');
        $inMemory = $this->isInMemoryConnection($connection);

        // Restore once per test, before the transaction starts (setPdo() resets the transaction count)
        if ($inMemory
            && isset(static::$sliceInMemoryConnections[$connection])
            && !isset($this->activeSliceTransactions[$connection]))
        {
            $database->connection($connection)->setPdo(static::$sliceInMemoryConnections[$connection]);
        }

        $migrationKey = "{$connection}:{$identifier}";

        if (!isset(static::$slicesMigrated[$migrationKey]))
        {
            // Drop all tables only once per connection (before any slice migrates on it)
            if (!isset(static::$sliceConnectionsDropped[$connection]))
            {
                Schema::connection($connection)->dropAllTables();
                static::$sliceConnectionsDropped[$connection] = true;
            }

            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => $slice->migrationPath(),
                '--realpath' => true,
                '--force' => true,
            ]);

            static::$slicesMigrated[$migrationKey] = true;
        }

        if ($inMemory)
        {
            static::$sliceInMemoryConnections[$connection] ??= $database->connection($connection)->getPdo();
        }

        // Begin a transaction for this connection only once per test
        if (!isset($this->activeSliceTransactions[$connection]))
        {
            $this->beginSliceTransaction($connection);
            $this->activeSliceTransactions[$connection] = true;
        }
    }

    /**
     * Adds the migrations of slices that live on the default connection to
     * Laravel's `migrate:fresh` run, so they are not wiped by it.
     *
     * If your test class overrides migrateFreshUsing(), merge in
     * defaultConnectionSliceMigrationOptions() yourself.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return array_merge(
            $this->defaultMigrateFreshUsing(),
            $this->defaultConnectionSliceMigrationOptions(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConnectionSliceMigrationOptions(): array
    {
        $defaultConnection = $this->defaultConnectionName();
        $paths = [];

        foreach ($this->slicesToRefresh() as $identifier)
        {
            if (!SliceRegistry::has($identifier))
            {
                continue;
            }

            $slice = SliceRegistry::get($identifier);

            if ($slice->usesConnection()
                && $slice->connection() === $defaultConnection
                && is_dir($slice->migrationPath()))
            {
                $paths[] = $slice->migrationPath();
            }
        }

        if ($paths === [])
        {
            return [];
        }

        // --path replaces the default locations, so they have to be listed as well
        return [
            '--path' => array_values(array_unique([
                ...$this->app['migrator']->paths(),
                $this->app->databasePath('migrations'),
                ...$paths,
            ])),
            '--realpath' => true,
        ];
    }

    protected function defaultConnectionName(): string
    {
        /** @var string */
        return config('database.default');
    }

    protected function isInMemoryConnection(string $connection): bool
    {
        return config("database.connections.{$connection}.database") === ':memory:';
    }

    /**
     * Begin a database transaction on the slice connection.
     */
    protected function beginSliceTransaction(string $connectionName): void
    {
        $database = $this->app->make('db');
        $connection = $database->connection($connectionName);
        $dispatcher = $connection->getEventDispatcher();

        $connection->unsetEventDispatcher();
        $connection->beginTransaction();
        $connection->setEventDispatcher($dispatcher);

        $this->beforeApplicationDestroyed(function () use ($connection): void
        {
            if ($connection->transactionLevel() > 0)
            {
                $connection->rollBack();
            }
        });
    }

    protected static function resetSliceConnectionsMigrated(): void
    {
        static::$slicesMigrated = [];
        static::$sliceConnectionsDropped = [];
        static::$sliceInMemoryConnections = [];
    }
}
