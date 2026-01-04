<?php
declare(strict_types=1);

namespace Tests\Double;

use FullSmack\LaravelSlice\Feature;
use FullSmack\LaravelSlice\Slice;

class FeatureFake implements Feature
{
    public bool $registered = false;
    public ?Slice $receivedSlice = null;

    public function register(Slice $slice): void
    {
        $this->registered = true;
        $this->receivedSlice = $slice;
    }
}
