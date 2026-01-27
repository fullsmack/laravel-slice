<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use FullSmack\LaravelSlice\Command\SliceDefinitions;
use FullSmack\LaravelSlice\Command\SliceMakeDefinitions;

class MakeSlice extends Command
{
    use SliceDefinitions;
    use SliceMakeDefinitions;

    /**
     * @var string
     */
    protected $signature = 'make:slice {sliceName} {--dir= : Subdirectory to create the slice in}';

    /**
     * @var string
     */
    protected $description = 'Create a new slice';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return int
     */
    public function handle()
    {
        /** @var string $sliceName */
        $sliceName = $this->argument('sliceName');

        /** @var string|null $dirOption */
        $dirOption = $this->option('dir');

        $this->defineSlice($sliceName, $dirOption);

        if (File::exists($this->slicePath()))
        {
            $this->error("Slice with name \"{$this->sliceName}\" already exists");

            return Command::FAILURE;
        }

        $directories = [
            'config',
            'lang/en',
            'resources/views/components',
            'routes',
            'src',
            'tests',
        ];

        foreach ($directories as $directory)
        {
            File::makeDirectory($this->slicePath($directory), 0755, true, true);
        }

        $slicePascalName = Str::studly($this->sliceFolderName);

        $stubPath = __DIR__ .'/../../stubs/SliceServiceProvider.stub';

        $serviceProviderContent = File::get($stubPath);

        $serviceProviderContent = Str::replace(
            ['{{sliceRootNamespace}}', '{{slicePascalName}}', '{{sliceName}}'],
            [$this->sliceNamespaceBase(), $slicePascalName, $this->sliceName],
            $serviceProviderContent
        );

        $serviceProviderPath = $this->sliceSourcePath("{$slicePascalName}ServiceProvider.php");

        File::put($serviceProviderPath, $serviceProviderContent);

        $this->updateComposerJson();
        $this->runComposerDumpAutoload();

        $this->info("Slice \"{$this->sliceName}\" created successfully.");

        return Command::SUCCESS;
    }

    private function updateComposerJson(): void
    {
        $composerFile = base_path('composer.json');
        $composerData = File::json($composerFile);

        $slicePascalName = Str::studly($this->sliceFolderName);

        // Use sliceProjectPath for filesystem path (e.g., "src/api/posts")
        $sliceRoot = $this->sliceProjectPath();

        // Build namespace with full path consideration
        $namespace = $this->sliceNamespace();

        $testNamespace = $this->sliceTestNamespace();

        // Update autoload section
        if (!isset($composerData['autoload']['psr-4'][$namespace . '\\']))
        {
            $composerData['autoload']['psr-4'][$namespace . '\\'] = "{$sliceRoot}/src/";
        }

        // Update autoload-dev section
        if (!isset($composerData['autoload-dev']['psr-4'][$testNamespace . '\\']))
        {
            $composerData['autoload-dev']['psr-4'][$testNamespace . '\\'] = "{$sliceRoot}/tests/";
        }

        $providerClass = "{$namespace}\\{$slicePascalName}ServiceProvider";

        if (config('laravel-slice.discovery.type') === 'composer')
        {
            // Update extra section for Laravel providers
            if (!isset($composerData['extra']['laravel']['providers']))
            {
                $composerData['extra']['laravel']['providers'] = [];
            }

            if (!in_array($providerClass, $composerData['extra']['laravel']['providers']))
            {
                $composerData['extra']['laravel']['providers'][] = $providerClass;
            }
        }

        // Save the updated composer.json
        file_put_contents($composerFile, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function runComposerDumpAutoload(): void
    {
        $process = new \Symfony\Component\Process\Process(['composer', 'dump-autoload']);
        $process->setWorkingDirectory(base_path());
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }
}
