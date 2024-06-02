<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use FullSmack\LaravelSlice\Command\SliceDefinitions;

class MakeSlice extends Command
{
    use SliceDefinitions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:slice {sliceName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new slice';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->defineSliceUsingArgument();

        if(File::exists($this->slicePath))
        {
            $this->error("Slice with name \"{$this->sliceName}\" already exists");
            return;
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
            File::makeDirectory("{$this->slicePath}/$directory", 0755, true, true);
        }

        $slicePascalName = Str::studly($this->sliceName);

        $stubPath = __DIR__ .'/../../stubs/SliceServiceProvider.stub';

        $serviceProviderContent = File::get($stubPath);

        $serviceProviderContent = Str::replace(
            ['{{sliceRootNamespace}}','{{slicePascalName}}', '{{sliceName}}'],
            [$this->sliceRootNamespace, $slicePascalName, $this->sliceName],
            $serviceProviderContent
        );

        $serviceProviderPath = "{$this->slicePath}/src/{$slicePascalName}ServiceProvider.php";

        File::put($serviceProviderPath, $serviceProviderContent);

        $this->updateComposerJson();
        $this->runComposerDumpAutoload();

        $this->info("Slice \"{$this->sliceName}\" created successfully.");
    }

    private function updateComposerJson()
    {
        $composerFile = base_path('composer.json');
        $composerData = File::json($composerFile);

        $slicePascalName = Str::studly($this->sliceName);

        $sliceRoot = "{$this->sliceRootFolder}/{$this->sliceName}";
        $namespace = "{$this->sliceRootNamespace}\\{$slicePascalName}";

        $testNamespace = "{$this->sliceTestNamespace}\\{$slicePascalName}";

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

        // Update extra section for Laravel providers
        if (!isset($composerData['extra']['laravel']['providers']))
        {
            $composerData['extra']['laravel']['providers'] = [];
        }

        $providerClass = "{$namespace}\\{$slicePascalName}ServiceProvider";

        if (!in_array($providerClass, $composerData['extra']['laravel']['providers']))
        {
            $composerData['extra']['laravel']['providers'][] = $providerClass;
        }

        // Save the updated composer.json
        file_put_contents($composerFile, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function runComposerDumpAutoload()
    {
        $process = new \Symfony\Component\Process\Process(['composer', 'dump-autoload']);
        $process->setWorkingDirectory(base_path());
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
    }
}
