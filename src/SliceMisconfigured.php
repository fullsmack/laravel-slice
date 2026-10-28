<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice;

use LogicException;

class SliceMisconfigured extends LogicException implements SliceException
{
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

    public static function becauseDatabaseConfigIsMissing(string $identifier, string $configKey): self
    {
        return new self(
            "Database configuration '{$configKey}' is missing for slice '{$identifier}'. " .
            "Either provide an explicit connection with ->useConnection('connection-name') or " .
            "create the config file with the required database.default setting."
        );
    }

    public static function becauseModelDoesNotUseConnectionTrait(string $identifier, string $modelClass): self
    {
        return new self(
            "Model '{$modelClass}' is bound to the connection of slice '{$identifier}' " .
            'but does not use the ' . Database\UsesConnection::class . ' trait.'
        );
    }
}
