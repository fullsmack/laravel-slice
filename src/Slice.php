<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use FullSmack\LaravelSlice\Feature;
use Illuminate\Console\Command;

class Slice
{
    protected string $name;
    protected string $basePath;
    protected string $baseNamespace;

    private bool $hasRoutes = false;
    private bool $hasTranslations = false;
    private bool $hasViews = false;
    private bool $hasMigrations = false;
    private bool $usesConnection = false;
    private ?string $connection = null;

    /** @var array<class-string<Command>> */
    private array $commands = [];

    /** @var array<string, Slice> */
    private static array $registry = [];

    /** @var array<Feature> */
    private array $features = [];

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function useRoutes(): static
    {
        $this->hasRoutes = true;

        return $this;
    }

    public function useTranslations(): static
    {
        $this->hasTranslations = true;

        return $this;
    }

    public function useViews(): static
    {
        $this->hasViews = true;

        return $this;
    }

    public function useMigrations(): static
    {
        $this->hasMigrations = true;

        return $this;
    }

    public function useConnection(?string $connection = null): static
    {
        $this->usesConnection = true;
        $this->connection = $connection;

        return $this;
    }

    public function connection(): ?string
    {
        if ($this->connection !== null)
        {
            return $this->connection;
        }

        if ($this->usesConnection)
        {
            return config($this->name . '::database.default');
        }

        return null;
    }

    /**
     * @param array<class-string<Command>>
     */
    public function withCommands(array $commands = []): static
    {
        $this->commands = $commands;

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

    public function hasRoutes(): bool
    {
        return $this->hasRoutes;
    }

    public function hasTranslations(): bool
    {
        return $this->hasTranslations;
    }

    public function hasViews(): bool
    {
        return $this->hasViews;
    }

    public function hasMigrations(): bool
    {
        return $this->hasMigrations;
    }

    public function usesConnection(): bool
    {
        return $this->usesConnection;
    }

    /**
     * @return array<class-string<Command>>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @return array<Feature>
     */
    public function features(): array
    {
        return $this->features;
    }

    public function migrationPath(): string
    {
        return $this->basePath('/../database/migrations');
    }

    public static function register(Slice $slice): void
    {
        static::$registry[$slice->name()] = $slice;
    }

    public static function get(string $name): Slice
    {
        if (!isset(static::$registry[$name]))
        {
            throw SliceNotRegistered::becauseSliceIsNotAddedToRegistry($name);
        }

        return static::$registry[$name];
    }

    public static function has(string $name): bool
    {
        return isset(static::$registry[$name]);
    }

    /**
     * @return array<string, Slice>
     */
    public static function all(): array
    {
        return static::$registry;
    }

    public static function clearRegistry(): void
    {
        static::$registry = [];
    }
}
