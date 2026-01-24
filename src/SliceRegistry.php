<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

abstract class SliceRegistry
{
    /** @var array<string, Slice> */
    private static array $registry = [];

    public static function register(Slice $slice): void
    {
        self::$registry[$slice->identifier()] = $slice;
    }

    public static function get(string $identifier): Slice
    {
        if (!isset(self::$registry[$identifier]))
        {
            throw SliceNotRegistered::becauseSliceIsNotAddedToRegistry($identifier);
        }

        return self::$registry[$identifier];
    }

    public static function has(string $identifier): bool
    {
        return isset(self::$registry[$identifier]);
    }

    /**
     * @return array<string, Slice>
     */
    public static function all(): array
    {
        return self::$registry;
    }

    public static function clear(): void
    {
        self::$registry = [];
    }
}
