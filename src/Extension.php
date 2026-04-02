<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use FullSmack\LaravelSlice\Slice;

interface Extension
{
    public function register(Slice $slice): void;
}
