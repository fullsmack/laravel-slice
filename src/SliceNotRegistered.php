<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use LogicException;

class SliceNotRegistered extends LogicException
{
    public static function becauseNameIsNotDefined(): self
    {
        return new self(
            'This slice does not have a name. '.
            'You can set one with `$slice->setName("slice-name")`'
        );
    }

    public static function becauseSliceIsNotAddedToRegistry(string $name): self
    {
        return new self(
            "Slice '{$name}' is not added to registry. " .
                'Make sure the slice service provider is loaded and registers the slice via SliceRegistry::register().'
        );
    }

    public static function becauseRouteDirectoryDoesntExist(string $directory): self
    {
        return new self(
            "Routes directory '{$directory}' does not exist. " .
            "Create the directory or remove ->useRoutes() from your slice configuration."
        );
    }

    public static function becauseViewDirectoryDoesntExist(string $directory): self
    {
        return new self(
            "Views directory '{$directory}' does not exist. " .
            "Create the directory or remove ->useViews() from your slice configuration."
        );
    }

    public static function becauseTranslationDirectoryDoesntExist(string $directory): self
    {
        return new self(
            "Translation directory '{$directory}' does not exist. " .
            "Create the directory or remove ->useTranslations() from your slice configuration."
        );
    }

    public static function becauseMigrationDirectoryDoesntExist(string $directory): self
    {
        return new self(
            "Migration directory '{$directory}' does not exist. " .
            "Create the directory or remove ->useMigrations() from your slice configuration."
        );
    }
}
