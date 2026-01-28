<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;

class TestSlice extends Command
{
    use SliceDefinitions;

    /**
     * @var string
     */
    protected $signature = 'test
        {--slice= : Run tests for a specific slice}
        {--dir= : Subdirectory where the slice is located}
        {--filter= : Filter which tests to run}
        {--parallel : Run tests in parallel}
        {--coverage : Generate code coverage report}
        {--min= : Minimum coverage threshold}';

    /**
     * @var string
     */
    protected $description = 'Run application tests, optionally scoped to a slice';

    public function handle(): int
    {
        $sliceName = $this->option('slice');

        if (!$sliceName || !is_string($sliceName))
        {
            // No slice specified, run all tests via standard test command
            return $this->runAllTests();
        }

        return $this->runSliceTests($sliceName);
    }

    protected function runAllTests(): int
    {
        $command = $this->buildTestCommand();

        return $this->executeProcess($command);
    }

    protected function runSliceTests(string $sliceName): int
    {
        try {
            $this->loadFromRegistry($sliceName);
        }
        catch (SliceNotRegistered $e)
        {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $slice = SliceRegistry::get($sliceName);

        $testPath = $slice->path('tests');

        if (!is_dir($testPath))
        {
            $this->error("No tests directory found for slice '{$sliceName}' at: {$testPath}");

            return Command::FAILURE;
        }

        $this->info("Running tests for slice: {$sliceName}");
        $this->info("Test path: {$testPath}");

        $command = $this->buildTestCommand($testPath);

        return $this->executeProcess($command);
    }

    /**
     * @return array<string>
     */
    protected function buildTestCommand(?string $testPath = null): array
    {
        $command = [PHP_BINARY, 'vendor/bin/phpunit'];

        if ($testPath !== null)
        {
            $command[] = $testPath;
        }

        $filter = $this->option('filter');

        if ($filter !== null && is_string($filter))
        {
            $command[] = "--filter={$filter}";
        }

        if ($this->option('parallel'))
        {
            $command[] = '--parallel';
        }

        if ($this->option('coverage'))
        {
            $command[] = '--coverage-text';

            $min = $this->option('min');

            if ($min !== null && is_string($min))
            {
                $command[] = "--min={$min}";
            }
        }

        return $command;
    }

    /**
     * @param array<string> $command
     */
    protected function executeProcess(array $command): int
    {
        $process = new Process($command, base_path());
        $process->setTimeout(null);

        if (Process::isTtySupported())
        {
            $process->setTty(true);
        }

        return $process->run(function (string $type, string $buffer): void
        {
            $this->output->write($buffer);
        });
    }
}
