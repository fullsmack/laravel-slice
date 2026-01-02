<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Database;

trait UsesConnection
{
    protected static ?string $sliceConnection = null;

    public static function useConnection(string $connection): void
    {
        static::$sliceConnection = $connection;
    }

    public function initializeUsesConnection(): void
    {
        if (static::$sliceConnection !== null && $this->connection === null)
        {
            $this->connection = static::$sliceConnection;
        }
    }
}
