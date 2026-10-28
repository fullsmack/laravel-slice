<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

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

    /**
     * @return int
     */
    public function handle()
    {
        /** @var string $sliceName */
        $sliceName = $this->argument('sliceName');

        /** @var string|null $dirOption */
        $dirOption = $this->option('dir');

        foreach ([$sliceName, $dirOption ?? ''] as $value)
        {
            $invalidSegment = $this->findInvalidSegment($value);

            if ($invalidSegment !== null)
            {
                $this->error(
                    "Invalid slice path segment \"{$invalidSegment}\". " .
                    'Segments must start with a letter and contain only letters, digits, "-" or "_".'
                );

                return Command::FAILURE;
            }
        }

        $composerFile = base_path('composer.json');

        if (!File::exists($composerFile))
        {
            $this->error("Unable to locate composer.json at [{$composerFile}].");

            return Command::FAILURE;
        }

        $this->defineSlice($sliceName, $dirOption);

        if (File::exists($this->slicePath()))
        {
            $this->error("Slice with identifier \"{$this->sliceName}\" already exists");

            return Command::FAILURE;
        }

        try {
            $this->updateComposerJson($composerFile);
        }
        catch (JsonException $e)
        {
            $this->error("Unable to parse composer.json: {$e->getMessage()}");

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

        $serviceProviderContent = str_replace(
            ['{{sliceRootNamespace}}', '{{slicePascalName}}', '{{sliceName}}'],
            [$this->sliceNamespaceBase(), $slicePascalName, $this->sliceName],
            File::get($stubPath)
        );

        $serviceProviderPath = $this->sliceSourcePath("{$slicePascalName}ServiceProvider.php");

        File::put($serviceProviderPath, $serviceProviderContent);

        $this->dumpAutoload();

        $this->info("Slice \"{$this->sliceName}\" created successfully.");

        if (config('laravel-slice.discovery.type') !== 'composer')
        {
            $this->line(
                "Register the service provider yourself, e.g. in bootstrap/providers.php: " .
                "{$this->sliceNamespace()}\\{$slicePascalName}ServiceProvider::class"
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Returns the first path segment that cannot become a valid directory and namespace segment.
     */
    private function findInvalidSegment(string $value): ?string
    {
        $value = trim(str_replace('\\', '/', $value), '/');

        if ($value === '')
        {
            return null;
        }

        foreach (preg_split('#[/.]#', $value) ?: [] as $segment)
        {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $segment))
            {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Adds the slice autoload entries (and provider, when using composer discovery).
     *
     * The file is decoded to objects rather than arrays so that empty objects
     * such as `"require-dev": {}` survive the round trip.
     *
     * @throws JsonException
     */
    private function updateComposerJson(string $composerFile): void
    {
        $composerData = json_decode(File::get($composerFile), false, 512, JSON_THROW_ON_ERROR);

        if (!$composerData instanceof stdClass)
        {
            throw new JsonException('composer.json must contain a JSON object');
        }

        $slicePascalName = Str::studly($this->sliceFolderName);

        // Use sliceProjectPath for filesystem path (e.g., "src/api/posts")
        $sliceRoot = $this->sliceProjectPath();

        // Build namespace with full path consideration
        $namespace = $this->sliceNamespace();

        $testNamespace = $this->sliceTestNamespace();

        $autoload = $this->objectAt($composerData, 'autoload', 'psr-4');
        $autoload->{$namespace . '\\'} ??= "{$sliceRoot}/src/";

        $autoloadDev = $this->objectAt($composerData, 'autoload-dev', 'psr-4');
        $autoloadDev->{$testNamespace . '\\'} ??= "{$sliceRoot}/tests/";

        if (config('laravel-slice.discovery.type') === 'composer')
        {
            $laravel = $this->objectAt($composerData, 'extra', 'laravel');

            /** @var array<int, string> $providers */
            $providers = $laravel->providers ?? [];

            $providerClass = "{$namespace}\\{$slicePascalName}ServiceProvider";

            if (!in_array($providerClass, $providers, true))
            {
                $providers[] = $providerClass;
            }

            $laravel->providers = $providers;
        }

        File::put(
            $composerFile,
            json_encode(
                $composerData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n"
        );
    }

    /**
     * Walks (and creates) nested objects, e.g. objectAt($data, 'autoload', 'psr-4').
     */
    private function objectAt(stdClass $data, string ...$keys): stdClass
    {
        foreach ($keys as $key)
        {
            if (!isset($data->{$key}) || !$data->{$key} instanceof stdClass)
            {
                $data->{$key} = new stdClass();
            }

            $data = $data->{$key};
        }

        return $data;
    }

    protected function dumpAutoload(): void
    {
        /** @var Composer $composer */
        $composer = $this->laravel->make('composer');

        if ($composer->dumpAutoloads() !== 0)
        {
            $this->warn('`composer dump-autoload` failed. Run it manually to autoload the new slice.');
        }
    }
}
