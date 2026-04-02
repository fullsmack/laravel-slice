<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\SliceNotRegistered;

final class SliceRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_slice_to_registry(): void
    {
        $slice = (new Slice())->setName('registry-test');

        SliceRegistry::register($slice);

        $this->assertTrue(SliceRegistry::has('registry-test'));
        $this->assertSame($slice, SliceRegistry::get('registry-test'));
    }

    #[Test]
    public function it_gets_all_registered_slices(): void
    {
        $slice1 = (new Slice())->setName('slice-one');
        $slice2 = (new Slice())->setName('slice-two');

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);

        $all = SliceRegistry::all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('slice-one', $all);
        $this->assertArrayHasKey('slice-two', $all);
    }

    #[Test]
    public function it_throws_exception_when_getting_unregistered_slice(): void
    {
        $this->expectException(SliceNotRegistered::class);

        SliceRegistry::get('non-existent-slice');
    }

    #[Test]
    public function it_clears_registry(): void
    {
        $slice = (new Slice())->setName('to-clear');
        SliceRegistry::register($slice);

        $this->assertTrue(SliceRegistry::has('to-clear'));

        SliceRegistry::clear();

        $this->assertFalse(SliceRegistry::has('to-clear'));
        $this->assertEmpty(SliceRegistry::all());
    }

    #[Test]
    public function it_returns_false_when_checking_for_unregistered_slice(): void
    {
        $this->assertFalse(SliceRegistry::has('non-existent'));
    }

    #[Test]
    public function it_overwrites_existing_slice_with_same_name(): void
    {
        $slice1 = (new Slice())->setName('duplicate');
        $slice2 = (new Slice())->setName('duplicate');

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);

        $this->assertSame($slice2, SliceRegistry::get('duplicate'));
        $this->assertCount(1, SliceRegistry::all());
    }
}
