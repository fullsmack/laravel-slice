<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test\Double;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;

final class SliceServiceProviderFake extends SliceServiceProvider
{
    private string $sliceName;
    private ?string $connection = null;
    private bool $useConnection = false;

    /** @var array<class-string<\Illuminate\Database\Eloquent\Model>> */
    private array $modelClasses = [];

    public function __construct($app, string $sliceName = 'fake-slice')
    {
        parent::__construct($app);

        $this->sliceName = $sliceName;
    }

    public function configure(Slice $slice): void
    {
        $slice->setIdentifier($this->sliceName);

        if ($this->useConnection)
        {
            $slice->useConnection($this->connection, $this->modelClasses);
        }
    }

    /**
     * @param array<class-string<\Illuminate\Database\Eloquent\Model>> $models
     */
    public function useConnection(?string $connection = null, array $models = []): static
    {
        $this->useConnection = true;
        $this->connection = $connection;
        $this->modelClasses = $models;

        return $this;
    }

    public function getSlice(): Slice
    {
        return $this->slice;
    }
}
