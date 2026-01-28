<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

final class MigrateSliceAllSlicesTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/laravel-slice-test-' . uniqid();
        mkdir($this->tempPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    #[Test]
    public function cannot_use_slice_and_all_slices_together(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--slice' => 'test-slice',
            '--all-slices' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('Cannot use --slice and --all-slices together', $output);
        $this->assertEquals(1, $exitCode);
    }

    #[Test]
    public function all_slices_shows_message_when_no_slices_with_connections(): void
    {
        // Register a slice without a connection
        $slicePath = $this->tempPath . '/no-connection-slice';
        mkdir($slicePath, 0777, true);

        $slice = (new Slice())
            ->setName('no-connection-slice')
            ->setPath($slicePath);

        SliceRegistry::register($slice);

        $exitCode = Artisan::call('migrate', [
            '--all-slices' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('No slices with custom connections registered', $output);
        $this->assertEquals(0, $exitCode);
    }

    #[Test]
    public function all_slices_skips_slices_without_migration_directory(): void
    {
        // Configure a secondary database connection
        config()->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // Register a slice with a connection but no migrations directory
        $slicePath = $this->tempPath . '/no-migrations-slice';
        mkdir($slicePath, 0777, true);

        $slice = (new Slice())
            ->setName('no-migrations-slice')
            ->setPath($slicePath)
            ->useConnection('secondary');

        SliceRegistry::register($slice);

        $exitCode = Artisan::call('migrate', [
            '--all-slices' => true,
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('No migrations directory found, skipping', $output);
        $this->assertEquals(0, $exitCode);
    }
}
