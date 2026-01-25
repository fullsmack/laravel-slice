<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationCreator;
use FullSmack\LaravelSlice\Command\MakeSlice;
use FullSmack\LaravelSlice\Command\MakeTest;
use FullSmack\LaravelSlice\Command\MakeComponent;
use FullSmack\LaravelSlice\Command\MakeMigration;
use FullSmack\LaravelSlice\Command\MigrateSlice;

class LaravelSliceServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string<Command>>
     */
    protected $commands = [
        MakeSlice::class,
        MakeTest::class,
        MakeComponent::class,
    ];

    /**
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();

        $this->publishesConfig();

        $this->registerCommands();
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->registerMigrationServices();
    }

    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-slice.php', 'laravel-slice');
    }

    protected function publishesConfig(): void
    {
        if ($this->app->runningInConsole())
        {
            $this->publishes([
                __DIR__.'/../config/laravel-slice.php' => config_path('laravel-slice.php'),
            ], 'config');
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole())
        {
            $this->commands($this->commands);
        }
    }

    /**
     * The migration-related services register separately to ensure proper stub resolution.
     */
    protected function registerMigrationServices(): void
    {
        if ($this->app->runningInConsole())
        {
            $this->app->singleton('migration.creator', function ($app) {
                return new MigrationCreator($app['files'], $app->basePath('stubs'));
            });

            $this->app->singleton(MakeMigration::class, function ($app) {
                return new MakeMigration($app['migration.creator'], $app['composer']);
            });

            $this->app->singleton(MigrateSlice::class, function ($app) {
                return new MigrateSlice($app['migrator'], $app['events']);
            });

            $this->commands([MakeMigration::class, MigrateSlice::class]);
        }
    }
}
