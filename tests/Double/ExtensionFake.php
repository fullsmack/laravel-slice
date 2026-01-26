<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test\Double;

use FullSmack\LaravelSlice\Extension;
use FullSmack\LaravelSlice\Slice;

final class ExtensionFake implements Extension
{
    public bool $registered = false;
    public ?Slice $receivedSlice = null;

    public function register(Slice $slice): void
    {
        $this->registered = true;
        $this->receivedSlice = $slice;
    }
}
