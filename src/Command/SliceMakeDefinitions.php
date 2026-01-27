<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;

/**
 * Provides slice creation context for make:slice command.
 *
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait SliceMakeDefinitions
{
    // =========================================================================
    // Entry Point
    // =========================================================================

    private function resolveSliceFromArgument(): void
    {
        $sliceName = $this->argument('sliceName');

        if (!$sliceName)
        {
            $this->error('Please provide a slice name as the first argument.');

            return;
        }

        /** @var string|null $dirOption */
        $dirOption = $this->option('dir');

        $this->defineSliceFromPath($sliceName, $dirOption);
    }

    // =========================================================================
    // Definition Method
    // =========================================================================

    /**
     * Define slice properties from path input (used during slice creation).
     */
    private function defineSliceFromPath(string $sliceName, ?string $dirOption = null): void
    {
        $config = config('laravel-slice');

        [$subdirectoryPath, $actualSliceName] = $this->parseSliceIdentifier($sliceName);

        $this->sliceFolderName = Str::kebab($actualSliceName);

        // Combine dirOption and subdirectoryPath for full path
        $fullSubdirectory = $dirOption
            ? ($subdirectoryPath ? "$dirOption/$subdirectoryPath" : $dirOption)
            : $subdirectoryPath;

        // Build the full slice name with dot notation (e.g., "api.posts")
        $sliceRelativePath = $fullSubdirectory
            ? $fullSubdirectory . '/' . $this->sliceFolderName
            : $this->sliceFolderName;

        $this->sliceName = str_replace('/', '.', $sliceRelativePath);

        // Build filesystem absolute path
        $sourceFolder = Str::lower($config['root']['folder']);
        $this->slicePath = $fullSubdirectory
            ? base_path("{$sourceFolder}/{$fullSubdirectory}/{$this->sliceFolderName}")
            : base_path("{$sourceFolder}/{$this->sliceFolderName}");

        // Build namespace
        $namespaceBase = $this->buildSliceNamespaceBase($subdirectoryPath, $dirOption);
        $this->sliceNamespace = $namespaceBase . '\\' . Str::studly($this->sliceFolderName);

        // Compute namespaceBase (path-derived segments without prefix)
        $pathNamespaceBase = $this->buildPathNamespaceBase($fullSubdirectory);

        // Resolve test namespace from namespaceBase
        $this->sliceTestNamespace = $this->resolveTestNamespace($pathNamespaceBase);
    }

    // =========================================================================
    // Computed Helper Methods
    // =========================================================================

    /**
     * Get the namespace base without the final slice segment.
     * Used for stub replacements and composer.json updates.
     */
    protected function sliceNamespaceBase(): string
    {
        $sliceSegment = Str::studly($this->sliceFolderName);

        $namespace = $this->sliceNamespace;

        if (Str::endsWith($namespace, '\\' . $sliceSegment))
        {
            return Str::beforeLast($namespace, '\\' . $sliceSegment);
        }

        return $namespace;
    }

    /**
     * Get the test namespace base without the final slice segment.
     * Used for composer.json updates.
     */
    protected function testNamespaceBase(): string
    {
        $sliceSegment = Str::studly($this->sliceFolderName);

        $namespace = $this->sliceTestNamespace;

        if (Str::endsWith($namespace, '\\' . $sliceSegment))
        {
            return Str::beforeLast($namespace, '\\' . $sliceSegment);
        }

        return $namespace;
    }

    /**
     * Get the configured root source folder (e.g., 'src').
     * Used by MakeSlice for composer.json paths.
     */
    protected function configuredSourceFolder(): string
    {
        return Str::lower(config('laravel-slice.root.folder'));
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Build the path-derived namespace segments (without any prefix).
     * Example: 'Api\Posts' for path 'api/posts'
     */
    private function buildPathNamespaceBase(?string $subdirectory): string
    {
        $segments = [];

        if (!empty($subdirectory))
        {
            $segments = array_map([Str::class, 'studly'], explode('/', $subdirectory));
        }

        $segments[] = Str::studly($this->sliceFolderName);

        return implode('\\', $segments);
    }

    /**
     * Build the slice namespace base (without slice segment) from config and path.
     */
    private function buildSliceNamespaceBase(string $subdirectoryPath, ?string $dirOption = null): string
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
            return implode('\\', $pathSegments);
        }

        if (empty($pathSegments))
        {
            return $rootNamespace;
        }

        return $rootNamespace . '\\' . implode('\\', $pathSegments);
    }
}
