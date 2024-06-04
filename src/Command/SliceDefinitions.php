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
        $config = config('laravel-slice');

        $this->sliceName = Str::kebab($sliceName);

        $this->sliceRootFolder = Str::lower($config['root']['folder']);
        $this->slicePath = base_path("{$this->sliceRootFolder}/{$this->sliceName}");

        $this->sliceRootNamespace = Str::studly($config['root']['namespace']);
        $this->sliceTestNamespace = Str::studly($config['test']['namespace']);
    }

    private function createInSlice(): bool
    {
        return isset($this->sliceName) && $this->sliceName;
    }

    protected function rootNamespace()
    {
        return $this->sliceRootNamespace.'\\'.Str::studly($this->sliceName).'\\';
    }

    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->slicePath .'/src/'. str_replace('\\', '/', $name) .'.php';
    }

    /**
     * Get the first view directory path from the application configuration.
     *
     * @param  string  $path
     * @return string
     */
    protected function viewPath($path = '')
    {
        if(!$this->createInSlice())
        {
            return parent::viewPath($path);
        }

        $views = $this->slicePath.'/resources/views';

        return $views.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}
