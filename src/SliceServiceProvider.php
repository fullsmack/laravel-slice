<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use ReflectionClass;
use FullSmack\LaravelSlice\Database\UsesConnection;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceMisconfigured;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\SliceRegistry;
use Symfony\Component\Finder\SplFileInfo;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;

abstract class SliceServiceProvider extends ServiceProvider
{
    protected Slice $slice;

    /** @var ReflectionClass<self> */
    private ReflectionClass $reflector;
    private Filesystem $filesystem;

    abstract public function configure(Slice $slice): void;

    protected function newSlice(): Slice
    {
        return new Slice();
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->reflector = $this->getReflector();

        $this->filesystem = $this->app->make(Filesystem::class);

        $this->registeringSlice();

        $this->slice = $this->newSlice();

        $this->slice->setPath(dirname($this->getSliceBaseDir()));

        $this->slice->setNamespace($this->getSliceNamespace());

        $this->configure($this->slice);

        if ($this->slice->identifier() === '')
        {
            throw SliceNotRegistered::becauseIdentifierIsNotDefined();
        }

        $this->registerConfig();

        SliceRegistry::register($this->slice);

        $this->sliceRegistered();
    }

    /**
     * @return void
     */
    public function boot()
    {
        $this->bootingSlice();

        if ($this->slice->hasRoutes())
        {
            $this->registerRoutes();
        }

        if ($this->slice->hasTranslations())
        {
            $this->registerTranslations();
        }

        if ($this->slice->hasViews())
        {
            $this->registerViews();
        }

        $this->registerExtensions();

        $this->bindModelsToConnection();

        if ($this->app->runningInConsole())
        {
            $commands = $this->slice->commands();

            if ($commands !== [])
            {
                $this->commands($commands);
            }

            if($this->slice->hasMigrations())
            {
                $this->registerMigrations();
            }
        }

        $this->sliceBooted();
    }

    /**
     * Merge each `config/*.php` file into the `{identifier}::{file}` config key.
     */
    protected function registerConfig(): void
    {
        $configDirectory = $this->slice->path('config');

        $this->phpFiles($configDirectory)->each(function (SplFileInfo $file): void {
            $this->mergeConfigFrom(
                $file->getPathname(),
                "{$this->slice->identifier()}::{$file->getFilenameWithoutExtension()}"
            );
        });
    }

    protected function registerRoutes(): void
    {
        $routesDirectory = $this->slice->path('routes');

        if (!$this->filesystem->isDirectory($routesDirectory))
        {
            throw SliceMisconfigured::becauseRouteDirectoryDoesntExist($routesDirectory);
        }

        $this->phpFiles($routesDirectory)->each(function (SplFileInfo $file): void {
            $this->loadRoutesFrom($file->getPathname());
        });
    }

    protected function registerTranslations(): void
    {
        $languageDirectory = $this->slice->path('lang');

        if (!$this->filesystem->isDirectory($languageDirectory))
        {
            $languageDirectory = $this->slice->path('resources/lang');
        }

        if (!$this->filesystem->isDirectory($languageDirectory))
        {
            throw SliceMisconfigured::becauseTranslationDirectoryDoesntExist($languageDirectory);
        }

        $this->loadTranslationsFrom($languageDirectory, $this->slice->identifier());

        $this->loadJsonTranslationsFrom($languageDirectory);
    }

    protected function registerViews(): void
    {
        $viewDirectory = $this->slice->path('resources/views');

        if (!$this->filesystem->isDirectory($viewDirectory))
        {
            throw SliceMisconfigured::becauseViewDirectoryDoesntExist($viewDirectory);
        }

        $this->loadViewsFrom($viewDirectory, $this->slice->identifier());
    }

    protected function registerMigrations(): void
    {
        $migrationDirectory = $this->slice->migrationPath();

        if (!$this->filesystem->isDirectory($migrationDirectory))
        {
            throw SliceMisconfigured::becauseMigrationDirectoryDoesntExist($migrationDirectory);
        }

        // Only register globally if slice uses default connection
        // Slices with custom connections are migrated via --slice or --all-slices
        if (!$this->slice->usesConnection())
        {
            $this->loadMigrationsFrom($migrationDirectory);
        }
    }

    protected function registerExtensions(): void
    {
        foreach ($this->slice->extensions() as $extension)
        {
            $extension->register($this->slice);
        }
    }

    /**
     * The `.php` files directly inside a directory (non-recursive).
     * Other files such as `.gitkeep` or `README.md` are ignored.
     *
     * @return Collection<int, SplFileInfo>
     */
    private function phpFiles(string $directory): Collection
    {
        if (!$this->filesystem->isDirectory($directory))
        {
            return new Collection();
        }

        return Collection::make($this->filesystem->files($directory))
            ->filter(static fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->values();
    }

    /**
     * @return ReflectionClass<self>
     */
    protected function getReflector(): ReflectionClass
    {
        /** @var ReflectionClass<self> */
        return new ReflectionClass($this::class);
    }

    protected function getSliceBaseDir(): string
    {
        $fileName = $this->reflector->getFileName();

        if ($fileName === false)
        {
            throw new \RuntimeException('Unable to determine slice base directory');
        }

        return dirname($fileName);
    }

    protected function getSliceNamespace(): string
    {
        return $this->reflector->getNamespaceName();
    }

    protected function registeringSlice(): void
    {
        //
    }

    protected function sliceRegistered(): void
    {
        //
    }

    protected function bootingSlice(): void
    {
        //
    }

    protected function sliceBooted(): void
    {
        //
    }

    /**
     * Bind the slice's connection to the model classes passed to
     * $slice->useConnection($connection, $models).
     *
     * Models must use the UsesConnection trait for this to work.
     *
     * @throws SliceMisconfigured when a model does not use the UsesConnection trait
     */
    protected function bindModelsToConnection(): void
    {
        $modelsToBind = $this->slice->modelsToBind();

        if ($modelsToBind === [] || !$this->slice->usesConnection())
        {
            return;
        }

        /** @var string $connection usesConnection() guarantees a string or an exception */
        $connection = $this->slice->connection();

        foreach ($modelsToBind as $modelClass)
        {
            if (!in_array(UsesConnection::class, class_uses_recursive($modelClass), true))
            {
                throw SliceMisconfigured::becauseModelDoesNotUseConnectionTrait(
                    $this->slice->identifier(),
                    $modelClass,
                );
            }

            /** @phpstan-ignore staticMethod.notFound (verified above via the UsesConnection trait) */
            $modelClass::useConnection($connection);
        }
    }
}
