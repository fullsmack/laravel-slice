<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use FullSmack\LaravelSlice\LaravelSliceServiceProvider;
use FullSmack\LaravelSlice\Command\MakeSlice;
use FullSmack\LaravelSlice\Command\MakeTest;
use FullSmack\LaravelSlice\Command\MakeComponent;

class LaravelSliceServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_commands(): void
    {
        // Test that commands are available by trying to get their help
        $this->artisan('help', ['command_name' => 'make:slice'])->assertExitCode(0);
        $this->artisan('help', ['command_name' => 'make:test'])->assertExitCode(0);
        $this->artisan('help', ['command_name' => 'make:component'])->assertExitCode(0);
    }

    #[Test]
    public function it_merges_package_config(): void
    {
        $this->assertNotNull(config('laravel-slice'));
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelSliceServiceProvider::class,
        ];
    }
}
