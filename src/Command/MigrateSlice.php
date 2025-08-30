<?php
namespace FullSmack\LaravelSlice\Command;

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * MigrateSlice Command
 *
 * Extends and overrides Laravel's migrate command to support slice-specific migrations.
 *
 * Usage:
 * - php artisan migrate --slice=slice-name (runs slice-specific migrations)
 * - php artisan migrate (runs default Laravel migrations when no slice specified)
 *
 * Features:
 * - Runs migrations from slice's database/migrations directory
 * - Supports slice-specific database connections via config('slice-name::database.default')
 * - Falls back to default Laravel behavior when no slice is specified
 */
class MigrateSlice extends MigrateCommand
{
    use SliceDefinitions;

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
                        {--slice= : Run migrations for a specific slice}';
    protected $description = 'Run the database migrations';

    public function handle()
    {
        $this->defineSliceUsingOption();

        if (!$this->createInSlice())
        {
            return parent::handle();
        }

        // Validate conflicting options when --slice is specified
        if ($this->option('path'))
        {
            $this->error('The --path option cannot be used with --slice. The slice path is determined automatically.');
            return 1;
        }

        if ($this->option('database'))
        {
            $this->error('The --database option cannot be used with --slice. The database connection is determined from slice configuration.');
            return 1;
        }

        if (!File::exists($this->slicePath))
        {
            $this->error("Slice '{$this->sliceName}' does not exist at path: {$this->slicePath}");
            return 1;
        }

        $migrationPath = $this->slicePath . '/database/migrations';

        if (!File::exists($migrationPath))
        {
            $this->warn("No migrations directory found for slice '{$this->sliceName}' at: {$migrationPath}");
            return 0;
        }

        /* Checks if there are any migration files */
        $migrationFiles = File::glob($migrationPath . '/*.php');

        if ($migrationFiles === [])
        {
            $this->info("No migration files found for slice '{$this->sliceName}'.");
            return 0;
        }

        $connection = config($this->sliceName . '::database.default');

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
            fn (mixed $value): bool => $value !== null && $value !== false && $value !== ''
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
