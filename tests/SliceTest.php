<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Feature;

final class SliceTest extends TestCase
{
    private Slice $slice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slice = new Slice();
    }

    #[Test]
    public function it_can_set_and_get_name(): void
    {
        $result = $this->slice->setName('test-slice');

        $this->assertSame($this->slice, $result);
        $this->assertSame('test-slice', $this->slice->name());
    }

    #[Test]
    public function it_can_enable_routes(): void
    {
        $this->assertFalse($this->slice->hasRoutes());

        $result = $this->slice->useRoutes();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasRoutes());
    }

    #[Test]
    public function it_can_enable_translations(): void
    {
        $this->assertFalse($this->slice->hasTranslations());

        $result = $this->slice->useTranslations();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasTranslations());
    }

    #[Test]
    public function it_can_enable_views(): void
    {
        $this->assertFalse($this->slice->hasViews());

        $result = $this->slice->useViews();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasViews());
    }

    #[Test]
    public function it_can_enable_migrations(): void
    {
        $this->assertFalse($this->slice->hasMigrations());

        $result = $this->slice->useMigrations();

        $this->assertSame($this->slice, $result);
        $this->assertTrue($this->slice->hasMigrations());
    }

    #[Test]
    public function it_can_add_features(): void
    {
        $feature = $this->createMock(Feature::class);

        $this->assertEmpty($this->slice->features());

        $result = $this->slice->withFeature($feature);

        $this->assertSame($this->slice, $result);
        $this->assertCount(1, $this->slice->features());
        $this->assertSame($feature, $this->slice->features()[0]);
    }

    #[Test]
    public function it_can_add_multiple_features(): void
    {
        $feature1 = $this->createMock(Feature::class);
        $feature2 = $this->createMock(Feature::class);

        $this->slice
            ->withFeature($feature1)
            ->withFeature($feature2);

        $this->assertCount(2, $this->slice->features());
        $this->assertSame($feature1, $this->slice->features()[0]);
        $this->assertSame($feature2, $this->slice->features()[1]);
    }

    #[Test]
    public function it_can_set_and_get_base_path(): void
    {
        $path = '/path/to/slice';

        $result = $this->slice->setBasePath($path);

        $this->assertSame($this->slice, $result);
        $this->assertSame($path, $this->slice->basePath());
    }

    #[Test]
    public function it_can_get_base_path_with_directory(): void
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
    public function it_can_set_and_get_base_namespace(): void
    {
        $namespace = 'Module\\TestSlice';

        $result = $this->slice->setBaseNamespace($namespace);

        $this->assertSame($this->slice, $result);
        $this->assertSame($namespace, $this->slice->baseNamespace());
    }

    #[Test]
    public function it_can_get_base_namespace_with_subnamespace(): void
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
        $feature = $this->createMock(Feature::class);

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
}
