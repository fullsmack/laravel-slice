<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Extension;
use FullSmack\LaravelSlice\Slice;

final class ExtensionTest extends TestCase
{
    #[Test]
    public function it_registers_an_extension(): void
    {
        $extension = new class implements Extension {
            public bool $registerCalled = false;

            public function register(Slice $slice): void
            {
                $this->registerCalled = true;
            }
        };

        $slice = new Slice();
        $extension->register($slice);

        $this->assertTrue($extension->registerCalled);
    }

    #[Test]
    public function it_receives_slice_instance_in_register(): void
    {
        $slice = new Slice();
        $slice->setName('test-slice');

        $extension = new class implements Extension {
            public ?Slice $receivedSlice = null;

            public function register(Slice $slice): void
            {
                $this->receivedSlice = $slice;
            }
        };

        $extension->register($slice);

        $this->assertSame($slice, $extension->receivedSlice);
        $this->assertSame('test-slice', $extension->receivedSlice->name());
    }
}
