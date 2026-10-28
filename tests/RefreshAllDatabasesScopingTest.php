<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\Testing\RefreshAllDatabases;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class RefreshAllDatabasesScopingTest extends TestCase
{
    use RefreshAllDatabases;

    /**
     * Redeclaring this property with a value must not conflict with the trait.
     *
     * @var array<int, string>
     */
    protected array $refreshSlice = ['scoped', 'on-default'];

    private string $tempPath;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        foreach (['scoped-connection', 'other-connection'] as $connection)
        {
            config()->set("database.connections.{$connection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        }
    }

    protected function setUp(): void
    {
        static::resetSliceConnectionsMigrated();

        // Force migrate:fresh to run so the default-connection slice is included
        RefreshDatabaseState::$migrated = false;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem())->deleteDirectory($this->tempPath);

        // Other test classes must not reuse a default database that contains this slice
        RefreshDatabaseState::$migrated = false;
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/laravel-slice-scoping-' . uniqid();

        SliceRegistry::clear();

        SliceRegistry::register($this->createSlice('scoped', 'scoped_records', 'scoped-connection'));
        SliceRegistry::register($this->createSlice('other', 'other_records', 'other-connection'));
        SliceRegistry::register($this->createSlice('on-default', 'default_records', 'testing'));
    }

    private function createSlice(string $identifier, string $tableName, string $connection): Slice
    {
        $migrationsPath = $this->tempPath . '/' . $identifier . '/database/migrations';
        mkdir($migrationsPath, 0777, true);

        file_put_contents(
            $migrationsPath . "/2024_01_01_000001_create_{$tableName}_table.php",
            <<<PHP
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
        });
    }
};
PHP
        );

        return (new Slice())
            ->setIdentifier($identifier)
            ->setPath($this->tempPath . '/' . $identifier)
            ->useConnection($connection);
    }

    #[Test]
    public function it_only_refreshes_the_listed_slices(): void
    {
        $this->assertTrue(Schema::connection('scoped-connection')->hasTable('scoped_records'));
        $this->assertFalse(Schema::connection('other-connection')->hasTable('other_records'));
    }

    #[Test]
    public function it_migrates_slices_on_the_default_connection_together_with_the_default_database(): void
    {
        $this->assertTrue(Schema::connection('testing')->hasTable('default_records'));

        // Only RefreshDatabase's own transaction is open on the default connection
        $this->assertSame(1, DB::connection('testing')->transactionLevel());
    }
}
