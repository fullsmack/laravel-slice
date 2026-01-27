<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

/**
 * Provides slice context for runtime make commands.
 *
 * This trait handles resolving slice context from the --slice option,
 * loading from the registry, and providing getter methods for paths/namespaces.
 *
 * For slice creation (make:slice), also use SliceMakeDefinitions trait.
 *
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait SliceDefinitions
{
    /**
     * Unique slice identifier (e.g., 'api.posts')
     *
     * @var string|null
     */
    private ?string $sliceName = null;

    /**
     * Kebab-case folder name (e.g., 'posts')
     *
     * @var string
     */
    private string $sliceFolderName;

    /**
     * Absolute filesystem path to slice root
     *
     * @var string
     */
    private string $slicePath;

    /**
     * Full PSR-4 namespace (e.g., 'Slice\Api\Blog')
     *
     * @var string
     */
    private string $sliceNamespace;

    /**
     * Full test namespace (e.g., 'Test\Api\Blog')
     *
     * @var string
     */
    private string $sliceTestNamespace;

    // =========================================================================
    // Entry Point
    // =========================================================================

    /**
     * Resolve slice from --slice option.
     * Loads from registry if slice exists, otherwise throws error.
     */
    private function resolveSliceFromOption(): void
    {
        $sliceName = $this->option('slice');

        if (!$sliceName)
        {
            return;
        }

        if (!SliceRegistry::has($sliceName))
        {
            $this->error("Slice \"{$sliceName}\" is not registered. Create it first with make:slice.");

            return;
        }

        $this->loadFromRegistry($sliceName);
    }

    // =========================================================================
    // Internal Methods
    // =========================================================================

    /**
     * Load slice properties from the registry.
     */
    private function loadFromRegistry(string $sliceName): void
    {
        $slice = SliceRegistry::get($sliceName);

        $this->sliceName = $slice->name();
        $this->slicePath = $slice->path();
        $this->sliceFolderName = basename($this->slicePath);
        $this->sliceNamespace = $slice->baseNamespace();

        $this->sliceTestNamespace = $this->resolveTestNamespace($slice->namespaceBase());
    }

    // =========================================================================
    // Slice-Mirrored Getter Methods
    // =========================================================================

    /**
     * Get the absolute path to the slice root, optionally with a subdirectory.
     * Mirrors Slice::path()
     */
    protected function slicePath(?string $directory = null): string
    {
        if ($directory === null)
        {
            return $this->slicePath;
        }

        return $this->slicePath . DIRECTORY_SEPARATOR .
            ltrim(ltrim($directory, '/'), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the slice's source folder, optionally with a subdirectory.
     * Mirrors Slice::sourcePath()
     */
    protected function sliceSourcePath(?string $directory = null): string
    {
        $source = $this->slicePath . DIRECTORY_SEPARATOR . 'src';

        if ($directory === null)
        {
            return $source;
        }

        return $source . DIRECTORY_SEPARATOR .
            ltrim(ltrim($directory, '/'), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the slice's migrations folder.
     * Mirrors Slice::migrationPath()
     */
    protected function sliceMigrationPath(): string
    {
        return $this->slicePath('database/migrations');
    }

    /**
     * Get the internal source folder name (always 'src').
     * Mirrors Slice::sourceFolder()
     */
    protected function sliceSourceFolder(): string
    {
        return 'src';
    }

    /**
     * Get the slice namespace, optionally with a sub-namespace.
     * Mirrors Slice::baseNamespace()
     */
    protected function sliceNamespace(?string $subnamespace = null): string
    {
        if ($subnamespace === null)
        {
            return $this->sliceNamespace;
        }

        return $this->sliceNamespace . '\\' . $subnamespace;
    }

    /**
     * Get the test namespace, optionally with a sub-namespace.
     */
    protected function sliceTestNamespace(?string $subnamespace = null): string
    {
        if ($subnamespace === null)
        {
            return $this->sliceTestNamespace;
        }

        return $this->sliceTestNamespace . '\\' . $subnamespace;
    }

    /**
     * Get the project-relative path to the slice root.
     * Example: 'src/api/posts' when slicePath is '/var/www/project/src/api/posts'
     */
    protected function sliceProjectPath(): string
    {
        $basePath = $this->laravel->basePath();

        // Remove base path prefix and normalize separators
        $relativePath = str_replace($basePath, '', $this->slicePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        $relativePath = ltrim($relativePath, '/');

        // Normalize to forward slashes for consistency
        return str_replace('\\', '/', $relativePath);
    }

    // =========================================================================
    // State & Registry Methods
    // =========================================================================

    /**
     * Check if currently operating in slice context.
     */
    private function runInSlice(): bool
    {
        return isset($this->sliceName);
    }

    /**
     * Get the registered Slice object if available.
     */
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

    /**
     * Check if the slice uses a custom database connection.
     */
    private function sliceUsesConnection(): bool
    {
        $slice = $this->getRegisteredSlice();

        return $slice !== null && $slice->usesConnection();
    }

    /**
     * Get the slice's database connection name.
     */
    private function getSliceConnection(): ?string
    {
        $slice = $this->getRegisteredSlice();

        return $slice?->connection();
    }

    // =========================================================================
    // Override Methods (for Laravel generator commands)
    // =========================================================================

    /**
     * Get the root namespace for class generation.
     *
     * @return string
     */
    protected function rootNamespace()
    {
        if (!$this->runInSlice())
        {
            /** @phpstan-ignore return.type, staticMethod.notFound */
            return parent::rootNamespace();
        }

        return $this->sliceNamespace . '\\';
    }

    /**
     * Get the destination path for generated classes.
     *
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        if (!$this->runInSlice())
        {
            /** @phpstan-ignore staticMethod.notFound */
            return parent::getPath($name);
        }

        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->sliceSourcePath(str_replace('\\', '/', $name) . '.php');
    }

    /**
     * Get the view directory path for the slice.
     *
     * @param string $path
     * @return string
     */
    protected function viewPath($path = '')
    {
        if (!$this->runInSlice())
        {
            /** @phpstan-ignore staticMethod.notFound (viewPath exists in GeneratorCommand subclasses) */
            return parent::viewPath($path);
        }

        $views = $this->slicePath . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

        return $views . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    /**
     * Resolve the test namespace from the namespace base.
     *
     * Currently uses prefix mode: Test\{namespaceBase}
     * Future: Could read from config for different strategies.
     */
    private function resolveTestNamespace(string $namespaceBase): string
    {
        $testPrefix = Str::studly(config('laravel-slice.test.namespace', 'test'));

        return $testPrefix . '\\' . $namespaceBase;
    }

    /**
     * Validate and normalize the slice identifier.
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
     *
     * @return array{0: string, 1: string} [subdirectoryPath, sliceName]
     */
    private function parseSliceIdentifier(string $identifier): array
    {
        $identifier = $this->validateSliceIdentifier($identifier);

        // Convert dot notation to slash notation if no slashes present
        if (!str_contains($identifier, '/') && str_contains($identifier, '.'))
        {
            $identifier = str_replace('.', '/', $identifier);
        }

        $segments = explode('/', $identifier);
        $sliceName = array_pop($segments);
        $subdirectoryPath = implode('/', $segments);

        return [$subdirectoryPath, $sliceName];
    }
}
