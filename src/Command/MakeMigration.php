<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use FullSmack\LaravelSlice\Slice;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Support\Str;

class MakeMigration extends MigrateMakeCommand
{
    protected $signature = 'make:migration {name : The name of the migration}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--fullpath : Output the full path of the migration (Deprecated)}
        {--slice= : The slice that the migration belongs to}';

    public function handle()
    {
        $sliceName = $this->option('slice');

        if (!$sliceName)
        {
            return parent::handle();
        }

        $config = config('laravel-slice');
        $sliceRootFolder = Str::lower($config['root']['folder']);

        $sliceName = Str::kebab($sliceName);

        $path = "{$sliceRootFolder}/{$sliceName}/database/migrations";

        $this->input->setOption('path', $path);

        $connection = $this->resolveSliceConnection($sliceName);

        $this->creator->stubPath(__DIR__ . '/../../stubs');

        $name = Str::snake(trim($this->input->getArgument('name')));

        $table = $this->option('table') ?: $this->option('create');

        $this->writeMigrationWithSlice($name, $table, $connection, $path);
    }

    protected function resolveSliceConnection(string $sliceName): ?string
    {
        if (!Slice::has($sliceName))
        {
            return null;
        }

        $slice = Slice::get($sliceName);

        return $slice->usesConnection() ? $slice->connection() : null;
    }

    protected function writeMigrationWithSlice(string $name, ?string $table, ?string $connection, string $path): void
    {
        $file = $this->creator->create($name, $this->laravel->basePath($path), $table, (bool) $this->option('create'));

        $this->replaceConnectionPlaceholder($file, $connection);

        $this->components->info(sprintf('Migration [%s] created successfully.', $file));
    }

    protected function replaceConnectionPlaceholder(string $file, ?string $connection): void
    {
        $contents = file_get_contents($file);

        $connectionLine = $connection
            ? "\n    protected \$connection = '{$connection}';\n"
            : '';

        $contents = str_replace('{{ connection }}', $connectionLine, $contents);

        file_put_contents($file, $contents);
    }
}
