<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Filesystem\Filesystem;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\Test\Double\FeatureFake;

class SliceServiceProviderTest extends TestCase
{
    private array $hooksCalled = [];

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

    private function createProviderWithFeature(FeatureFake $feature): SliceServiceProvider
    {
        return new class($this->app, $feature) extends SliceServiceProvider {
            public FeatureFake $feature;

            public function __construct($app, FeatureFake $feature)
            {
                parent::__construct($app);
                $this->feature = $feature;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('feature-slice')
                    ->withFeature($this->feature);
            }
        };
    }

    #[Test]
    public function it_registers_a_slice_with_name(): void
    {
        $provider = $this->createTestProvider();

        $result = $provider->register();

        $this->assertSame($provider, $result);
    }

    #[Test]
    public function it_fails_to_register_slice_when_slice_name_is_not_defined(): void
    {
        $this->expectException(SliceNotRegistered::class);

        $provider = $this->createEmptyProvider();
        $provider->register();
    }

    #[Test]
    public function it_boots_a_slice(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        $result = $provider->boot();

        $this->assertSame($provider, $result);
    }

    #[Test]
    public function it_registers_features_when_booting(): void
    {
        $feature = new FeatureFake();
        $provider = $this->createProviderWithFeature($feature);
        $provider->register();

        $this->assertFalse($feature->registered);

        $provider->boot();

        $this->assertTrue($feature->registered);
    }

    #[Test]
    public function it_sets_base_path_from_provider_location(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        // Use reflection to access the protected slice property
        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        // Anonymous classes are defined in this test file
        $expectedPath = dirname((new \ReflectionClass($provider))->getFileName());
        $this->assertSame($expectedPath, $slice->basePath());
    }

    #[Test]
    public function it_sets_base_namespace_from_provider_namespace(): void
    {
        $provider = $this->createTestProvider();
        $provider->register();

        // Use reflection to access the protected slice property
        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        $expectedNamespace = (new \ReflectionClass($provider))->getNamespaceName();
        $this->assertSame($expectedNamespace, $slice->baseNamespace());
    }

    #[Test]
    public function it_calls_lifecycle_hooks_in_correct_order(): void
    {
        $hooksCalled = &$this->hooksCalled;

        $provider = new class($this->app, $hooksCalled) extends SliceServiceProvider {
            private array $hooksCalled;

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
    public function it_registers_slice_with_multiple_features(): void
    {
        $feature1 = new FeatureFake();
        $feature2 = new FeatureFake();

        $provider = new class($this->app, $feature1, $feature2) extends SliceServiceProvider {
            private FeatureFake $feature1;
            private FeatureFake $feature2;

            public function __construct($app, FeatureFake $feature1, FeatureFake $feature2)
            {
                parent::__construct($app);
                $this->feature1 = $feature1;
                $this->feature2 = $feature2;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('multi-feature-slice')
                    ->withFeature($this->feature1)
                    ->withFeature($this->feature2);
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        $this->assertSame('multi-feature-slice', $slice->name());
        $this->assertCount(2, $slice->features());
        $this->assertTrue($feature1->registered);
        $this->assertTrue($feature2->registered);
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
