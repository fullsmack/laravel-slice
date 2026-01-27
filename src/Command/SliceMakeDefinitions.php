<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;

/**
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait SliceMakeDefinitions
{
    private function defineSlice(string $sliceName, ?string $dirOption = null): void
    {
        $config = config('laravel-slice');

        [$subdirectoryPath, $actualSliceName] = $this->parseSliceIdentifier($sliceName);

        $this->sliceFolderName = Str::kebab($actualSliceName);

        $fullSubdirectory = $dirOption
            ? ($subdirectoryPath ? "$dirOption/$subdirectoryPath" : $dirOption)
            : $subdirectoryPath;

        /* Build the full slice name with dot notation (e.g., "api.posts") */
        $sliceRelativePath = $fullSubdirectory
            ? $fullSubdirectory . '/' . $this->sliceFolderName
            : $this->sliceFolderName;

        $this->sliceName = str_replace('/', '.', $sliceRelativePath);

        $sourceFolder = Str::lower($config['root']['folder']);

        $this->slicePath = $fullSubdirectory
            ? base_path("{$sourceFolder}/{$fullSubdirectory}/{$this->sliceFolderName}")
            : base_path("{$sourceFolder}/{$this->sliceFolderName}");

        $namespaceBase = $this->buildSliceNamespaceBase($subdirectoryPath, $dirOption);

        $this->sliceNamespace = $namespaceBase . '\\' . Str::studly($this->sliceFolderName);

        $pathNamespaceBase = $this->buildPathNamespaceBase($fullSubdirectory);

        $this->sliceTestNamespace = $this->resolveTestNamespace($pathNamespaceBase);
    }

    /**
     * Gets the namespace base without the final slice segment.
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
     * Gets the test namespace base without the final slice segment.
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
     * Builds the path-derived namespace segments (without any prefix).
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
     * Builds the slice namespace base (without slice segment) from config and path.
     */
    private function buildSliceNamespaceBase(string $subdirectoryPath, ?string $dirOption = null): string
    {
        $config = config('laravel-slice');
        $namespaceMode = $config['root']['namespace-mode'] ?? 'prefix';
        $rootNamespace = Str::studly($config['root']['namespace']);

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
