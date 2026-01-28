<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use Illuminate\Support\Collection;

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

    /**
     * Returns the slice that owns a given migration path, or null if not found.
     */
    public static function sliceForMigrationPath(string $path): ?Slice
    {
        // Normalize path separators for consistent comparison
        $normalizedPath = str_replace('\\', '/', $path);

        foreach (self::$registry as $slice)
        {
            $normalizedMigrationPath = str_replace('\\', '/', $slice->migrationPath());

            if (str_starts_with($normalizedPath, $normalizedMigrationPath))
            {
                return $slice;
            }
        }

        return null;
    }

    /**
     * Returns a collection of all slices that have custom database connections configured.
     *
     * @return Collection<string, Slice>
     */
    public static function slicesWithConnections(): Collection
    {
        return (new Collection(self::$registry))->filter(
            fn (Slice $slice): bool => $slice->usesConnection()
        );
    }
}
