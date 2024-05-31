<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use ReflectionClass;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Feature;

abstract class SliceServiceProvider extends ServiceProvider
{
    protected Slice $slice;
    private ReflectionClass $reflector;

    abstract public function configure(Slice $slice): void;

    protected function newSlice(): Slice
    {
        return new Slice();
    }

    public function register()
    {
        $this->reflector = $this->getReflector();

        $this->registeringSlice();

        $this->slice = $this->newSlice();

        $this->slice->setBasePath($this->getSliceBaseDir());

        $this->slice->setBaseNamespace($this->getSliceBaseNamespace());

        $this->configure($this->slice);

        if($this->slice->name() === '')
        {
            throw SliceNotRegistered::becauseNameIsNotDefined();
        }

        $this->registerConfig();

        $this->sliceRegistered();

        return $this;
    }

    public function boot()
    {
        $this->bootingSlice();

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
            if($this->slice->hasMigrations())
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

        $filesystem = $this->app->make(Filesystem::class);

        if($filesystem->isDirectory($configDirectory))
        {
            $fileNames = Collection::make(
                    $filesystem->allFiles($configDirectory.'/')
                )
                ->map(function (SplFileInfo $file): string {
                    return (string) Str::of($file->getRelativePathname())
                        ->before('.');
                });

            $fileNames->each(function(string $configFileName)
                use($configDirectory): void {
                    $this->mergeConfigFrom(
                        "{$configDirectory}/{$configFileName}.php",
                        "{$this->slice->name()}::{$configFileName}"
                    );
            });
        }
    }

    protected function registerMigrations(): void
    {
        $migrationDirectory = $this->slice->basePath('/../database/migrations');

        $this->loadMigrationsFrom($migrationDirectory);
    }

    protected function registerViews(): void
    {
        $viewDirectory = $this->slice->basePath('/../resources/views');

        $viewPaths = [
            $viewDirectory.'/',
        ];

        $this->loadViewsFrom($viewPaths, $this->slice->name());
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

    protected function registerTranslations(): void
    {
        $slicePath = $this->slice->basePath('/..');

        $hasLangDir = is_dir($slicePath .'/lang');

        $langPath = $slicePath . ($hasLangDir ? '/lang' : '/resources/lang');

        $this->loadTranslationsFrom($langPath, $this->slice->name());

        $this->loadJsonTranslationsFrom($langPath, $this->slice->name());
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
}
