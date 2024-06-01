<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Illuminate\Support\ServiceProvider;

use FullSmack\LaravelSlice\Command\MakeSlice;
use FullSmack\LaravelSlice\Command\MakeTest;

class LaravelSliceServiceProvider extends ServiceProvider
{
    protected $commands = [
        MakeSlice::class,
        MakeTest::class,
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
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

    protected function registerCommands(): void
    {
        if($this->app->runningInConsole())
        {
            $this->commands($this->commands);
        }
    }
}
