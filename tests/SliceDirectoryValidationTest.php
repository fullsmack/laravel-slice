<?php
declare(strict_types=1);

namespace Tests;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceServiceProvider;
use FullSmack\LaravelSlice\SliceNotRegistered;

class SliceDirectoryValidationTest extends TestCase
{
    private function createRouteTestProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('route-test')
                    ->useRoutes();
            }
        };
    }

    private function createViewTestProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('view-test')
                    ->useViews();
            }
        };
    }

    private function createTranslationTestProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('translation-test')
                    ->useTranslations();
            }
        };
    }

    private function createMigrationTestProvider(): SliceServiceProvider
    {
        return new class($this->app) extends SliceServiceProvider {
            public function configure(Slice $slice): void
            {
                $slice->setName('migration-test')
                    ->useMigrations();
            }
        };
    }

    #[Test]
    public function it_throws_exception_when_routes_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Routes directory');

        $provider = $this->createRouteTestProvider();
        $provider->register();
        $provider->boot(); // Should throw exception because routes directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_views_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Views directory');

        $provider = $this->createViewTestProvider();
        $provider->register();
        $provider->boot(); // Should throw exception because views directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_translations_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Translation directory');

        $provider = $this->createTranslationTestProvider();
        $provider->register();
        $provider->boot(); // Should throw exception because translation directory doesn't exist
    }

    #[Test]
    public function it_throws_exception_when_migrations_directory_missing(): void
    {
        $this->expectException(SliceNotRegistered::class);
        $this->expectExceptionMessage('Migration directory');

        $provider = $this->createMigrationTestProvider();
        $provider->register();
        $provider->boot(); // Should throw exception because migration directory doesn't exist
    }
}
