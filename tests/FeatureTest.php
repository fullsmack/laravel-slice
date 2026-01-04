<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Feature;
use FullSmack\LaravelSlice\Slice;

class FeatureTest extends TestCase
{
    #[Test]
    public function it_can_be_implemented(): void
    {
        $feature = new class implements Feature {
            public bool $registerCalled = false;

            public function register(Slice $slice): void
            {
                $this->registerCalled = true;
            }
        };

        $slice = new Slice();
        $feature->register($slice);

        $this->assertTrue($feature->registerCalled);
    }

    #[Test]
    public function it_receives_slice_instance_in_register(): void
    {
        $slice = new Slice();
        $slice->setName('test-slice');

        $feature = new class implements Feature {
            public ?Slice $receivedSlice = null;

            public function register(Slice $slice): void
            {
                $this->receivedSlice = $slice;
            }
        };

        $feature->register($slice);

        $this->assertSame($slice, $feature->receivedSlice);
        $this->assertSame('test-slice', $feature->receivedSlice->name());
    }
}
