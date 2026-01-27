<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use FullSmack\LaravelSlice\SliceNotRegistered;
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

        $this->input->setOption('path', $this->sliceMigrationPath());
        $this->input->setOption('realpath', true);

        return parent::handle();
    }

    protected function resolveSliceConnection(): ?string
    {
        if (!SliceRegistry::has($this->sliceName))
        {
            return null;
        }

        $slice = SliceRegistry::get($this->sliceName);

        return $slice->usesConnection() ? $slice->connection() : null;
    }

    protected function writeMigration($name, $table, $create)
    {
        if (!$this->option('slice'))
        {
            return parent::writeMigration($name, $table, $create);
        }

        $sliceCreator = new MigrationCreator(
            new Filesystem(),
            dirname(__DIR__, 2) . '/stubs',
        );

        $file = $sliceCreator->create(
            $name,
            $this->getMigrationPath(),
            $table,
            $create,
        );

        $connection = $this->resolveSliceConnection();

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
