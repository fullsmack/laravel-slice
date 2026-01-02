<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Database;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

trait MigrationConnection
{
    protected function schema(): Builder
    {
        return Schema::connection($this->getConnection());
    }
}
