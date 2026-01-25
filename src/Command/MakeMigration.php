<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use FullSmack\LaravelSlice\SliceRegistry;

class MakeMigration extends MigrateMakeCommand
{
    use SliceDefinitions;

    protected $signature = 'make:migration {name : The name of the migration}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--fullpath : Output the full path of the migration (Deprecated)}
        {--slice= : The slice that the migration belongs to}
        {--dir= : Subdirectory where the slice is located}';

    public function __construct(MigrationCreator $creator, Composer $composer)
    {
        parent::__construct($creator, $composer);
    }

    /**
     * @return void
     */
    public function handle()
    {
        $sliceName = $this->option('slice');

        if (!$sliceName)
        {
            parent::handle();

            return;
        }

        $this->defineSliceUsingOption();

        $path = "{$this->slicePath}/database/migrations";
        $this->input->setOption('path', $path);

        $connection = $this->resolveSliceConnection();

        $name = Str::snake(trim($this->input->getArgument('name')));

        $table = $this->option('table') ?: $this->option('create');

        $this->writeMigrationWithSlice($name, $table, $connection, $path);
    }

    protected function resolveSliceConnection(): ?string
    {
        $registryKey = $this->sliceFullPath ?? $this->sliceName;

        if (!SliceRegistry::has($registryKey))
        {
            return null;
        }

        $slice = SliceRegistry::get($registryKey);

        return $slice->usesConnection() ? $slice->connection() : null;
    }

    protected function writeMigrationWithSlice(string $name, ?string $table, ?string $connection, string $path): void
    {
        $sliceCreator = new MigrationCreator(
            new Filesystem(),
            dirname(__DIR__, 2) . '/stubs'
        );

        $file = $sliceCreator->create(
            $name,
            $path,
            $table,
            (bool) $this->option('create'),
        );

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
