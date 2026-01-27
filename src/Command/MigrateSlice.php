<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use FullSmack\LaravelSlice\SliceNotRegistered;

class MigrateSlice extends MigrateCommand
{
    use SliceDefinitions;

    /**
     * @var string
     */
    protected $signature = 'migrate
        {--database= : The database connection to use}
        {--force : Force the operation to run when in production}
        {--path=* : The path(s) to the migrations files to be executed}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--schema-path= : The path to a schema dump file}
        {--pretend : Dump the SQL queries that would be run}
        {--seed : Indicates if the seed task should be re-run}
        {--seeder= : The class name of the root seeder}
        {--step : Force the migrations to be run so they can be rolled back individually}
        {--slice= : Run migrations for a specific slice}
        {--dir= : Subdirectory where the slice is located}';

    /**
     * @var string
     */
    protected $description = 'Run the database migrations';

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);
    }

    /**
     * @return int
     */
    public function handle()
    {
        $sliceName = $this->option('slice');

        if (!$sliceName)
        {
            return parent::handle();
        }

        try {
            $this->loadFromRegistry($sliceName);
        }
        catch (SliceNotRegistered $e)
        {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        // Validate --path option cannot be used with --slice (path is determined automatically)
        if ($this->option('path'))
        {
            $this->error('The --path option cannot be used with --slice. The slice path is determined automatically.');

            return Command::FAILURE;
        }

        // If slice has its own connection, don't allow --database override
        if ($this->sliceUsesConnection() && $this->option('database'))
        {
            $this->error('The --database option cannot be used with --slice when the slice has a configured connection.');

            return Command::FAILURE;
        }

        if (!File::exists($this->slicePath()))
        {
            $this->error("Slice '{$this->sliceName}' does not exist at path: {$this->slicePath()}");

            return Command::FAILURE;
        }

        $migrationPath = $this->sliceMigrationPath();

        if (!File::exists($migrationPath))
        {
            $this->warn("No migrations directory found for slice '{$this->sliceName}' at: {$migrationPath}");

            return Command::SUCCESS;
        }

        /* Checks if there are any migration files */
        $migrationFiles = File::glob($migrationPath . '/*.php');

        if ($migrationFiles === [])
        {
            $this->info("No migration files found for slice '{$this->sliceName}'.");

            return Command::SUCCESS;
        }

        // Use slice connection if configured, otherwise allow --database or default
        $connection = $this->sliceUsesConnection() ? $this->sliceConnection() : $this->option('database');

        if ($this->sliceUsesConnection() && !$connection)
        {
            $this->error("Slice '{$this->sliceName}' is configured to use a connection but no connection is defined. Use ->useConnection('connection-name') when configuring the slice.");

            return Command::FAILURE;
        }

        $params = array_filter([
                '--path' => $migrationPath,
                '--database' => $connection,
                '--force' => $this->option('force'),
                '--realpath' => $this->option('realpath'),
                '--schema-path' => $this->option('schema-path'),
                '--pretend' => $this->option('pretend'),
                '--seed' => $this->option('seed'),
                '--seeder' => $this->option('seeder'),
                '--step' => $this->option('step'),
            ],
            static fn (mixed $value): bool => $value !== null && $value !== false && $value !== '',
        );

        $this->line("Running migrations for slice: {$this->sliceName}");
        $this->line("Migration path: {$migrationPath}");

        if ($connection)
        {
            $this->line("Using database connection: {$connection}");
        }

        return Artisan::call(MigrateCommand::class, $params);
    }
}
