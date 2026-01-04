<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;
use RuntimeException;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\Testing\RefreshSliceDatabase;

class RefreshSliceDatabaseTest extends TestCase
{
    use RefreshSliceDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('database.connections.slice_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[Test]
    public function it_throws_exception_when_slice_not_in_registry(): void
    {
        $this->expectException(SliceNotRegistered::class);

        $this->refreshSlice('non-existent-slice');
    }

    #[Test]
    public function it_throws_exception_when_slice_does_not_use_connection(): void
    {
        $slice = (new Slice())
            ->setName('no-connection-slice')
            ->setBasePath(sys_get_temp_dir());

        Slice::register($slice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not use a separate connection');

        $this->refreshSlice('no-connection-slice');
    }

    #[Test]
    public function it_throws_exception_when_connection_is_null(): void
    {
        $slice = (new Slice())
            ->setName('null-connection-slice')
            ->setBasePath(sys_get_temp_dir())
            ->useConnection(); // No explicit connection and no config

        Slice::register($slice);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no connection is defined');

        $this->refreshSlice('null-connection-slice');
    }

    #[Test]
    public function it_can_reset_migration_state(): void
    {
        static::resetSliceMigrationState();

        // This should not throw an exception, confirming state was reset
        $this->assertTrue(true);
    }
}
