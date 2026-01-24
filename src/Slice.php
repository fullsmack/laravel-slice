<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use FullSmack\LaravelSlice\Feature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class Slice
{
    protected string $name = '';
    protected string $identifier = '';  // Full path identifier for registry (e.g., "api/pizza")
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

    /** @var array<Feature> */
    private array $features = [];

    /** @var array<class-string<Model>> */
    private array $modelsToBind = [];

    public function setName(string $name): static
    {
        $this->name = $name;

        // Default identifier to name if not set separately
        if (empty($this->identifier))
        {
            $this->identifier = $name;
        }

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function identifier(): string
    {
        return $this->identifier ?: $this->name;
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

    /**
     * Configure the slice to use a specific database connection.
     * Optionally bind models to this connection.
     *
     * @param string|null $connection The connection name, or null to use config-based lookup
     * @param array<class-string<Model>> $modelsToBind Optional array of model classes to bind to this connection
     */
    public function withConnection(?string $connection = null, array $modelsToBind = []): static
    {
        $this->usesConnection = true;
        $this->connection = $connection;
        $this->modelsToBind = $modelsToBind;

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
            $configKey = $this->name . '::database.default';
            $connection = config($configKey);

            if ($connection === null)
            {
                throw SliceNotRegistered::becauseDatabaseConfigIsMissing($this->name, $configKey);
            }

            return $connection;
        }

        return null;
    }

    /**
     * @param array<class-string<Command>> $commands
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
        if ($subnamespace === null)
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

    /**
     * @return array<class-string<Model>>
     */
    public function modelsToBind(): array
    {
        return $this->modelsToBind;
    }

    public function migrationPath(): string
    {
        return $this->basePath('/../database/migrations');
    }
}
