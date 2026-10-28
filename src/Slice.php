<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use FullSmack\LaravelSlice\Extension;
use FullSmack\LaravelSlice\Path;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slice
{
    protected string $identifier = '';
    protected string $path;
    protected string $namespace;

    private bool $hasRoutes = false;
    private bool $hasTranslations = false;
    private bool $hasViews = false;
    private bool $hasMigrations = false;
    private bool $usesConnection = false;
    private ?string $connection = null;

    /** @var array<class-string<Command>> */
    private array $commands = [];

    /** @var array<Extension> */
    private array $extensions = [];

    /** @var array<class-string<Model>> */
    private array $modelsToBind = [];

    /**
     * Set the unique slice identifier, e.g. 'pizza' or 'api.posts' for nested slices.
     * It is also the `identifier::` namespace for the slice's config, views and translations.
     */
    public function setIdentifier(string $identifier): static
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function identifier(): string
    {
        return $this->identifier;
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

    /**
     * Configure the slice to use its own database connection.
     * Optionally bind models to this connection.
     *
     * @param string|null $connection The connection name, or null to use `{identifier}::database.default` from config
     * @param array<class-string<Model>> $modelsToBind Model classes (using the UsesConnection trait) to bind to this connection
     */
    public function useConnection(?string $connection = null, array $modelsToBind = []): static
    {
        $this->usesConnection = true;
        $this->connection = $connection;
        $this->modelsToBind = $modelsToBind;

        return $this;
    }

    /**
     * @throws SliceMisconfigured when the connection should come from config but none is set
     */
    public function connection(): ?string
    {
        if ($this->connection !== null)
        {
            return $this->connection;
        }

        if ($this->usesConnection)
        {
            $configKey = $this->identifier . '::database.default';
            $connection = config($configKey);

            if (!is_string($connection) || $connection === '')
            {
                throw SliceMisconfigured::becauseDatabaseConfigIsMissing($this->identifier, $configKey);
            }

            return $connection;
        }

        return null;
    }

    /**
     * Whether the connection name was passed explicitly to useConnection(),
     * as opposed to being resolved from the slice config.
     */
    public function hasExplicitConnection(): bool
    {
        return $this->connection !== null;
    }

    /**
     * @param array<class-string<Command>> $commands
     */
    public function withCommands(array $commands = []): static
    {
        $this->commands = $commands;

        return $this;
    }

    public function withExtension(Extension $extension): static
    {
        $this->extensions[] = $extension;

        return $this;
    }

    public function path(?string $directory = null): string
    {
        if ($directory === null)
        {
            return $this->path;
        }

        return Path::join($this->path, $directory);
    }

    public function sourcePath(?string $directory = null): string
    {
        $source = Path::join($this->path, 'src');

        if ($directory === null)
        {
            return $source;
        }

        return Path::join($source, $directory);
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function setNamespace(string $namespace): static
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function namespace(?string $subnamespace = null): string
    {
        if ($subnamespace === null)
        {
            return $this->namespace;
        }

        return $this->namespace .'\\'. $subnamespace;
    }

    /**
     * Get the path-derived namespace segments (without root prefix).
     * Example: 'Web\Blog' for a slice with namespace 'Slice\Web\Blog'
     */
    public function namespaceBase(): string
    {
        $prefix = Str::studly(config('laravel-slice.root.namespace', 'slice'));

        if (Str::startsWith($this->namespace, $prefix . '\\'))
        {
            return Str::after($this->namespace, $prefix . '\\');
        }

        return $this->namespace;
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
     * @return array<Extension>
     */
    public function extensions(): array
    {
        return $this->extensions;
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
        return $this->path('database/migrations');
    }

    public function sourceFolder(): string
    {
        return 'src';
    }

    /**
     * Get the slice path relative to the project root.
     * Example: 'src/api/posts' for a slice at /var/www/project/src/api/posts
     */
    public function projectPath(): string
    {
        return Path::normalize(Path::relative(base_path(), $this->path));
    }
}
