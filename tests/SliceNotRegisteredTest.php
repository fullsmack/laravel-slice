<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\SliceNotRegistered;
use LogicException;

class SliceNotRegisteredTest extends TestCase
{
    #[Test]
    public function it_extends_logic_exception(): void
    {
        $exception = SliceNotRegistered::becauseNameIsNotDefined();

        $this->assertInstanceOf(LogicException::class, $exception);
    }

    #[Test]
    public function it_has_descriptive_message_when_name_is_not_defined(): void
    {
        $exception = SliceNotRegistered::becauseNameIsNotDefined();

        $expectedMessage = 'This slice does not have a name. ' .
            'You can set one with `$slice->setName("slice-name")`';

        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    #[Test]
    public function it_has_descriptive_message_when_route_directory_doesnt_exist(): void
    {
        $directory = '/path/to/routes';
        $exception = SliceNotRegistered::becauseRouteDirectoryDoesntExist($directory);

        $expectedMessage = "Routes directory '/path/to/routes' does not exist. " .
            "Create the directory or remove ->useRoutes() from your slice configuration.";

        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    #[Test]
    public function it_has_descriptive_message_when_view_directory_doesnt_exist(): void
    {
        $directory = '/path/to/views';
        $exception = SliceNotRegistered::becauseViewDirectoryDoesntExist($directory);

        $expectedMessage = "Views directory '/path/to/views' does not exist. " .
            "Create the directory or remove ->useViews() from your slice configuration.";

        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    #[Test]
    public function it_has_descriptive_message_when_translation_directory_doesnt_exist(): void
    {
        $directory = '/path/to/lang';
        $exception = SliceNotRegistered::becauseTranslationDirectoryDoesntExist($directory);

        $expectedMessage = "Translation directory '/path/to/lang' does not exist. " .
            "Create the directory or remove ->useTranslations() from your slice configuration.";

        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    #[Test]
    public function it_has_descriptive_message_when_migration_directory_doesnt_exist(): void
    {
        $directory = '/path/to/migrations';
        $exception = SliceNotRegistered::becauseMigrationDirectoryDoesntExist($directory);

        $expectedMessage = "Migration directory '/path/to/migrations' does not exist. " .
            "Create the directory or remove ->useMigrations() from your slice configuration.";

        $this->assertSame($expectedMessage, $exception->getMessage());
    }
}
