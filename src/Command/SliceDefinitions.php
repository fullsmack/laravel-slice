<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

/**
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait SliceDefinitions
{
    private string $sliceName;
    private string $slicePath;
    private string $sliceFullPath;
    private string $sliceRootFolder;
    private string $sliceRootNamespace;
    private string $sliceTestFolder;
    private string $sliceTestNamespace;

    private function defineSliceUsingArgument(): void
    {
        $sliceName = $this->argument('sliceName');

        if (!$sliceName)
        {
            $this->error('Please provide a slice name as the first argument.');

            return;
        }

        $dirOption = method_exists($this, 'option') ? $this->option('dir') : null;

        $this->defineSlice($sliceName, $dirOption);
    }

    private function defineSliceUsingOption(): void
    {
        $sliceName = $this->option('slice');

        if (!$sliceName)
        {
            return;
        }

        $this->defineSlice($sliceName);
    }

    /**
     * Validate and normalize the slice identifier (name or path).
     * Ensures forward slashes are used and warns if backslashes are detected.
     */
    private function validateSliceIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '\\'))
        {
            $this->warn("Please use forward slashes (/) instead of backslashes (\\) in slice paths.");
            $identifier = str_replace('\\', '/', $identifier);
        }

        return trim($identifier, '/');
    }

    /**
     * Parse the slice identifier to extract subdirectory path and slice name.
     * Returns [subdirectoryPath, sliceName]
     */
    private function parseSliceIdentifier(string $identifier): array
    {
        $identifier = $this->validateSliceIdentifier($identifier);

        $segments = explode('/', $identifier);
        $sliceName = array_pop($segments);
        $subdirectoryPath = implode('/', $segments);

        return [$subdirectoryPath, $sliceName];
    }

    /**
     * Build the slice namespace based on config and subdirectory path.
     */
    private function buildSliceNamespace(string $subdirectoryPath, string $dirOption = null): string
    {
        $config = config('laravel-slice');
        $namespaceMode = $config['root']['namespace-mode'] ?? 'prefix';
        $rootNamespace = Str::studly($config['root']['namespace']);

        // Combine dirOption and subdirectoryPath
        $fullSubdirectory = $dirOption
            ? ($subdirectoryPath ? "$dirOption/$subdirectoryPath" : $dirOption)
            : $subdirectoryPath;

        $pathSegments = $fullSubdirectory
            ? array_map([Str::class, 'studly'], explode('/', $fullSubdirectory))
            : [];

        if ($namespaceMode === 'fallback' && !empty($pathSegments))
        {
            // Use path-based namespace only, no root namespace prepended
            return implode('\\', $pathSegments);
        }

        // 'prefix' mode or no path: always use root namespace
        if (empty($pathSegments))
        {
            return $rootNamespace;
        }

        return $rootNamespace . '\\' . implode('\\', $pathSegments);
    }

    private function defineSlice(string $sliceName, ?string $dirOption = null): void
    {
        $config = config('laravel-slice');

        [$subdirectoryPath, $actualSliceName] = $this->parseSliceIdentifier($sliceName);

        $this->sliceName = Str::kebab($actualSliceName);
        $this->sliceRootFolder = Str::lower($config['root']['folder']);

        // Combine dirOption and subdirectoryPath for full path
        $fullSubdirectory = $dirOption
            ? ($subdirectoryPath ? "$dirOption/$subdirectoryPath" : $dirOption)
            : $subdirectoryPath;

        // Build the full path identifier for registry (e.g., "api/pizza")
        $this->sliceFullPath = $fullSubdirectory
            ? $fullSubdirectory . '/' . $this->sliceName
            : $this->sliceName;

        // Build filesystem path
        $this->slicePath = $fullSubdirectory
            ? base_path("{$this->sliceRootFolder}/{$fullSubdirectory}/{$this->sliceName}")
            : base_path("{$this->sliceRootFolder}/{$this->sliceName}");

        $this->sliceRootNamespace = $this->buildSliceNamespace($subdirectoryPath, $dirOption);
        $this->sliceTestNamespace = Str::studly($config['test']['namespace']);
    }

    private function runInSlice(): bool
    {
        return isset($this->sliceName) && $this->sliceName;
    }

    private function getRegisteredSlice(): ?Slice
    {
        if (!$this->runInSlice())
        {
            return null;
        }

        // Use full path for registry lookup
        $registryKey = $this->sliceFullPath ?? $this->sliceName;

        if (!SliceRegistry::has($registryKey))
        {
            return null;
        }

        return SliceRegistry::get($registryKey);
    }

    private function sliceUsesConnection(): bool
    {
        $slice = $this->getRegisteredSlice();

        return $slice !== null && $slice->usesConnection();
    }

    private function getSliceConnection(): ?string
    {
        $slice = $this->getRegisteredSlice();

        return $slice?->connection();
    }

    /**
     * @return string
     */
    protected function rootNamespace()
    {
        return $this->sliceRootNamespace.'\\'.Str::studly($this->sliceName).'\\';
    }

    /**
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->slicePath .'/src/'. str_replace('\\', '/', $name) .'.php';
    }

    /**
     * Get the first view directory path from the application configuration.
     *
     * @param  string  $path
     * @return string
     */
    protected function viewPath($path = '')
    {
        if (!$this->runInSlice())
        {
            /** @phpstan-ignore staticMethod.notFound (viewPath exists in GeneratorCommand subclasses) */
            return parent::viewPath($path);
        }

        $views = $this->slicePath.'/resources/views';

        return $views.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}
