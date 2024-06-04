<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Illuminate\Support\ServiceProvider;

use FullSmack\LaravelSlice\Command\MakeSlice;
use FullSmack\LaravelSlice\Command\MakeTest;
use FullSmack\LaravelSlice\Command\MakeComponent;

class LaravelSliceServiceProvider extends ServiceProvider
{
    protected $commands = [
        MakeSlice::class,
        MakeTest::class,
        MakeComponent::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();

        $this->publishesConfig();

        $this->registerCommands();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    protected function registerConfig()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-slice.php', 'laravel-slice');
    }

    protected function publishesConfig()
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
        if($this->app->runningInConsole())
        {
            $this->commands($this->commands);
        }
    }
}
