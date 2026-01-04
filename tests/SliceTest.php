<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceNotRegistered;
use FullSmack\LaravelSlice\Test\Double\FeatureFake;

final class SliceTest extends TestCase
{
    private Slice $slice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slice = new Slice();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
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
    public function it_adds_features(): void
    {
        $feature = new FeatureFake();

        $this->assertEmpty($this->slice->features());

        $result = $this->slice->withFeature($feature);

        $this->assertSame($this->slice, $result);
        $this->assertCount(1, $this->slice->features());
        $this->assertSame($feature, $this->slice->features()[0]);
    }

    #[Test]
    public function it_adds_multiple_features(): void
    {
        $feature1 = new FeatureFake();
        $feature2 = new FeatureFake();

        $this->slice
            ->withFeature($feature1)
            ->withFeature($feature2);

        $this->assertCount(2, $this->slice->features());
        $this->assertSame($feature1, $this->slice->features()[0]);
        $this->assertSame($feature2, $this->slice->features()[1]);
    }

    #[Test]
    public function it_sets_and_gets_base_path(): void
    {
        $path = '/path/to/slice';

        $result = $this->slice->setBasePath($path);

        $this->assertSame($this->slice, $result);
        $this->assertSame($path, $this->slice->basePath());
    }

    #[Test]
    public function it_gets_base_path_with_directory(): void
    {
        $basePath = '/path/to/slice';
        $this->slice->setBasePath($basePath);

        $result = $this->slice->basePath('subdirectory');

        $expectedPath = $basePath . DIRECTORY_SEPARATOR . 'subdirectory';
        $this->assertSame($expectedPath, $result);
    }

    #[Test]
    public function it_handles_directory_separators_in_base_path(): void
    {
        $basePath = '/path/to/slice';
        $this->slice->setBasePath($basePath);

        $result1 = $this->slice->basePath('/subdirectory');
        $result2 = $this->slice->basePath('subdirectory/');

        $expectedPath = $basePath . DIRECTORY_SEPARATOR . 'subdirectory';
        $this->assertSame($expectedPath, $result1);

        $expectedPath2 = $basePath . DIRECTORY_SEPARATOR . 'subdirectory/';
        $this->assertSame($expectedPath2, $result2);
    }

    #[Test]
    public function it_sets_and_gets_base_namespace(): void
    {
        $namespace = 'Module\\TestSlice';

        $result = $this->slice->setBaseNamespace($namespace);

        $this->assertSame($this->slice, $result);
        $this->assertSame($namespace, $this->slice->baseNamespace());
    }

    #[Test]
    public function it_gets_base_namespace_with_subnamespace(): void
    {
        $baseNamespace = 'Module\\TestSlice';
        $this->slice->setBaseNamespace($baseNamespace);

        $result = $this->slice->baseNamespace('Controllers');

        $expectedNamespace = $baseNamespace . '\\Controllers';
        $this->assertSame($expectedNamespace, $result);
    }

    #[Test]
    public function it_supports_method_chaining(): void
    {
        $feature = new FeatureFake();

        $result = $this->slice
            ->setName('test-slice')
            ->setBasePath('/test/path')
            ->setBaseNamespace('Test\\Namespace')
            ->useRoutes()
            ->useViews()
            ->useTranslations()
            ->useMigrations()
            ->withFeature($feature);

        $this->assertSame($this->slice, $result);
        $this->assertSame('test-slice', $this->slice->name());
        $this->assertSame('/test/path', $this->slice->basePath());
        $this->assertSame('Test\\Namespace', $this->slice->baseNamespace());
        $this->assertTrue($this->slice->hasRoutes());
        $this->assertTrue($this->slice->hasViews());
        $this->assertTrue($this->slice->hasTranslations());
        $this->assertTrue($this->slice->hasMigrations());
        $this->assertCount(1, $this->slice->features());
    }

    #[Test]
    public function it_registers_slice_to_static_registry(): void
    {
        $this->slice->setName('registry-test');

        Slice::register($this->slice);

        $this->assertTrue(Slice::has('registry-test'));
        $this->assertSame($this->slice, Slice::get('registry-test'));
    }

    #[Test]
    public function it_gets_all_registered_slices(): void
    {
        $slice1 = (new Slice())->setName('slice-one');
        $slice2 = (new Slice())->setName('slice-two');

        Slice::register($slice1);
        Slice::register($slice2);

        $all = Slice::all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('slice-one', $all);
        $this->assertArrayHasKey('slice-two', $all);
    }

    #[Test]
    public function it_throws_exception_when_getting_unregistered_slice(): void
    {
        $this->expectException(SliceNotRegistered::class);

        Slice::get('non-existent-slice');
    }

    #[Test]
    public function it_clears_registry(): void
    {
        $this->slice->setName('to-clear');
        Slice::register($this->slice);

        $this->assertTrue(Slice::has('to-clear'));

        Slice::clearRegistry();

        $this->assertFalse(Slice::has('to-clear'));
        $this->assertEmpty(Slice::all());
    }

    #[Test]
    public function it_enables_connection(): void
    {
        $this->assertFalse($this->slice->usesConnection());

        $result = $this->slice->useConnection('mysql');

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->usesConnection());
        $this->assertSame('mysql', $this->slice->connection());
    }

    #[Test]
    public function it_enables_connection_without_explicit_name(): void
    {
        $this->slice->setName('test-slice');

        $result = $this->slice->useConnection();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->usesConnection());
        // Connection returns null when using config-based connection lookup and config doesn't exist
        $this->assertNull($this->slice->connection());
    }

    #[Test]
    public function it_registers_commands(): void
    {
        $this->assertEmpty($this->slice->commands());

        $result = $this->slice->withCommands(['App\\Commands\\TestCommand']);

        $this->assertSame($this->slice, $result);
        $this->assertCount(1, $this->slice->commands());
    }

    #[Test]
    public function it_gets_migration_path(): void
    {
        $this->slice->setBasePath('/app/slices/my-slice/src');

        $migrationPath = $this->slice->migrationPath();

        $this->assertStringContainsString('database', $migrationPath);
        $this->assertStringContainsString('migrations', $migrationPath);
    }
}
