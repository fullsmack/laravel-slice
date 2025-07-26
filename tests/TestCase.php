<?php
declare(strict_types=1);

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use FullSmack\LaravelSlice\LaravelSliceServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelSliceServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
    }
}
