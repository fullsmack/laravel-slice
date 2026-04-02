<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

abstract class Path
{
    /**
     * Trim leading slashes and separators from a path segment.
     */
    public static function trimLeadingSeparators(string $segment): string
    {
        return ltrim(ltrim($segment, '/'), DIRECTORY_SEPARATOR);
    }

    public static function join(string ...$segments): string
    {
        $normalized = [];
        foreach ($segments as $i => $segment)
        {
            $normalized[] = $i === 0 ? $segment : self::trimLeadingSeparators($segment);
        }

        return implode(DIRECTORY_SEPARATOR, $normalized);
    }

    /**
     * Normalize path separators to forward slashes for consistency.
     */
    public static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Get the relative path from basePath to fullPath.
     */
    public static function relative(string $basePath, string $fullPath): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $fullPath = rtrim($fullPath, DIRECTORY_SEPARATOR);

        if (str_starts_with($fullPath, $basePath . DIRECTORY_SEPARATOR))
        {
            return substr($fullPath, strlen($basePath) + 1);
        }

        return $fullPath;
    }
}
