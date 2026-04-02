<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Test\Double\ExtensionFake;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;

final class SliceServiceProviderTest extends TestCase
{
    /** @var array<string> */
    private array $hooksCalled = [];

    private ?string $tempConfigTestPath = null;

    protected function tearDown(): void
    {
        $this->tearDownConfigTest();

        parent::tearDown();
    }

    private function createTestProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('test-slice');
            }
        };
    }

    private function createEmptyProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                // Intentionally empty to test exception
            }
        };
    }

    private function createProviderWithExtension(ExtensionFake $extension): SliceServiceProvider
    {
        return new class($this->app, $extension) extends SliceServiceProvider {
            public ExtensionFake $extension;

            public function __construct($app, ExtensionFake $extension)
            {
                parent::__construct($app);

                $this->extension = $extension;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('extension-slice')
                    ->withExtension($this->extension);
            }
        };
    }


    private function tearDownConfigTest(): void
    {
        if ($this->tempConfigTestPath !== null && is_dir($this->tempConfigTestPath))
        {
            $filesystem = new \Illuminate\Filesystem\Filesystem();
            $filesystem->deleteDirectory($this->tempConfigTestPath);
        }
    }

    private function createConfigDirectory(): string
    {
        $this->tempConfigTestPath = sys_get_temp_dir() . '/laravel-slice-config-test-' . uniqid();
        $configDir = $this->tempConfigTestPath . '/config';

        mkdir($configDir, 0777, true);

        file_put_contents(
            $configDir . '/app.php',
            "<?php\n\nreturn [\n    'route_prefix' => 'admin',\n    'enabled' => true,\n];"
        );

        return $this->tempConfigTestPath;
    }

    private function createConfigTestProvider(string $tempBasePath): SliceServiceProvider
    {
        return new class($this->app, $tempBasePath) extends SliceServiceProvider {
            private string $tempBasePath;

            public function __construct($app, string $tempBasePath)
            {
                $this->tempBasePath = $tempBasePath;
                parent::__construct($app);
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('config-test-slice');
            }

            protected function getSliceBaseDir(): string
            {
                // Return a src subdirectory so dirname() gives us tempBasePath as the slice root
                return $this->tempBasePath . '/src';
            }
        };
    }

    #[Test]
    public function it_registers_a_slice_with_name(): void
    {
        $provider = $this->createTestProvider();

        $provider->register();

        $this->assertTrue(SliceRegistry::has('test-slice'));
    }

    #[Test]
    public function it_fails_to_register_slice_when_slice_name_is_not_defined(): void
    {
        $this->expectException(SliceNotRegistered::class);

        $provider = $this->createEmptyProvider();
        $provider->register();
    }

    #[Test]
    public function it_registers_config_during_register_phase(): void
    {
        $tempBasePath = $this->createConfigDirectory();
        $provider = $this->createConfigTestProvider($tempBasePath);

        $provider->register();

        $this->assertSame('admin', config('config-test-slice::app.route_prefix'));
        $this->assertTrue(config('config-test-slice::app.enabled'));
    }

    #[Test]
    public function it_boots_a_slice(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        $provider->boot();

        $this->assertTrue(SliceRegistry::has('test-slice'));
    }

    #[Test]
    public function it_registers_extensions_when_booting(): void
    {
        $extension = new ExtensionFake();
        $provider = $this->createProviderWithExtension($extension);
        $provider->register();

        $this->assertFalse($extension->registered);

        $provider->boot();

        $this->assertTrue($extension->registered);
    }

    #[Test]
    public function it_sets_slice_path_from_provider_location(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        $fileName = (new \ReflectionClass($provider))->getFileName();
        $this->assertNotFalse($fileName);
        $expectedPath = dirname(dirname($fileName));
        $this->assertSame($expectedPath, $slice->path());
    }

    #[Test]
    public function it_sets_base_namespace_from_provider_namespace(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        $expectedNamespace = (new \ReflectionClass($provider))->getNamespaceName();
        $this->assertSame($expectedNamespace, $slice->namespace());
    }

    #[Test]
    public function it_calls_lifecycle_hooks_in_correct_order(): void
    {
        $hooksCalled = &$this->hooksCalled;

        $provider = new class($this->app, $hooksCalled) extends SliceServiceProvider {
            /**
             * @var array<string>
             * @phpstan-ignore property.onlyWritten
             */
            private array $hooksCalled;

            /** @param array<string> $hooksCalled */
            public function __construct($app, array &$hooksCalled = [])
            {
                parent::__construct($app);
                $this->hooksCalled = &$hooksCalled;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('hooks-test');
            }

            public function registeringSlice(): void
            {
                $this->hooksCalled[] = 'registeringSlice';
            }

            public function sliceRegistered(): void
            {
                $this->hooksCalled[] = 'sliceRegistered';
            }

            public function bootingSlice(): void
            {
                $this->hooksCalled[] = 'bootingSlice';
            }

            public function sliceBooted(): void
            {
                $this->hooksCalled[] = 'sliceBooted';
            }
        };

        $provider->register();
        $provider->boot();

        $this->assertSame([
            'registeringSlice',
            'sliceRegistered',
            'bootingSlice',
            'sliceBooted'
        ], $this->hooksCalled);
    }

    #[Test]
    public function it_registers_slice_with_multiple_extensions(): void
    {
        $extension1 = new ExtensionFake();
        $extension2 = new ExtensionFake();

        $provider = new class($this->app, $extension1, $extension2) extends SliceServiceProvider {
            private ExtensionFake $extension1;
            private ExtensionFake $extension2;

            public function __construct($app, ExtensionFake $extension1, ExtensionFake $extension2)
            {
                parent::__construct($app);
                $this->extension1 = $extension1;
                $this->extension2 = $extension2;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('multi-extension-slice')
                    ->withExtension($this->extension1)
                    ->withExtension($this->extension2);
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        $provider->register();
        $provider->boot();

        /** @disregard P1013 method is defined on anonymous class */
        $slice = $provider->getSlice();

        $this->assertSame('multi-extension-slice', $slice->name());
        $this->assertCount(2, $slice->extensions());
        $this->assertTrue($extension1->registered);
        $this->assertTrue($extension2->registered);
    }

    #[Test]
    public function it_adds_slice_to_registry_on_registration(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        $this->assertTrue(SliceRegistry::has('test-slice'));
        $this->assertInstanceOf(Slice::class, SliceRegistry::get('test-slice'));
    }
}
