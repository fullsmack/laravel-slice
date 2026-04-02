<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Collection;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

final class SliceRegistrySlicesWithConnectionsTest extends TestCase
{
    #[Test]
    public function slices_with_connections_returns_only_connection_slices(): void
    {
        $sliceWithConnection = (new Slice())
            ->setName('with-connection')
            ->useConnection('custom');

        $sliceWithoutConnection = (new Slice())
            ->setName('without-connection');

        SliceRegistry::register($sliceWithConnection);
        SliceRegistry::register($sliceWithoutConnection);

        $result = SliceRegistry::slicesWithConnections();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertTrue($result->has('with-connection'));
        $this->assertFalse($result->has('without-connection'));
    }

    #[Test]
    public function slices_with_connections_returns_empty_when_none(): void
    {
        $slice1 = (new Slice())->setName('slice-one');
        $slice2 = (new Slice())->setName('slice-two');

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);

        $result = SliceRegistry::slicesWithConnections();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    #[Test]
    public function slice_for_migration_path_returns_correct_slice(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-project';

        $slice = (new Slice())
            ->setName('posts')
            ->setPath($basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'posts');

        SliceRegistry::register($slice);

        $migrationFile = $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2024_01_01_000000_create_posts_table.php';

        $result = SliceRegistry::sliceForMigrationPath($migrationFile);

        $this->assertSame($slice, $result);
    }

    #[Test]
    public function slice_for_migration_path_returns_null_when_not_found(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-project';

        $slice = (new Slice())
            ->setName('posts')
            ->setPath($basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'posts');

        SliceRegistry::register($slice);

        $migrationFile = $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'other' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2024_01_01_000000_create_other_table.php';

        $result = SliceRegistry::sliceForMigrationPath($migrationFile);

        $this->assertNull($result);
    }

    #[Test]
    public function slices_with_connections_returns_multiple_when_available(): void
    {
        $slice1 = (new Slice())
            ->setName('slice-one')
            ->useConnection('connection-one');

        $slice2 = (new Slice())
            ->setName('slice-two')
            ->useConnection('connection-two');

        $slice3 = (new Slice())
            ->setName('slice-three');

        SliceRegistry::register($slice1);
        SliceRegistry::register($slice2);
        SliceRegistry::register($slice3);

        $result = SliceRegistry::slicesWithConnections();

        $this->assertCount(2, $result);
        $this->assertTrue($result->has('slice-one'));
        $this->assertTrue($result->has('slice-two'));
        $this->assertFalse($result->has('slice-three'));
    }
}
