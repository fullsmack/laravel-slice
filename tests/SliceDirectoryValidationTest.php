<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;

class SliceDirectoryValidationTest extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('route-test')
            ->useRoutes(); // Only routes, no other features
    }
}

class SliceDirectoryValidationTest extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('view-test')
            ->useViews(); // Only views, no other features
    }
}

class SliceDirectoryValidationTest extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('translation-test')
            ->useTranslations(); // Only translations, no other features
    }
}

class SliceDirectoryValidationTest extends SliceServiceProvider
{
    public function configure(Slice $slice): void
    {
        $slice->setName('migration-test')
            ->useMigrations(); // Only migrations, no other features
    }
}

class SliceDirectoryValidationTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_routes_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Routes directory');

        $provider = new RouteTestSliceServiceProvider($this->app);
        $provider->register();
        $provider->boot(); // Should throw exception because routes directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_views_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Views directory');

        $provider = new ViewTestSliceServiceProvider($this->app);
        $provider->register();
        $provider->boot(); // Should throw exception because views directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_translations_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Translation directory');

        $provider = new TranslationTestSliceServiceProvider($this->app);
        $provider->register();
        $provider->boot(); // Should throw exception because translation directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_migrations_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Migration directory');

        $provider = new MigrationTestSliceServiceProvider($this->app);
        $provider->register();
        $provider->boot(); // Should throw exception because migration directory doesn't exist
    }
}
