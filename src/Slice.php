<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Fullsmack\LaravelSlice\Feature;

class Slice
{
    protected string $name;
    protected string $basePath;
    protected string $baseNamespace;

    private bool $hasConfig = false;
    private bool $hasViews = false;
    private bool $hasTranslations = false;
    private bool $hasMigrations = false;

    /** @var array<Feature> */
    private array $features = [];

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function useConfig(): static
    {
        $this->hasConfig = true;

        return $this;
    }

    public function useViews(): static
    {
        $this->hasViews = true;

        return $this;
    }

    public function useTranslations(): static
    {
        $this->hasTranslations = true;

        return $this;
    }

    public function useMigrations(): static
    {
        $this->hasMigrations = true;

        return $this;
    }

    public function withFeature(Feature $feature): static
    {
        $this->features[] = $feature;

        return $this;
    }

    public function basePath(string $directory = null): string
    {
        if ($directory === null)
        {
            return $this->basePath;
        }

        return $this->basePath . DIRECTORY_SEPARATOR .
            ltrim(ltrim($directory, '/'), DIRECTORY_SEPARATOR);
    }

    public function setBasePath(string $path): static
    {
        $this->basePath = $path;

        return $this;
    }

    public function setBaseNamespace(string $namespace): static
    {
        $this->baseNamespace = $namespace;

        return $this;
    }

    public function baseNamespace(string $subnamespace = null): string
    {
        if($subnamespace === null)
        {
            return $this->baseNamespace;
        }

        return $this->baseNamespace .'\\'. $subnamespace;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function hasConfig(): bool
    {
        return $this->hasConfig;
    }

    public function hasViews(): bool
    {
        return $this->hasViews;
    }

    public function hasTranslations(): bool
    {
        return $this->hasTranslations;
    }

    public function hasMigrations(): bool
    {
        return $this->hasMigrations;
    }

    public function features(): array
    {
        return $this->features;
    }
}
