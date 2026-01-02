<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\Feature;

/**
 * Mock LivewireComponents feature to demonstrate the pattern
 */
class ExampleUsageTest implements Feature
{
    public bool $registered = false;

    public function register(Slice $slice): void
    {
        $this->registered = true;
    }
}

/**
 * Example slice service provider following the pattern from the user's code
 */
class ExampleUsageTest extends SliceServiceProvider
{
    private MockLivewireComponents $livewireComponents;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->livewireComponents = new MockLivewireComponents();
    }

    public function configure(Slice $slice): void
    {
        $slice->setName('pizza')
            ->withFeature($this->livewireComponents);
        // Don't enable directory-dependent features in tests
    }

    public function sliceRegistered(): void
    {
        // Custom logic after slice is registered
    }

    public function sliceBooted(): void
    {
        // Custom logic after slice is booted
    }

    public function getLivewireComponents(): MockLivewireComponents
    {
        return $this->livewireComponents;
    }

    public function getSlice(): Slice
    {
        return $this->slice;
    }
}

/**
 * Test the example usage pattern
 */
class ExampleUsageTest extends TestCase
{
    #[Test]
    public function it_demonstrates_the_pizza_slice_registration_pattern(): void
    {
        $provider = new PizzaSliceServiceProvider($this->app);

        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        // Assert the slice was configured correctly
        $this->assertSame('pizza', $slice->name());
        $this->assertFalse($slice->hasRoutes());
        $this->assertFalse($slice->hasViews());
        $this->assertFalse($slice->hasTranslations());
        $this->assertFalse($slice->hasMigrations());

        // Assert the feature was registered
        $this->assertCount(1, $slice->features());
        $this->assertInstanceOf(MockLivewireComponents::class, $slice->features()[0]);
        $this->assertTrue($provider->getLivewireComponents()->registered);
    }

    #[Test]
    public function it_supports_fluent_configuration_like_in_the_example(): void
    {
        $slice = new Slice();

        // This mirrors the configuration pattern from the user's example
        $result = $slice->setName('pizza')
            ->withFeature(new MockLivewireComponents());

        // Should support method chaining
        $this->assertSame($slice, $result);

        // Should have all the configured options
        $this->assertSame('pizza', $slice->name());
        $this->assertFalse($slice->hasRoutes());
        $this->assertFalse($slice->hasViews());
        $this->assertFalse($slice->hasTranslations());
        $this->assertFalse($slice->hasMigrations());
        $this->assertCount(1, $slice->features());
    }

    #[Test]
    public function it_allows_custom_lifecycle_hooks(): void
    {
        $hooksCalled = [];

        $provider = new class($this->app, $hooksCalled) extends SliceServiceProvider {
            private array $hooksCalled;

            public function __construct($app, array &$hooksCalled)
            {
                parent::__construct($app);
                $this->hooksCalled = &$hooksCalled;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('hooks-test');
            }

            public function sliceRegistered(): void
            {
                $this->hooksCalled[] = 'sliceRegistered';
            }

            public function sliceBooted(): void
            {
                $this->hooksCalled[] = 'sliceBooted';
            }
        };

        $provider->register();
        $provider->boot();

        $this->assertContains('sliceRegistered', $hooksCalled);
        $this->assertContains('sliceBooted', $hooksCalled);
    }
}
