<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Database;

/** @phpstan-ignore trait.unused (trait is meant to be used by package consumers) */
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
