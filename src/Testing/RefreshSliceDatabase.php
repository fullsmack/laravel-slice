<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Testing;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** @phpstan-ignore trait.unused (trait is meant to be used by package consumers in their tests) */
trait RefreshSliceDatabase
{
    /** @var array<string, bool> */
    protected static array $slicesMigrated = [];

    /** @var array<string> */
    protected array $sliceConnectionsWithTransactions = [];

    protected function refreshSlice(string ...$sliceNames): static
    {
        foreach ($sliceNames as $sliceName)
        {
            $this->refreshSingleSlice($sliceName);
        }

        return $this;
    }

    protected function refreshSingleSlice(string $sliceName): void
    {
        if (!SliceRegistry::has($sliceName))
        {
            throw SliceNotRegistered::becauseSliceIsNotAddedToRegistry($sliceName);
        }

        $slice = SliceRegistry::get($sliceName);

        if (!$slice->usesConnection())
        {
            throw new RuntimeException(
                "Slice '{$sliceName}' does not use a separate connection. " .
                "Use Laravel's RefreshDatabase trait instead, or configure the slice with ->useConnection('connection-name')."
            );
        }

        $connection = $slice->connection();

        if ($connection === null)
        {
            throw new RuntimeException(
                "Slice '{$sliceName}' is configured to use a connection but no connection is defined. " .
                "Either pass a connection name to ->useConnection('connection-name') or define '{$sliceName}::database.default' in config."
            );
        }

        $migrationPath = $slice->migrationPath();

        $this->migrateSliceConnection($sliceName, $connection, $migrationPath);
        $this->beginSliceTransaction($connection);
    }

    protected function migrateSliceConnection(string $sliceName, string $connection, string $migrationPath): void
    {
        if (isset(static::$slicesMigrated[$connection]))
        {
            return;
        }

        Schema::connection($connection)->dropAllTables();

        $this->artisan('migrate', [
            '--path' => $migrationPath,
            '--database' => $connection,
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->app[Kernel::class]->setArtisan(null);

        static::$slicesMigrated[$connection] = true;
    }

    protected function beginSliceTransaction(string $connection): void
    {
        $database = $this->app->make('db');

        $database->connection($connection)->beginTransaction();

        $this->sliceConnectionsWithTransactions[] = $connection;

        $this->beforeApplicationDestroyed(function () use ($database, $connection)
        {
            $connection = $database->connection($connection);

            if ($connection->transactionLevel() > 0)
            {
                $connection->rollBack();
            }
        });
    }

    protected function rollbackSliceTransactions(): void
    {
        $database = $this->app->make('db');

        foreach ($this->sliceConnectionsWithTransactions as $connection)
        {
            $conn = $database->connection($connection);

            if ($conn->transactionLevel() > 0)
            {
                $conn->rollBack();
            }
        }

        $this->sliceConnectionsWithTransactions = [];
    }

    protected static function resetSliceMigrationState(): void
    {
        static::$slicesMigrated = [];
    }
}
