<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\Feature;

/**
 * Mock-based testing approach for directory-dependent features
 * This demonstrates how to test slice functionality without requiring real directories
 */
class MockBasedSliceTest extends TestCase
{
    #[Test]
    public function it_can_test_slice_configuration_without_directories(): void
    {
        // Mock filesystem in the container before provider registration
        $mockFilesystem = $this->createMock(Filesystem::class);
        $this->app->instance(Filesystem::class, $mockFilesystem);

        // Create a test feature
        $testFeature = new class MockBasedSliceTest Feature {
            public bool $registered = false;
            public function register(Slice $slice): void {
                $this->registered = true;
            }
        };

        // Create provider that enables ALL features
        $provider = new class($this->app, $testFeature) extends SliceServiceProvider {
            private $testFeature;

            public function __construct($app, $testFeature)
            {
                parent::__construct($app);
                $this->testFeature = $testFeature;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('complete-mock-slice')
                    ->useRoutes()
                    ->useViews()
                    ->useTranslations()
                    ->useMigrations()
                    ->withFeature($this->testFeature);
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }

            public function getFeature()
            {
                return $this->testFeature;
            }
        };

        // Configure mock to return false for all directory checks
        // This simulates the scenario where no directories exist
        $mockFilesystem->method('isDirectory')->willReturn(false);

        // Register and boot the provider
        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        // Verify slice configuration was set correctly
        $this->assertSame('complete-mock-slice', $slice->name());
        $this->assertTrue($slice->hasRoutes());
        $this->assertTrue($slice->hasViews());
        $this->assertTrue($slice->hasTranslations());
        $this->assertTrue($slice->hasMigrations());
        $this->assertCount(1, $slice->features());

        // Verify feature was registered
        $this->assertTrue($provider->getFeature()->registered);
    }

    #[Test]
    public function it_can_mock_directory_structure_with_files(): void
    {
        // Mock filesystem in the container
        $mockFilesystem = $this->createMock(Filesystem::class);
        $this->app->instance(Filesystem::class, $mockFilesystem);

        $provider = new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('mock-with-files');
                // Only enable features we want to test
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        // Mock config directory exists with files
        $mockFilesystem->expects($this->once())
            ->method('isDirectory')
            ->willReturn(true);

        // Mock config files
        $mockConfigFiles = [
            $this->createMockSplFileInfo('database.php'),
            $this->createMockSplFileInfo('services.php'),
        ];

        $mockFilesystem->expects($this->once())
            ->method('allFiles')
            ->willReturn($mockConfigFiles);

        // Register and boot
        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();
        $this->assertSame('mock-with-files', $slice->name());
    }

    #[Test]
    public function it_demonstrates_feature_testing_without_filesystem(): void
    {
        // This test shows how to test slice features independent of filesystem operations
        $slice = new Slice();

        // Test feature that doesn't require directories
        $mockFeature = new class MockBasedSliceTest Feature {
            public array $calls = [];

            public function register(Slice $slice): void {
                $this->calls[] = ['method' => 'register', 'slice_name' => $slice->name()];
            }
        };

        // Configure slice
        $slice->setName('feature-test')
            ->setBasePath('/fake/path')
            ->setBaseNamespace('App\\Slices\\FeatureTest')
            ->withFeature($mockFeature);

        // Simulate feature registration (what would happen in boot())
        foreach ($slice->features() as $feature) {
            $feature->register($slice);
        }

        // Verify feature was called correctly
        $this->assertCount(1, $mockFeature->calls);
        $this->assertSame('register', $mockFeature->calls[0]['method']);
        $this->assertSame('feature-test', $mockFeature->calls[0]['slice_name']);
    }

    #[Test]
    public function it_shows_slice_configuration_api_patterns(): void
    {
        // This demonstrates the fluent API patterns that slices support
        $slice = new Slice();

        // Test method chaining
        $result = $slice->setName('api-test')
            ->useRoutes()
            ->useViews()
            ->useTranslations()
            ->useMigrations();

        // Verify fluent interface MockBasedSliceTest same instance
        $this->assertSame($slice, $result);

        // Test path building
        $slice->setBasePath('/base/path');
        $this->assertSame('/base/path', $slice->basePath());
        $this->assertSame('/base/path'.DIRECTORY_SEPARATOR.'routes', $slice->basePath('/routes'));
        $this->assertSame('/base/path'.DIRECTORY_SEPARATOR.'config', $slice->basePath('/config'));

        // Test namespace building
        $slice->setBaseNamespace('App\\Slices\\ApiTest');
        $this->assertSame('App\\Slices\\ApiTest', $slice->baseNamespace());
        $this->assertSame('App\\Slices\\ApiTest\\Controllers', $slice->baseNamespace('Controllers'));
    }

    /**
     * Create a mock SplFileInfo object for testing
     */
    private function createMockSplFileInfo(string $filename): SplFileInfo
    {
        $mock = $this->createMock(SplFileInfo::class);
        $mock->method('getRelativePathname')->willReturn($filename);
        return $mock;
    }
}
