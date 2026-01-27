<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use FullSmack\LaravelSlice\LaravelSliceServiceProvider;
use FullSmack\LaravelSlice\SliceRegistry;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        SliceRegistry::clear();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSliceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Register migration infrastructure for testing
        // In production, these are provided by Laravel's MigrationServiceProvider
        $app->singleton('migration.repository', function ($app) {
            return new DatabaseMigrationRepository($app['db'], 'migrations');
        });

        $app->singleton('migrator', function ($app) {
            return new Migrator(
                $app['migration.repository'],
                $app['db'],
                $app['files'],
                $app['events'],
            );
        });
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
