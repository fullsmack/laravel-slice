<?php

declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;

trait SliceDefinitions
{
    private string $sliceName;
    private string $slicePath;

    private function defineSlice(string $sliceName): void
    {
        $this->sliceName = Str::kebab($sliceName);
        $this->slicePath = base_path("src/{$sliceName}");
    }
}
