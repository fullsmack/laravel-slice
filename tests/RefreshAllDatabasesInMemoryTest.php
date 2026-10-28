<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\Testing\RefreshAllDatabases;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * Migration state is deliberately NOT reset between these tests: each test gets a
 * fresh application (and connection object), so the in-memory slice database has
 * to survive between them, while each test's writes are rolled back.
 */
final class RefreshAllDatabasesInMemoryTest extends TestCase
{
    use RefreshAllDatabases;

    private static string $slicePath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::resetSliceConnectionsMigrated();

        self::$slicePath = sys_get_temp_dir() . '/laravel-slice-memory-' . uniqid();
        mkdir(self::$slicePath . '/database/migrations', 0777, true);

        file_put_contents(
            self::$slicePath . '/database/migrations/2024_01_01_000001_create_memory_records_table.php',
            <<<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memory_records', function (Blueprint $table) {
            $table->id();
        });
    }
};
PHP
        );
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->deleteDirectory(self::$slicePath);

        parent::tearDownAfterClass();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('database.connections.memory-slice', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function beforeRefreshingDatabase(): void
    {
        SliceRegistry::clear();

        SliceRegistry::register(
            (new Slice())
                ->setIdentifier('memory')
                ->setPath(self::$slicePath)
                ->useConnection('memory-slice')
        );
    }

    #[Test]
    public function first_test_sees_the_migrated_slice_table(): void
    {
        $this->assertSliceTableIsUsableAndEmpty();
    }

    #[Test]
    public function second_test_still_sees_the_migrated_slice_table(): void
    {
        $this->assertSliceTableIsUsableAndEmpty();
    }

    private function assertSliceTableIsUsableAndEmpty(): void
    {
        $this->assertTrue(Schema::connection('memory-slice')->hasTable('memory_records'));
        $this->assertSame(0, DB::connection('memory-slice')->table('memory_records')->count());

        DB::connection('memory-slice')->table('memory_records')->insert(['id' => 1]);

        $this->assertSame(1, DB::connection('memory-slice')->table('memory_records')->count());
    }
}
