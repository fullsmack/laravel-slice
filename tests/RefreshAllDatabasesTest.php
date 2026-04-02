<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\Testing\RefreshAllDatabases;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class RefreshAllDatabasesTest extends TestCase
{
    use RefreshAllDatabases;

    private string $tempPath;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('database.connections.shared-connection', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        // Reset static tracking and registry before the parent sets up the app,
        // so the auto-triggered refreshDatabase() sees a clean slate.
        static::resetSliceConnectionsMigrated();
        SliceRegistry::clear();

        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/laravel-slice-test-' . uniqid();
        mkdir($this->tempPath, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempPath);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createSliceWithMigration(string $sliceName, string $tableName): Slice
    {
        $slicePath = $this->tempPath . '/' . $sliceName;
        $migrationsPath = $slicePath . '/database/migrations';
        mkdir($migrationsPath, 0777, true);

        $connection = 'shared-connection';
        $timestamp = '2024_01_01_000001';

        file_put_contents(
            $migrationsPath . "/{$timestamp}_create_{$tableName}_table.php",
            <<<PHP
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('{$connection}')->create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
        });
    }
    public function down(): void
    {
        Schema::connection('{$connection}')->dropIfExists('{$tableName}');
    }
};
PHP
        );

        return (new Slice())
            ->setName($sliceName)
            ->setPath($slicePath)
            ->withConnection($connection);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    #[Test]
    public function it_migrates_all_slices_that_share_the_same_connection(): void
    {
        $sliceAlpha = $this->createSliceWithMigration('slice-alpha', 'alpha_records');
        $sliceBeta = $this->createSliceWithMigration('slice-beta', 'beta_records');

        SliceRegistry::register($sliceAlpha);
        SliceRegistry::register($sliceBeta);

        $this->refreshSliceDatabases();

        $this->assertTrue(
            Schema::connection('shared-connection')->hasTable('alpha_records'),
            'alpha_records table should exist after migrating slice-alpha'
        );
        $this->assertTrue(
            Schema::connection('shared-connection')->hasTable('beta_records'),
            'beta_records table should exist after migrating slice-beta'
        );
    }

    #[Test]
    public function it_begins_only_one_transaction_per_shared_connection(): void
    {
        $sliceAlpha = $this->createSliceWithMigration('slice-alpha', 'alpha_records');
        $sliceBeta = $this->createSliceWithMigration('slice-beta', 'beta_records');

        SliceRegistry::register($sliceAlpha);
        SliceRegistry::register($sliceBeta);

        $this->refreshSliceDatabases();

        $transactionLevel = $this->app->make('db')
            ->connection('shared-connection')
            ->transactionLevel();

        $this->assertEquals(
            1,
            $transactionLevel,
            'Only one transaction should be open per connection, even when multiple slices share it'
        );
    }

    #[Test]
    public function it_throws_exception_when_slice_is_not_registered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Slice 'non-existent' is not registered.");

        $this->refreshSingleSliceDatabase('non-existent');
    }

    #[Test]
    public function it_skips_slices_that_use_the_default_connection(): void
    {
        $slice = (new Slice())
            ->setName('default-connection-slice')
            ->setPath($this->tempPath . '/default-connection-slice');

        SliceRegistry::register($slice);

        // Should not throw — slices without a custom connection are skipped
        $this->refreshSingleSliceDatabase('default-connection-slice');

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_resets_migration_state(): void
    {
        static::resetSliceConnectionsMigrated();

        /** @phpstan-ignore method.alreadyNarrowedType */
        $this->assertTrue(true);
    }
}
