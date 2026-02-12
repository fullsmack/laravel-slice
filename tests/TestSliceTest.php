<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

final class TestSliceTest extends TestCase
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
    public function errors_when_slice_not_registered(): void
    {
        $exitCode = Artisan::call('slice:test', [
            'sliceName' => 'non-existent-slice',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('not registered', $output);
        $this->assertEquals(1, $exitCode);
    }

    #[Test]
    public function errors_when_slice_has_no_tests_directory(): void
    {
        // Create slice path without tests directory
        $slicePath = $this->tempPath . '/no-tests-slice';
        mkdir($slicePath, 0777, true);

        $slice = (new Slice())
            ->setName('no-tests-slice')
            ->setPath($slicePath)
            ->setNamespace('Slice\\NoTestsSlice');

        SliceRegistry::register($slice);

        $exitCode = Artisan::call('slice:test', [
            'sliceName' => 'no-tests-slice',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('No tests directory found', $output);
        $this->assertEquals(1, $exitCode);
    }


}
