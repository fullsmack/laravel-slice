<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use Orchestra\Testbench\TestCase as Orchestra;
use FullSmack\LaravelSlice\LaravelSliceServiceProvider;
use FullSmack\LaravelSlice\Slice;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Slice::clearRegistry();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSliceServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
