<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;
use RuntimeException;

/**
 * Run tests scoped to a specific slice.
 *
 * This command spawns PHPUnit/Pest directly with the slice's test path,
 * providing real-time streaming output like the standard test command.
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

        return $this->runTests($testPath);
    }

    /**
     * Run the tests using a Process for real-time output streaming.
     */
    protected function runTests(string $testPath): int
    {
        $process = (new Process(
            $this->buildCommand($testPath),
            base_path(),
            $this->buildEnvironmentVariables(),
        ))->setTimeout(null);

        try {
            $process->setTty(!$this->option('without-tty'));
        }
        catch (RuntimeException)
        {
            // TTY not supported, continue without it
        }

        $exitCode = 1;

        try {
            $exitCode = $process->run(function (string $type, string $line): void
            {
                $this->output->write($line);
            });
        }
        catch (ProcessSignaledException $e)
        {
            if (extension_loaded('pcntl') && $e->getSignal() !== SIGINT)
            {
                throw $e;
            }
        }

        return $exitCode;
    }

    /**
     * Build the test command with all arguments.
     *
     * @return array<int, string>
     */
    protected function buildCommand(string $testPath): array
    {
        $command = $this->binary();

        // Add common arguments
        $command = array_merge($command, $this->buildArguments());

        // Add configuration file
        $command[] = '--configuration=' . $this->getConfigurationFile();

        // Add the slice test path
        $command[] = $testPath;

        return $command;
    }

    /**
     * Get the PHP binary and test runner.
     *
     * @return array<int, string>
     */
    protected function binary(): array
    {
        if ($this->usingPest())
        {
            $runner = $this->option('parallel')
                ? ['vendor/pestphp/pest/bin/pest', '--parallel']
                : ['vendor/pestphp/pest/bin/pest'];
        }
        else
        {
            $runner = $this->option('parallel')
                ? ['vendor/brianium/paratest/bin/paratest']
                : ['vendor/phpunit/phpunit/phpunit'];
        }

        if (PHP_SAPI === 'phpdbg')
        {
            return array_merge([PHP_BINARY, '-qrr'], $runner);
        }

        return array_merge([PHP_BINARY], $runner);
    }

    /**
     * Determine if Pest is being used.
     */
    protected function usingPest(): bool
    {
        return function_exists('\Pest\version');
    }

    /**
     * Build the arguments for the test runner.
     *
     * @return array<int, string>
     */
    protected function buildArguments(): array
    {
        $arguments = [];

        // Color support
        if ($this->option('ansi'))
        {
            $arguments[] = '--colors=always';
        }
        elseif ($this->option('no-ansi'))
        {
            $arguments[] = '--colors=never';
        }
        else
        {
            $arguments[] = '--colors=always';
        }

        // Filter
        $filter = $this->option('filter');
        if ($filter !== null && is_string($filter))
        {
            $arguments[] = "--filter={$filter}";
        }

        // Coverage
        if ($this->option('coverage'))
        {
            $arguments[] = '--coverage-text';
        }

        return $arguments;
    }

    /**
     * Get the PHPUnit configuration file path.
     */
    protected function getConfigurationFile(): string
    {
        $file = base_path('phpunit.xml');

        if (!file_exists($file))
        {
            $file = base_path('phpunit.xml.dist');
        }

        return $file;
    }

    /**
     * Build environment variables for the test process.
     *
     * @return array<string, string>
     */
    protected function buildEnvironmentVariables(): array
    {
        $env = [];

        if ($this->option('recreate-databases'))
        {
            $env['LARAVEL_PARALLEL_TESTING_DROP_DATABASES'] = '1';
        }

        if ($this->option('drop-databases'))
        {
            $env['LARAVEL_PARALLEL_TESTING_DROP_DATABASES'] = '1';
        }

        if ($this->option('without-databases'))
        {
            $env['LARAVEL_PARALLEL_TESTING_WITHOUT_DATABASES'] = '1';
        }

        return $env;
    }
}
