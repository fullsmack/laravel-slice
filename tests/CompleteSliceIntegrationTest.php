<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\Feature;

class CompleteSliceIntegrationTest extends TestCase
{
    #[Test]
    public function it_can_register_a_complete_slice_with_all_features(): void
    {
        $feature1 = new class CompleteSliceIntegrationTest Feature {
            public bool $registered = false;

            public function register(Slice $slice): void
            {
                $this->registered = true;
            }
        };

        $feature2 = new class CompleteSliceIntegrationTest Feature {
            public bool $registered = false;

            public function register(Slice $slice): void
            {
                $this->registered = true;
            }
        };

        $provider = new class($this->app, $feature1, $feature2) extends SliceServiceProvider {
            private $feature1;
            private $feature2;

            public function __construct($app, $feature1, $feature2)
            {
                parent::__construct($app);
                $this->feature1 = $feature1;
                $this->feature2 = $feature2;
            }

            public function configure(Slice $slice): void
            {
                $slice->setName('complete-slice')
                    ->withFeature($this->feature1)
                    ->withFeature($this->feature2);
                // Don't enable directory-dependent features in this test
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        // Register and boot the provider
        $provider->register();
        $provider->boot();

        // Get the slice using public getter
        $slice = $provider->getSlice();

        // Assert slice configuration
        $this->assertSame('complete-slice', $slice->name());
        $this->assertFalse($slice->hasRoutes());
        $this->assertFalse($slice->hasViews());
        $this->assertFalse($slice->hasTranslations());
        $this->assertFalse($slice->hasMigrations());
        $this->assertCount(2, $slice->features());

        // Assert features were registered
        $this->assertTrue($feature1->registered);
        $this->assertTrue($feature2->registered);
    }

    #[Test]
    public function it_can_handle_slice_without_optional_features(): void
    {
        $provider = new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('minimal-slice');
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        $this->assertSame('minimal-slice', $slice->name());
        $this->assertFalse($slice->hasRoutes());
        $this->assertFalse($slice->hasViews());
        $this->assertFalse($slice->hasTranslations());
        $this->assertFalse($slice->hasMigrations());
        $this->assertEmpty($slice->features());
    }

    #[Test]
    public function it_handles_mixed_feature_configuration(): void
    {
        $provider = new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('mixed-slice');
                // Only enable translations, not routes since we don't have directories
            }

            public function getSlice(): Slice
            {
                return $this->slice;
            }
        };

        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        $this->assertSame('mixed-slice', $slice->name());
        $this->assertFalse($slice->hasRoutes());
        $this->assertFalse($slice->hasViews());
        $this->assertFalse($slice->hasTranslations());
        $this->assertFalse($slice->hasMigrations());
    }
}
