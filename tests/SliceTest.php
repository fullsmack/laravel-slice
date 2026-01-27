<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Test\Double\CommandFake;
use FullSmack\LaravelSlice\Test\Double\ExtensionFake;

final class SliceTest extends TestCase
{
    private Slice $slice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slice = new Slice();
    }

    #[Test]
    public function it_sets_and_gets_name(): void
    {
        $result = $this->slice->setName('test-slice');

        $this->assertSame($this->slice, $result);
        $this->assertSame('test-slice', $this->slice->name());
    }

    #[Test]
    public function it_enables_routes(): void
    {
        $this->assertFalse($this->slice->hasRoutes());

        $result = $this->slice->useRoutes();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasRoutes());
    }

    #[Test]
    public function it_enables_translations(): void
    {
        $this->assertFalse($this->slice->hasTranslations());

        $result = $this->slice->useTranslations();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasTranslations());
    }

    #[Test]
    public function it_enables_views(): void
    {
        $this->assertFalse($this->slice->hasViews());

        $result = $this->slice->useViews();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasViews());
    }

    #[Test]
    public function it_enables_migrations(): void
    {
        $this->assertFalse($this->slice->hasMigrations());

        $result = $this->slice->useMigrations();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasMigrations());
    }

    #[Test]
    public function it_adds_extensions(): void
    {
        $extension = new ExtensionFake();

        $this->assertEmpty($this->slice->extensions());

        $result = $this->slice->withExtension($extension);

        $this->assertSame($this->slice, $result);
        $this->assertCount(1, $this->slice->extensions());
        $this->assertSame($extension, $this->slice->extensions()[0]);
    }

    #[Test]
    public function it_adds_multiple_extensions(): void
    {
        $extension1 = new ExtensionFake();
        $extension2 = new ExtensionFake();

        $this->slice
            ->withExtension($extension1)
            ->withExtension($extension2);

        $this->assertCount(2, $this->slice->extensions());
        $this->assertSame($extension1, $this->slice->extensions()[0]);
        $this->assertSame($extension2, $this->slice->extensions()[1]);
    }

    #[Test]
    public function it_sets_and_gets_base_path(): void
    {
        $path = '/path/to/slice';

        $result = $this->slice->setPath($path);

        $this->assertSame($this->slice, $result);
        $this->assertSame($path, $this->slice->path());
    }

    #[Test]
    public function it_gets_base_path_with_directory(): void
    {
        $basePath = '/path/to/slice';
        $this->slice->setPath($basePath);

        $result = $this->slice->path('subdirectory');

        $expectedPath = $basePath . DIRECTORY_SEPARATOR . 'subdirectory';
        $this->assertSame($expectedPath, $result);
    }

    #[Test]
    public function it_handles_directory_separators_in_base_path(): void
    {
        $basePath = '/path/to/slice';
        $this->slice->setPath($basePath);

        $result1 = $this->slice->path('/subdirectory');
        $result2 = $this->slice->path('subdirectory/');

        $expectedPath = $basePath . DIRECTORY_SEPARATOR . 'subdirectory';
        $this->assertSame($expectedPath, $result1);

        $expectedPath2 = $basePath . DIRECTORY_SEPARATOR . 'subdirectory/';
        $this->assertSame($expectedPath2, $result2);
    }

    #[Test]
    public function it_sets_and_gets_base_namespace(): void
    {
        $namespace = 'Module\\TestSlice';

        $result = $this->slice->setNamespace($namespace);

        $this->assertSame($this->slice, $result);
        $this->assertSame($namespace, $this->slice->namespace());
    }

    #[Test]
    public function it_gets_base_namespace_with_subnamespace(): void
    {
        $namespace = 'Module\\TestSlice';
        $this->slice->setNamespace($namespace);

        $result = $this->slice->namespace('Controllers');

        $expectedNamespace = $namespace . '\\Controllers';
        $this->assertSame($expectedNamespace, $result);
    }

    #[Test]
    public function it_supports_method_chaining(): void
    {
        $extension = new ExtensionFake();

        $result = $this->slice
            ->setName('test-slice')
            ->setPath('/test/path')
            ->setNamespace('Test\\Namespace')
            ->useRoutes()
            ->useViews()
            ->useTranslations()
            ->useMigrations()
            ->withExtension($extension);

        $this->assertSame($this->slice, $result);
        $this->assertSame('test-slice', $this->slice->name());
        $this->assertSame('/test/path', $this->slice->path());
        $this->assertSame('Test\\Namespace', $this->slice->namespace());
        $this->assertTrue($this->slice->hasRoutes());
        $this->assertTrue($this->slice->hasViews());
        $this->assertTrue($this->slice->hasTranslations());
        $this->assertTrue($this->slice->hasMigrations());
        $this->assertCount(1, $this->slice->extensions());
    }

    #[Test]
    public function it_enables_connection(): void
    {
        $this->assertFalse($this->slice->usesConnection());

        $result = $this->slice->withConnection('mysql');

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->usesConnection());
        $this->assertSame('mysql', $this->slice->connection());
    }

    #[Test]
    public function it_enables_connection_without_explicit_name(): void
    {
        $this->slice->setName('test-slice');

        $result = $this->slice->withConnection();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->usesConnection());

        // Connection throws exception when using config-based connection lookup and config doesn't exist
        $this->expectException(\FullSmack\LaravelSlice\SliceNotRegistered::class);
        $this->expectExceptionMessage("Database configuration 'test-slice::database.default' is missing");
        $this->slice->connection();
    }

    #[Test]
    public function it_registers_commands(): void
    {
        $this->assertEmpty($this->slice->commands());

        $result = $this->slice->withCommands([CommandFake::class]);

        $this->assertSame($this->slice, $result);
        $this->assertCount(1, $this->slice->commands());
    }

    #[Test]
    public function it_gets_migration_path(): void
    {
        $this->slice->setPath('/app/slices/my-slice/src');

        $migrationPath = $this->slice->migrationPath();

        $this->assertStringContainsString('database', $migrationPath);
        $this->assertStringContainsString('migrations', $migrationPath);
    }
}
