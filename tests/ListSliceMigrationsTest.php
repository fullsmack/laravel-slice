<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Filesystem\Filesystem;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

final class ListSliceMigrationsTest extends TestCase
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
    public function lists_all_registered_slices(): void
    {
        // Create slice paths with migrations directories
        $slice1Path = $this->tempPath . '/slice-one';
        $slice2Path = $this->tempPath . '/slice-two';

        mkdir($slice1Path . '/database/migrations', 0777, true);
        mkdir($slice2Path, 0777, true);

        $slice1 = (new Slice())
            ->setName('slice-one')
            ->setPath($slice1Path)
            ->useConnection('connection-one');

        $slice2 = (new Slice())
            ->setName('slice-two')
            ->setPath($slice2Path);

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);

        $exitCode = Artisan::call('slice:list-migrations');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('slice-one', $output);
        $this->assertStringContainsString('slice-two', $output);
        $this->assertStringContainsString('connection-one', $output);
        $this->assertStringContainsString('(default)', $output);
    }

    #[Test]
    public function shows_empty_message_when_no_slices(): void
    {
        $exitCode = Artisan::call('slice:list-migrations');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No slices registered', $output);
    }

    #[Test]
    public function correctly_identifies_migration_directories(): void
    {
        // Create one slice with migrations, one without
        $sliceWithMigrations = $this->tempPath . '/with-migrations';
        $sliceWithoutMigrations = $this->tempPath . '/without-migrations';

        mkdir($sliceWithMigrations . '/database/migrations', 0777, true);
        mkdir($sliceWithoutMigrations, 0777, true);

        $slice1 = (new Slice())
            ->setName('with-migrations')
            ->setPath($sliceWithMigrations);

        $slice2 = (new Slice())
            ->setName('without-migrations')
            ->setPath($sliceWithoutMigrations);

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);

        $exitCode = Artisan::call('slice:list-migrations');
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        // The table should show checkmark for with-migrations and X for without-migrations
        $this->assertStringContainsString('with-migrations', $output);
        $this->assertStringContainsString('without-migrations', $output);
    }
}
