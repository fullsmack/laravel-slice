<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NunoMaduro\Collision\Adapters\Laravel\Commands\TestCommand;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;

/**
 * Extends the test command to support slice-scoped testing.
 *
 * When --slice is provided, this command runs tests only for that slice.
 * Otherwise, it delegates to the standard test command.
 */
class TestSlice extends Command
{
    use SliceDefinitions;

    /**
     * @var string
     */
    protected $signature = 'slice:test
        {--slice= : Run tests for a specific slice}
        {--dir= : Subdirectory where the slice is located}
        {--without-tty : Disable output to TTY}
        {--compact : Indicates whether the compact printer should be used}
        {--coverage : Indicates whether code coverage information should be collected}
        {--min= : Indicates the minimum threshold enforcement for code coverage}
        {--p|parallel : Indicates if the tests should run in parallel}
        {--profile : Lists top 10 slowest tests}
        {--recreate-databases : Indicates if the test databases should be re-created}
        {--drop-databases : Indicates if the test databases should be dropped}
        {--without-databases : Indicates if database configuration should be performed}
        {--filter= : Filter which tests to run}';

    /**
     * @var string
     */
    protected $description = 'Run tests for a specific slice';

    /**
     * @return int
     */
    public function handle()
    {
        $sliceName = $this->option('slice');

        if (!$sliceName || !is_string($sliceName))
        {
            $this->error('The --slice option is required for slice:test command.');
            $this->info('Use "php artisan test" to run all tests.');

            return static::FAILURE;
        }

        try {
            $this->loadFromRegistry($sliceName);
        }
        catch (SliceNotRegistered $e)
        {
            $this->error($e->getMessage());

            return static::FAILURE;
        }

        $slice = SliceRegistry::get($sliceName);
        $testPath = $slice->path('tests');

        if (!is_dir($testPath))
        {
            $this->error("No tests directory found for slice '{$sliceName}' at: {$testPath}");

            return static::FAILURE;
        }

        $this->info("Running tests for slice: {$sliceName}");
        $this->info("Test path: {$testPath}");
        $this->newLine();

        // Build arguments to pass to the test command
        // We inject the test path via $_SERVER['argv'] manipulation
        $originalArgv = $_SERVER['argv'];

        try {
            // Rebuild argv without our custom options, but with the test path
            $_SERVER['argv'] = $this->buildArgvForTestCommand($testPath);

            return Artisan::call(TestCommand::class, $this->buildTestOptions(), $this->output);
        }
        finally
        {
            // Restore original argv
            $_SERVER['argv'] = $originalArgv;
        }
    }

    /**
     * Build the argv array for the test command.
     *
     * @return array<int, string>
     */
    protected function buildArgvForTestCommand(string $testPath): array
    {
        $argv = ['artisan', 'test'];

        // Pass through supported options
        if ($this->option('without-tty'))
        {
            $argv[] = '--without-tty';
        }

        if ($this->option('compact'))
        {
            $argv[] = '--compact';
        }

        if ($this->option('coverage'))
        {
            $argv[] = '--coverage';
        }

        $min = $this->option('min');
        if ($min !== null && is_string($min))
        {
            $argv[] = "--min={$min}";
        }

        if ($this->option('parallel'))
        {
            $argv[] = '--parallel';
        }

        if ($this->option('profile'))
        {
            $argv[] = '--profile';
        }

        if ($this->option('recreate-databases'))
        {
            $argv[] = '--recreate-databases';
        }

        if ($this->option('drop-databases'))
        {
            $argv[] = '--drop-databases';
        }

        if ($this->option('without-databases'))
        {
            $argv[] = '--without-databases';
        }

        $filter = $this->option('filter');
        if ($filter !== null && is_string($filter))
        {
            $argv[] = "--filter={$filter}";
        }

        // Add the test path as the final argument
        $argv[] = $testPath;

        return $argv;
    }

    /**
     * Build the options array for Artisan::call.
     *
     * @return array<string, mixed>
     */
    protected function buildTestOptions(): array
    {
        $options = [];

        if ($this->option('without-tty'))
        {
            $options['--without-tty'] = true;
        }

        if ($this->option('compact'))
        {
            $options['--compact'] = true;
        }

        if ($this->option('coverage'))
        {
            $options['--coverage'] = true;
        }

        $min = $this->option('min');
        if ($min !== null)
        {
            $options['--min'] = $min;
        }

        if ($this->option('parallel'))
        {
            $options['--parallel'] = true;
        }

        if ($this->option('profile'))
        {
            $options['--profile'] = true;
        }

        if ($this->option('recreate-databases'))
        {
            $options['--recreate-databases'] = true;
        }

        if ($this->option('drop-databases'))
        {
            $options['--drop-databases'] = true;
        }

        if ($this->option('without-databases'))
        {
            $options['--without-databases'] = true;
        }

        return $options;
    }
}
