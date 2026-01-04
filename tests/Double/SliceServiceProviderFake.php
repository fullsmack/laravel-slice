<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test\Double;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class SliceServiceProviderFake extends SliceServiceProvider
{
    private string $sliceName;
    private ?string $connection = null;
    private ?string $basePath = null;
    private bool $useConnection = false;

    /** @var array<class-string> */
    private array $modelClasses = [];

    public function __construct($app, string $sliceName = 'fake-slice')
    {
        parent::__construct($app);

        $this->sliceName = $sliceName;
    }

    public function configure(Slice $slice): void
    {
        $slice->setName($this->sliceName);

        if ($this->useConnection)
        {
            $slice->useConnection($this->connection);
        }

        if ($this->basePath !== null)
        {
            $slice->setBasePath($this->basePath);
        }

        if (!empty($this->modelClasses))
        {
            $slice->bindModelsToConnection(...$this->modelClasses);
        }
    }

    public function withConnection(?string $connection = null): static
    {
        $this->useConnection = true;
        $this->connection = $connection;

        return $this;
    }

    public function withBasePath(string $path): static
    {
        $this->basePath = $path;

        return $this;
    }

    public function withModels(string ...$modelClasses): static
    {
        $this->modelClasses = $modelClasses;

        return $this;
    }

    public function getSlice(): Slice
    {
        return $this->slice;
    }
}
