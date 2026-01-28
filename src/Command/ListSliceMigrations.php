<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use FullSmack\LaravelSlice\SliceRegistry;

class ListSliceMigrations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'slice:list-migrations';

    /**
     * @var string
     */
    protected $description = 'List all registered slices with their migration paths and connections';

    public function handle(): int
    {
        $slices = SliceRegistry::all();

        if ($slices === [])
        {
            $this->info('No slices registered.');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($slices as $slice)
        {
            $migrationPath = $slice->migrationPath();
            $hasMigrations = is_dir($migrationPath) ? '✓' : '✗';
            $connection = $slice->usesConnection()
                ? $slice->connection()
                : '(default)';

            $rows[] = [
                $slice->name(),
                $connection,
                $migrationPath,
                $hasMigrations,
            ];
        }

        $this->table(
            ['Slice', 'Connection', 'Migration Path', 'Has Migrations'],
            $rows
        );

        return Command::SUCCESS;
    }
}
