<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

final class MigrateSliceSingleSliceTest extends TestCase
{
    private string $tempPath;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('database.connections.secondary', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/laravel-slice-test-' . uniqid();
        mkdir($this->tempPath, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    private function createSlice(string $identifier, string $tableName): Slice
    {
        $migrationsPath = $this->tempPath . '/' . $identifier . '/database/migrations';
        mkdir($migrationsPath, 0777, true);

        // Written like the package stub: no hardcoded connection, uses $this->schema()
        file_put_contents(
            $migrationsPath . "/2024_01_01_000001_create_{$tableName}_table.php",
            <<<PHP
<?php
use FullSmack\LaravelSlice\Database\SliceMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SliceMigration;

    public function up(): void
    {
        \$this->schema()->create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
        });
    }

    public function down(): void
    {
        \$this->schema()->dropIfExists('{$tableName}');
    }
};
PHP
        );

        return (new Slice())
            ->setIdentifier($identifier)
            ->setPath($this->tempPath . '/' . $identifier)
            ->setNamespace('Slice\\' . ucfirst($identifier));
    }

    #[Test]
    public function it_migrates_a_single_slice_without_the_realpath_option(): void
    {
        SliceRegistry::register($this->createSlice('solo', 'solo_records')->useConnection('secondary'));

        $this->artisan('migrate', ['--slice' => 'solo', '--force' => true])->assertSuccessful();

        $this->assertTrue(Schema::connection('secondary')->hasTable('solo_records'));
        $this->assertFalse(Schema::connection('testing')->hasTable('solo_records'));
    }

    #[Test]
    public function it_migrates_a_default_connection_slice_on_the_default_connection(): void
    {
        SliceRegistry::register($this->createSlice('plain', 'plain_records'));

        $this->artisan('migrate', ['--slice' => 'plain', '--force' => true])->assertSuccessful();

        $this->assertTrue(Schema::connection('testing')->hasTable('plain_records'));
    }

    #[Test]
    public function it_fails_when_the_slice_connection_config_is_missing(): void
    {
        SliceRegistry::register($this->createSlice('broken', 'broken_records')->useConnection());

        $this->artisan('migrate', ['--slice' => 'broken', '--force' => true])
            ->expectsOutputToContain("Database configuration 'broken::database.default' is missing")
            ->assertFailed();
    }

    #[Test]
    public function all_slices_fails_when_a_slice_connection_config_is_missing(): void
    {
        SliceRegistry::register($this->createSlice('broken', 'broken_records')->useConnection());

        $this->artisan('migrate', ['--all-slices' => true, '--force' => true])->assertFailed();
    }

    #[Test]
    public function it_fails_for_an_unregistered_slice(): void
    {
        $this->artisan('migrate', ['--slice' => 'missing'])->assertFailed();
    }
}
