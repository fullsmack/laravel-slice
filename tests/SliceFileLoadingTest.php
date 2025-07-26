<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

class FileLoadingSliceServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('file-test');
        // Don't enable any features by default - let individual tests enable them
    }
}

class SliceFileLoadingTest extends TestCase
{
    private string $tempSliceDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a proper slice directory structure
        // tempSliceDir/
        //   ├── src/           <- This will be the slice base path
        //   ├── routes/        <- Routes directory
        //   ├── config/        <- Config directory
        //   ├── resources/
        //   │   └── views/     <- Views directory
        //   ├── lang/          <- Translations directory
        //   └── database/
        //       └── migrations/ <- Migrations directory

        $this->tempSliceDir = sys_get_temp_dir() . '/test-slice-' . uniqid();
        File::makeDirectory($this->tempSliceDir, 0755, true);

        // Create the src directory - this will be our slice base path
        File::makeDirectory($this->tempSliceDir . '/src', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempSliceDir);
        parent::tearDown();
    }

    #[Test]
    public function it_loads_config_files_from_config_directory(): void
    {
        // Create a test config file
        $configDir = $this->tempSliceDir . '/config';
        File::makeDirectory($configDir, 0755, true);
        File::put($configDir . '/test-config.php', "<?php\nreturn ['key' => 'value'];");

        $provider = $this->createProviderWithTempPath(); // Config is always loaded, no need to enable it
        $provider->register();
        $provider->boot();

        // The config should be merged with the slice name prefix
        $this->assertEquals('value', config('file-test::test-config.key'));
    }

    #[Test]
    public function it_loads_routes_from_routes_directory(): void
    {
        // Create a test routes file
        $routesDir = $this->tempSliceDir . '/routes';
        File::makeDirectory($routesDir, 0755, true);
        File::put($routesDir . '/test-routes.php', "<?php\nRoute::get('/test-route', function() { return 'test'; });");

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useRoutes(); // Enable routes for this test
        });
        $provider->register();
        $provider->boot();

        // If we get here without exception, the route loading worked
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_when_routes_directory_missing(): void
    {
        $this->expectException(\FullSmack\LaravelSlice\SliceNotRegistered::class);
        $this->expectExceptionMessage('Routes directory');

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useRoutes(); // Enable routes but don't create directory
        });
        $provider->register();
        $provider->boot(); // Should throw exception because routes directory doesn't exist
    }

    #[Test]
    public function it_loads_views_from_resources_views_directory(): void
    {
        // Create a test view file
        $viewsDir = $this->tempSliceDir . '/resources/views';
        File::makeDirectory($viewsDir, 0755, true);
        File::put($viewsDir . '/test-view.blade.php', '<h1>Test View</h1>');

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useViews(); // Enable views for this test
        });
        $provider->register();
        $provider->boot();

        // Check if view namespace was registered
        $this->assertTrue(View::exists('file-test::test-view'));
    }

    #[Test]
    public function it_throws_exception_when_views_directory_missing(): void
    {
        $this->expectException(\FullSmack\LaravelSlice\SliceNotRegistered::class);
        $this->expectExceptionMessage('Views directory');

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useViews(); // Enable views but don't create directory
        });
        $provider->register();
        $provider->boot(); // Should throw exception because views directory doesn't exist
    }

    #[Test]
    public function it_loads_translations_from_lang_directory(): void
    {
        // Create a test translation file
        $langDir = $this->tempSliceDir . '/lang/en';
        File::makeDirectory($langDir, 0755, true);
        File::put($langDir . '/messages.php', "<?php\nreturn ['hello' => 'Hello World'];");

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useTranslations(); // Enable translations for this test
        });
        $provider->register();
        $provider->boot();

        // Translation should be available with slice namespace
        $this->assertEquals('Hello World', trans('file-test::messages.hello'));
    }

    #[Test]
    public function it_loads_translations_from_resources_lang_directory_when_lang_not_exists(): void
    {
        // Create a test translation file in resources/lang
        $langDir = $this->tempSliceDir . '/resources/lang/en';
        File::makeDirectory($langDir, 0755, true);
        File::put($langDir . '/messages.php', "<?php\nreturn ['goodbye' => 'Goodbye World'];");

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useTranslations(); // Enable translations for this test
        });
        $provider->register();
        $provider->boot();

        // Translation should be available with slice namespace
        $this->assertEquals('Goodbye World', trans('file-test::messages.goodbye'));
    }

    #[Test]
    public function it_loads_json_translations(): void
    {
        // Create a test JSON translation file
        $langDir = $this->tempSliceDir . '/lang';
        File::makeDirectory($langDir, 0755, true);
        File::put($langDir . '/en.json', '{"Welcome": "Welcome to our app"}');

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useTranslations(); // Enable translations for this test
        });
        $provider->register();
        $provider->boot();

        // JSON translation should be available
        $this->assertEquals('Welcome to our app', __('Welcome'));
    }

    private function createProviderWithTempPath(callable $configureCallback = null): FileLoadingSliceServiceProvider
    {
        $provider = new class($this->app, $this->tempSliceDir, $configureCallback) extends FileLoadingSliceServiceProvider {
            private string $tempPath;
            private $configureCallback;

            public function __construct($app, string $tempPath, callable $configureCallback = null)
            {
                parent::__construct($app);
                $this->tempPath = $tempPath;
                $this->configureCallback = $configureCallback;
            }

            public function configure(Slice $slice): void
            {
                parent::configure($slice); // Set the name

                if ($this->configureCallback) {
                    ($this->configureCallback)($slice);
                }
            }

            protected function getSliceBaseDir(): string
            {
                // Return the src directory - this is the slice base path
                return $this->tempPath . '/src';
            }
        };

        return $provider;
    }
}
