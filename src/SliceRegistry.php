<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

abstract class SliceRegistry
{
    /** @var array<string, Slice> */
    private static array $registry = [];

    public static function register(Slice $slice): void
    {
        self::$registry[$slice->name()] = $slice;
    }

    public static function get(string $name): Slice
    {
        if (!isset(self::$registry[$name]))
        {
            throw SliceNotRegistered::becauseSliceIsNotAddedToRegistry($name);
        }

        return self::$registry[$name];
    }

    public static function has(string $name): bool
    {
        return isset(self::$registry[$name]);
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
