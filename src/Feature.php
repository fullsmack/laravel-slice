<?php
declare(strict_types=1);

namespace Fullsmack\LaravelSlice;

use FullSmack\LaravelSlice\Slice;

interface Feature
{
    public function register(Slice $slice): void;
}
