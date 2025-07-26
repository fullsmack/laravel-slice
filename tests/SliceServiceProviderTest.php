<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Filesystem\Filesystem;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\Feature;

class TestSliceServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('test-slice');
        // Don't enable features that require directories for basic tests
    }
}

class EmptySliceServiceProvider extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        // Intentionally empty to test exception
    }
}

class TestFeature implements Feature
{
    public bool $registered = false;

    public function register(Slice $slice): void
    {
        $this->registered = true;
    }
}

class SliceServiceProviderWithFeature extends SliceServiceProvider
{
    public TestFeature $feature;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->feature = new TestFeature();
    }

    public function configure(Slice $slice): void
    {
        $slice->setName('feature-slice')
            ->withFeature($this->feature);
    }
}

class SliceServiceProviderTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_slice_name_is_not_defined(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage(
            'This slice does not have a name.' .
            'You can set one with `$slice->setName("slice-name")`'
        );

        $provider = new EmptySliceServiceProvider($this->app);
        $provider->register();
    }

    #[Test]
    public function it_can_register_a_slice_with_name(): void
    {
        $provider = new TestSliceServiceProvider($this->app);

        $result = $provider->register();

        $this->assertSame($provider, $result);
    }

    #[Test]
    public function it_can_boot_a_slice(): void
    {
        $provider = new TestSliceServiceProvider($this->app);
        $provider->register();

        $result = $provider->boot();

        $this->assertSame($provider, $result);
    }

    #[Test]
    public function it_registers_features_when_booting(): void
    {
        $provider = new SliceServiceProviderWithFeature($this->app);
        $provider->register();

        $this->assertFalse($provider->feature->registered);

        $provider->boot();

        $this->assertTrue($provider->feature->registered);
    }

    #[Test]
    public function it_sets_base_path_from_provider_location(): void
    {
        $provider = new TestSliceServiceProvider($this->app);
        $provider->register();

        // Use reflection to access the protected slice property
        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        $expectedPath = dirname((new \ReflectionClass(TestSliceServiceProvider::class))->getFileName());
        $this->assertSame($expectedPath, $slice->basePath());
    }

    #[Test]
    public function it_sets_base_namespace_from_provider_namespace(): void
    {
        $provider = new TestSliceServiceProvider($this->app);
        $provider->register();

        // Use reflection to access the protected slice property
        $reflection = new \ReflectionClass($provider);
        $sliceProperty = $reflection->getProperty('slice');
        $sliceProperty->setAccessible(true);
        $slice = $sliceProperty->getValue($provider);

        $expectedNamespace = (new \ReflectionClass(TestSliceServiceProvider::class))->getNamespaceName();
        $this->assertSame($expectedNamespace, $slice->baseNamespace());
    }
}

class SliceServiceProviderHooksTest extends TestCase
{
    private array $hooksCalled = [];

    #[Test]
    public function it_calls_lifecycle_hooks_in_correct_order(): void
    {
        $provider = new class($this->app, $this->hooksCalled) extends SliceServiceProvider {
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
}
