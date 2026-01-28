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
     * Track which slice connections have been migrated.
     *
     * @var array<string, bool>
     */
    protected static array $sliceConnectionsMigrated = [];

    protected function refreshDatabase(): void
    {
        $this->refreshDefaultDatabase();

        $this->refreshSliceDatabases();
    }

    protected function refreshSliceDatabases(): void
    {
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

        // Skip if already migrated in this test run
        if (isset(static::$sliceConnectionsMigrated[$connection]))
        {
            $this->beginSliceTransaction($connection);

            return;
        }

        // Drop all tables and migrate fresh
        Schema::connection($connection)->dropAllTables();

        Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => $migrationPath,
            '--realpath' => true,
            '--force' => true,
        ]);

        static::$sliceConnectionsMigrated[$connection] = true;

        $this->beginSliceTransaction($connection);
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
        static::$sliceConnectionsMigrated = [];
    }
}
