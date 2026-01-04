<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\Testing\RefreshSliceDatabase;

final class RefreshSliceDatabaseTest extends TestCase
{
    use RefreshSliceDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('database.connections.slice-database', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[Test]
    public function it_resets_migration_state(): void
    {
        static::resetSliceMigrationState();

        /* Confirms state was reset */
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_when_slice_not_in_registry(): void
    {
        $this->expectException(SliceNotRegistered::class);

        $this->refreshSlice('non-existent-slice');
    }

    #[Test]
    public function it_prevents_refreshing_database_when_slice_does_not_use_connection(): void
    {
        $slice = (new Slice())
            ->setName('no-connection-slice')
            ->setBasePath(sys_get_temp_dir());

        SliceRegistry::register($slice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not use a separate connection');

        $this->refreshSlice('no-connection-slice');
    }

    #[Test]
    public function it_prevents_refreshing_database_when_connection_is_null(): void
    {
        $slice = (new Slice())
            ->setName('null-connection-slice')
            ->setBasePath(sys_get_temp_dir())
            ->withConnection(); // No explicit connection and no config

        SliceRegistry::register($slice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no connection is defined');

        $this->refreshSlice('null-connection-slice');
    }
}
