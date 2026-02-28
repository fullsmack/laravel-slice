<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Testing;

use FullSmack\LaravelSlice\SliceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Trait for refreshing the default database and all slice databases in tests.
 *
 * This trait combines Laravel's RefreshDatabase behavior with slice-specific refreshes.
 * It runs default connection migrations first (for User/auth tables), then runs
 * slice migrations for custom connections.
 *
 * Usage:
 *   use RefreshAllDatabases;
 *
 *   // Optional: limit to a single slice + default
 *   protected ?string $refreshSlice = 'accounting-bookkeeping';
 *
 * @phpstan-ignore trait.unused (trait is meant to be used by package consumers in their tests)
 */
trait RefreshAllDatabases
{
    use RefreshDatabase {
        refreshDatabase as protected refreshDefaultDatabase;
    }

    /**
     * Optionally limit to a single slice (plus default connection).
     * Set this property in your test class to scope migrations.
     */
    protected ?string $refreshSlice = null;

    /**
     * Track which (connection, slice) pairs have been migrated.
     * Key format: "{connection}:{sliceName}"
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

    protected function refreshSliceDatabases(): void
    {
        $this->activeSliceTransactions = [];

        if ($this->refreshSlice !== null)
        {
            $this->refreshSingleSliceDatabase($this->refreshSlice);

            return;
        }

        $slices = SliceRegistry::slicesWithConnections();

        foreach ($slices as $slice)
        {
            $this->refreshSingleSliceDatabase($slice->name());
        }
    }

    protected function refreshSingleSliceDatabase(string $sliceName): void
    {
        if (!SliceRegistry::has($sliceName))
        {
            throw new RuntimeException("Slice '{$sliceName}' is not registered.");
        }

        $slice = SliceRegistry::get($sliceName);

        if (!$slice->usesConnection())
        {
            // Slice uses default connection, already handled by refreshDefaultDatabase
            return;
        }

        $connection = $slice->connection();

        if ($connection === null)
        {
            throw new RuntimeException(
                "Slice '{$sliceName}' is configured to use a connection but no connection is defined."
            );
        }

        $migrationPath = $slice->migrationPath();
        $migrationKey = "{$connection}:{$sliceName}";

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
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => true,
            ]);

            static::$slicesMigrated[$migrationKey] = true;
        }

        // Begin a transaction for this connection only once per test
        if (!isset($this->activeSliceTransactions[$connection]))
        {
            $this->beginSliceTransaction($connection);
            $this->activeSliceTransactions[$connection] = true;
        }
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
    }
}
