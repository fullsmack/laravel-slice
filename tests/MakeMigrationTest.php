<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\File;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;

final class MakeMigrationTest extends TestCase
{
    protected string $testSliceName = 'test-slice';
    protected string $testSlicePath;

    protected function setUp(): void
    {
        parent::setUp();

        $sliceRootFolder = config('laravel-slice.root.folder', 'src');

        $this->testSlicePath = base_path($sliceRootFolder . '/' . $this->testSliceName);

        /* Creates test slice directory structure including all parent directories */
        File::ensureDirectoryExists(dirname($this->testSlicePath));
        File::ensureDirectoryExists($this->testSlicePath);
        File::ensureDirectoryExists($this->testSlicePath . '/database/migrations');

        /* Ensures standard Laravel migrations directory exists */
        File::ensureDirectoryExists(base_path('database/migrations'));
    }

    protected function tearDown(): void
    {
        if (File::exists(base_path('database/migrations')))
        {
            $migrations = File::glob(base_path('database/migrations') . '/*_test_*.php');
            foreach ($migrations as $migration)
            {
                File::delete($migration);
            }
        }

        if (File::exists($this->testSlicePath))
        {
            File::deleteDirectory($this->testSlicePath);
        }

        $sliceRootFolder = config('laravel-slice.root.folder', 'src');

        $srcDir = base_path($sliceRootFolder);

        if (File::exists($srcDir) && count(File::directories($srcDir)) === 0 && count(File::files($srcDir)) === 0)
        {
            File::deleteDirectory($srcDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_uses_custom_stubs_when_creating_migration_for_slice(): void
    {
        $slice = new Slice($this->testSliceName, $this->testSlicePath);

        SliceRegistry::register($slice);

        $this->artisan('make:migration', [
            'name' => 'test_slice_migration',
            '--slice' => $this->testSliceName,
            '--create' => 'test_table',
        ])->assertSuccessful();

        $migrations = File::glob($this->testSlicePath . '/database/migrations/*_test_slice_migration.php');

        $this->assertCount(1, $migrations, 'Expected exactly one migration file to be created');

        $migrationFile = $migrations[0];
        $migrationContent = File::get($migrationFile);

        $this->assertStringContainsString('use FullSmack\LaravelSlice\Database\SliceMigration;', $migrationContent);
        $this->assertStringContainsString('use SliceMigration;', $migrationContent);

        $this->assertStringContainsString('$this->schema()->create(', $migrationContent);
        $this->assertStringContainsString('$this->schema()->dropIfExists(', $migrationContent);
    }

    #[Test]
    public function it_uses_default_laravel_stubs_when_creating_migration_without_slice(): void
    {
        File::ensureDirectoryExists(base_path('database/migrations'));

        $this->artisan('make:migration', [
            'name' => 'test_regular_migration',
            '--create' => 'test_table',
        ])->assertSuccessful();

        $migrations = File::glob(base_path('database/migrations') . '/*_test_regular_migration.php');

        $this->assertCount(1, $migrations);

        $migrationFile = $migrations[0];
        $migrationContent = File::get($migrationFile);

        $this->assertStringNotContainsString('use FullSmack\LaravelSlice\Database\SliceMigration;', $migrationContent);
        $this->assertStringNotContainsString('use SliceMigration;', $migrationContent);

        $this->assertStringContainsString('Schema::create(', $migrationContent);
    }

    #[Test]
    public function it_includes_connection_placeholder_in_slice_migrations(): void
    {
        $slice = new Slice($this->testSliceName, $this->testSlicePath);

        SliceRegistry::register($slice);

        $this->artisan('make:migration', [
            'name' => 'test_migration_with_connection',
            '--slice' => $this->testSliceName,
        ])->assertSuccessful();

        $migrations = File::glob($this->testSlicePath . '/database/migrations/*_test_migration_with_connection.php');

        $this->assertCount(1, $migrations);

        $migrationFile = $migrations[0];
        $migrationContent = File::get($migrationFile);

        $this->assertStringNotContainsString('{{ connection }}', $migrationContent);

        $this->assertStringContainsString('use SliceMigration;', $migrationContent);
    }

    #[Test]
    public function it_uses_table_option_correctly_in_slice_migrations(): void
    {
        $slice = new Slice($this->testSliceName, $this->testSlicePath);

        SliceRegistry::register($slice);

        $this->artisan('make:migration', [
            'name' => 'update_users_table_test',
            '--slice' => $this->testSliceName,
            '--table' => 'users',
        ])->assertSuccessful();

        $migrations = File::glob($this->testSlicePath . '/database/migrations/*_update_users_table_test.php');

        $this->assertCount(1, $migrations);

        $migrationFile = $migrations[0];
        $migrationContent = File::get($migrationFile);

        $this->assertStringContainsString('use SliceMigration;', $migrationContent);

        $this->assertStringContainsString('$this->schema()->table(', $migrationContent);
    }
}
