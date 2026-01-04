<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\LaravelSliceServiceProvider;

final class LaravelSliceServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_package_config(): void
    {
        $this->assertNotNull(config('laravel-slice'));
        $this->assertIsArray(config('laravel-slice'));
    }

    #[Test]
    public function it_has_root_folder_config(): void
    {
        $this->assertNotNull(config('laravel-slice.root.folder'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSliceServiceProvider::class,
        ];
    }
}
