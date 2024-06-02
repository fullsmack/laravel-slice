<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;

trait SliceDefinitions
{
    private string $sliceName;
    private string $slicePath;
    private string $sliceRootFolder;
    private string $sliceRootNamespace;
    private string $sliceTestFolder;
    private string $sliceTestNamespace;

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
        $config = $this->app->make(Repository::class)->get('laravel-slice');

        $this->sliceName = Str::kebab($sliceName);

        $this->sliceRootFolder = $config['root']['folder'];
        $this->sliceRootNamespace = $config['root']['namespace'];
        $this->sliceTestNamespace = $config['test']['namespace'];
        $this->slicePath = base_path("{$this->sliceRootFolder}/{$sliceName}");
    }
}
