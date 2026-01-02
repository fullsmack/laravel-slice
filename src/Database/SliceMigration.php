<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Database;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

trait SliceMigration
{
    protected function schema(): Builder
    {
        $connection = $this->getConnection();

        return $connection
            ? Schema::connection($connection)
            : Schema::getFacadeRoot();
    }
}
