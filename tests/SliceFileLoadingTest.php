<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;

final class SliceFileLoadingTest extends TestCase
{
    private string $tempSliceDir;

    protected function setUp(): void
    {
        parent::setUp();

        /* Creates a proper slice directory structure */
        /*
            tempSliceDir/
               ├── config/            <- Config directory
               ├── database/
               │   └── migrations/    <- Migrations directory
               ├── lang/              <- Translations directory
               ├── resources/
               │   └── views/         <- Views directory
               ├── routes/            <- Routes directory
               ├── src/               <- This will be the slice base path
               └── tests/             <- Tests directory
        */

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
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_when_routes_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
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

    #[Test]
    public function it_loads_migrations_from_database_migrations_directory(): void
    {
        // Create a test migration file
        $migrationsDir = $this->tempSliceDir . '/database/migrations';
        File::makeDirectory($migrationsDir, 0755, true);
        File::put($migrationsDir . '/2024_01_01_000000_create_test_table.php', "<?php\nuse Illuminate\Database\Migrations\Migration;\nclass CreateTestTable extends Migration {\n    public function up() {}\n    public function down() {}\n}");

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useMigrations(); // Enable migrations for this test
        });
        $provider->register();
        $provider->boot();

        // If we get here without exception, the migration loading worked
        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_load_migrations_globally_when_slice_uses_connection(): void
    {
        // Create a test migration file
        $migrationsDir = $this->tempSliceDir . '/database/migrations';
        File::makeDirectory($migrationsDir, 0755, true);
        File::put($migrationsDir . '/2024_01_01_000000_create_test_table.php', "<?php\nuse Illuminate\Database\Migrations\Migration;\nclass CreateTestTable extends Migration {\n    public function up() {}\n    public function down() {}\n}");

        $provider = $this->createProviderWithTempPath(function($slice) {
            $slice->useMigrations(); // Enable migrations for this test
            $slice->withConnection('custom-connection'); // Also use a custom connection
        });
        $provider->register();
        $provider->boot();

        // Verify that the migration directory was NOT added to the migrator's paths
        // (slices with custom connections should not register migrations globally)
        $migrator = $this->app->make('migrator');
        $paths = $migrator->paths();

        $expectedPath = realpath($migrationsDir);
        $actualPathsResolved = array_map('realpath', $paths);

        $this->assertNotContains($expectedPath, $actualPathsResolved, 'Migration directory should NOT be registered globally when slice uses a custom connection');
    }

    private function createProviderWithTempPath(?callable $configureCallback = null): SliceServiceProvider
    {
        return new class($this->app, $this->tempSliceDir, $configureCallback) extends SliceServiceProvider {
            private string $tempPath;
            /** @var callable|null */
            private $configureCallback;

            public function __construct($app, string $tempPath, ?callable $configureCallback = null)
            {
                parent::__construct($app);
                $this->tempPath = $tempPath;
                $this->configureCallback = $configureCallback;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('file-test');

                if ($this->configureCallback)
                {
                    ($this->configureCallback)($slice);
                }
            }

            protected function getSliceBaseDir(): string
            {
                // Return the src directory - this is the slice base path
                return $this->tempPath . '/src';
            }
        };
    }
}
