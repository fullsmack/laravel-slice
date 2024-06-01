<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Support\Str;

trait SliceDefinitions
{
    private string $sliceName;
    private string $slicePath;

    private function defineSliceUsingArgument(): void
    {
        $sliceName = $this->argument('sliceName');

        if (!$sliceName)
        {
            $this->error('Please provide a slice name as the first argument.');

            return;
        }

        $this->defineSlice($sliceName);
    }

    private function defineSliceUsingOption(): void
    {
        $sliceName = $this->option('slice');

        if(!$sliceName)
        {
            return;
        }

        $this->defineSlice($sliceName);
    }

    private function defineSlice(string $sliceName): void
    {
        $this->sliceName = Str::kebab($sliceName);
        $this->slicePath = base_path("src/{$sliceName}");
    }
}
