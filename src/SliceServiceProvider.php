<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use ReflectionClass;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Database\Eloquent\Model;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Feature;

abstract class SliceServiceProvider extends ServiceProvider
{
    protected Slice $slice;
    private ReflectionClass $reflector;
    private Filesystem $filesystem;

    abstract public function configure(Slice $slice): void;

    protected function newSlice(): Slice
    {
        return new Slice();
    }

    /**
     * @return static
     */
    public function register()
    {
        $this->reflector = $this->getReflector();

        $this->filesystem = $this->app->make(Filesystem::class);

        $this->registeringSlice();

        $this->slice = $this->newSlice();

        $this->slice->setBasePath($this->getSliceBaseDir());

        $this->slice->setBaseNamespace($this->getSliceBaseNamespace());

        $this->configure($this->slice);

        if($this->slice->name() === '')
        {
            throw SliceNotRegistered::becauseNameIsNotDefined();
        }

        Slice::register($this->slice);

        $this->sliceRegistered();

        return $this;
    }

    public function boot()
    {
        $this->bootingSlice();

        $this->registerConfig();

        if($this->slice->hasRoutes())
        {
            $this->registerRoutes();
        }

        if($this->slice->hasTranslations())
        {
            $this->registerTranslations();
        }

        if($this->slice->hasViews())
        {
            $this->registerViews();
        }

        $this->registerFeatures();

        if($this->app->runningInConsole())
        {
            $commands = $this->slice->commands();

            if($commands !== [])
            {
                $this->commands($commands);
            }

            if($this->slice->hasMigrations() && !$this->slice->usesConnection())
            {
                $this->registerMigrations();
            }
        }

        $this->sliceBooted();

        return $this;
    }

    protected function registerConfig(): void
    {
        $configDirectory = $this->slice->basePath('/../config');

        if($this->filesystem->isDirectory($configDirectory))
        {
            $files = $this->filesystem->allFiles($configDirectory.'/');

            Collection::make($files)
                ->map(static function (SplFileInfo $file): string {
                    return (string) Str::of($file->getRelativePathname())
                        ->before('.');
                })
                ->each(function(string $configFileName)
                    use($configDirectory): void {
                        $this->mergeConfigFrom(
                            "{$configDirectory}/{$configFileName}.php",
                            "{$this->slice->name()}::{$configFileName}"
                        );
                }
            );
        }
    }

    protected function registerRoutes(): void
    {
        $routesDirectory = $this->slice->basePath('/../routes');

        $routeFiles = $this->directoryFiles($routesDirectory);

        $routeFiles->each(function($routeFile) use($routesDirectory): void {
            $this->loadRoutesFrom("{$routesDirectory}/{$routeFile}");
        });
    }

    protected function registerTranslations(): void
    {
        $slicePath = $this->slice->basePath('/..');

        $hasLangDir = is_dir($slicePath .'/lang');

        $langPath = $slicePath . ($hasLangDir ? '/lang' : '/resources/lang');

        $this->loadTranslationsFrom($langPath, $this->slice->name());

        $this->loadJsonTranslationsFrom($langPath, $this->slice->name());
    }

    protected function registerViews(): void
    {
        $viewDirectory = $this->slice->basePath('/../resources/views');

        $viewPaths = [
            $viewDirectory,
        ];

        $this->loadViewsFrom($viewPaths, $this->slice->name());
    }

    protected function registerMigrations(): void
    {
        $migrationDirectory = $this->slice->basePath('/../database/migrations');

        $this->loadMigrationsFrom($migrationDirectory);
    }

    protected function registerFeatures(): void
    {
        foreach($this->slice->features() as $feature)
        {
            if($feature instanceof Feature)
            {
                $feature->register($this->slice);
            }
        }
    }

    /**
     * @param string $path
     * @return Collection<int, string>
     */
    private function directoryFiles(string $path): Collection
    {
        return Collection::make($this->filesystem->files($path.'/'))
            ->map(
                static fn(SplFileInfo $file): string => $file->getRelativePathname()
            );
    }

    protected function getReflector(): ReflectionClass
    {
        return new ReflectionClass($this::class);
    }

    protected function getSliceBaseDir(): string
    {
        return dirname($this->reflector->getFileName());
    }

    protected function getSliceBaseNamespace(): string
    {
        return $this->reflector->getNamespaceName();
    }

    public function registeringSlice(): void
    {
        //
    }

    public function sliceRegistered(): void
    {
        //
    }

    public function bootingSlice(): void
    {
        //
    }

    public function sliceBooted(): void
    {
        //
    }

    /**
     * Bind the slice's connection to multiple model classes.
     *
     * This sets the default connection for all specified models to use
     * the connection defined via $slice->useConnection().
     *
     * Models must use the UsesConnection trait for this to work.
     *
     * @param class-string<Model> ...$modelClasses
     */
    protected function bindModelsToConnection(string ...$modelClasses): void
    {
        if (!$this->slice->usesConnection())
        {
            return;
        }

        $connection = $this->slice->connection();

        foreach ($modelClasses as $modelClass)
        {
            if (method_exists($modelClass, 'useConnection'))
            {
                $modelClass::useConnection($connection);
            }
        }
    }
}
