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
    private ?string $sliceName = null;
    private string $sliceFolderName;
    private string $slicePath;
    private string $sliceFullPath;
    private string $sliceRootFolder;
    private string $sliceNamespaceBase;
    private string $sliceTestFolder;
    private string $testNamespaceBase;
    private string $sliceNamespace;
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

        // Try to get slice from registry first (for existing slices)
        if (SliceRegistry::has($sliceName))
        {
            $this->defineSliceFromRegistry($sliceName);
        }
        else
        {
            // Fallback to path-based definition for slice creation commands
            $this->defineSlice($sliceName);
        }
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
     * Handles both slash notation (api/posts) and dot notation (api.posts).
     * Returns [subdirectoryPath, sliceName]
     */
    private function parseSliceIdentifier(string $identifier): array
    {
        $identifier = $this->validateSliceIdentifier($identifier);

        // Convert dot notation to slash notation (api.posts -> api/posts)
        // Only if there are no slashes already present
        if (!str_contains($identifier, '/') && str_contains($identifier, '.'))
        {
            $identifier = str_replace('.', '/', $identifier);
        }

        $segments = explode('/', $identifier);
        $sliceName = array_pop($segments);
        $subdirectoryPath = implode('/', $segments);

        return [$subdirectoryPath, $sliceName];
    }

    /**
     * Build the slice namespace based on config and subdirectory path.
     */
    private function buildSliceNamespace(string $subdirectoryPath, ?string $dirOption = null): string
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

        $this->sliceFolderName = Str::kebab($actualSliceName);
        $this->sliceRootFolder = Str::lower($config['root']['folder']);

        // Combine dirOption and subdirectoryPath for full path
        $fullSubdirectory = $dirOption
            ? ($subdirectoryPath ? "$dirOption/$subdirectoryPath" : $dirOption)
            : $subdirectoryPath;

        // Build the full path identifier for registry (e.g., "api/pizza")
        $this->sliceFullPath = $fullSubdirectory
            ? $fullSubdirectory . '/' . $this->sliceFolderName
            : $this->sliceFolderName;

        // Build the full slice name with dot notation (e.g., "api.posts")
        $this->sliceName = $fullSubdirectory
            ? str_replace('/', '.', $this->sliceFullPath)
            : $this->sliceFolderName;

        // Build filesystem path
        $this->slicePath = $fullSubdirectory
            ? base_path("{$this->sliceRootFolder}/{$fullSubdirectory}/{$this->sliceFolderName}")
            : base_path("{$this->sliceRootFolder}/{$this->sliceFolderName}");

        $this->sliceNamespaceBase = $this->buildSliceNamespace($subdirectoryPath, $dirOption);

        // Build test namespace base to mirror slice namespace structure
        $config = config('laravel-slice');
        $testRootNamespace = Str::studly($config['test']['namespace']);
        $pathSegments = $fullSubdirectory
            ? array_map([Str::class, 'studly'], explode('/', $fullSubdirectory))
            : [];

        $this->testNamespaceBase = empty($pathSegments)
            ? $testRootNamespace
            : $testRootNamespace . '\\' . implode('\\', $pathSegments);

        // Compute full namespaces
        $this->sliceNamespace = $this->sliceNamespaceBase . '\\' . Str::studly($this->sliceFolderName);
        $this->sliceTestNamespace = $this->testNamespaceBase . '\\' . Str::studly($this->sliceFolderName);
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

        if (!SliceRegistry::has($this->sliceName))
        {
            return null;
        }

        return SliceRegistry::get($this->sliceName);
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
        return $this->sliceNamespace . '\\';
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
