<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

abstract class SliceRegistry
{
    /** @var array<string, Slice> */
    private static array $registry = [];

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

    public static function clear(): void
    {
        static::$registry = [];
    }
}
