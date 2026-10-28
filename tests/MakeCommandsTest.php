<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class MakeCommandsTest extends TestCase
{
    private string $slicePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slicePath = sys_get_temp_dir() . '/laravel-slice-make-' . uniqid();
        mkdir($this->slicePath . '/database/migrations', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->slicePath);

        parent::tearDown();
    }

    private function registerSlice(?callable $configure = null): Slice
    {
        $slice = (new Slice())
            ->setIdentifier('shop')
            ->setPath($this->slicePath)
            ->setNamespace('Slice\\Shop');

        if ($configure !== null)
        {
            $configure($slice);
        }

        SliceRegistry::register($slice);

        return $slice;
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function makeCommandsProvider(): array
    {
        return [
            'make:component' => ['make:component', ['name' => 'Alert']],
            'make:migration' => ['make:migration', ['name' => 'create_things_table']],
            'make:test' => ['make:test', ['name' => 'ThingTest']],
        ];
    }

    /**
     * @param array<string, string> $arguments
     */
    #[Test]
    #[DataProvider('makeCommandsProvider')]
    public function it_returns_a_failure_exit_code_for_an_unregistered_slice(string $command, array $arguments): void
    {
        $this->artisan($command, [...$arguments, '--slice' => 'missing'])
            ->expectsOutputToContain("Slice 'missing' is not registered")
            ->assertFailed();
    }

    #[Test]
    public function make_test_creates_the_test_inside_the_slice_tests_directory(): void
    {
        $this->registerSlice();

        $this->artisan('make:test', ['name' => 'Feature/ThingTest', '--slice' => 'shop', '--phpunit' => true])
            ->assertSuccessful();

        $file = $this->slicePath . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Feature/ThingTest.php';

        $this->assertFileExists($file);
        $this->assertStringContainsString('namespace Test\\Shop\\Feature;', File::get($file));
    }

    #[Test]
    public function make_component_creates_the_class_and_a_slice_namespaced_view(): void
    {
        $this->registerSlice();

        $this->artisan('make:component', ['name' => 'Alert', '--slice' => 'shop'])->assertSuccessful();

        $class = $this->slicePath . '/src/View/Components/Alert.php';

        $this->assertFileExists($class);
        $this->assertStringContainsString("view('shop::components.alert')", File::get($class));
        $this->assertFileExists($this->slicePath . '/resources/views/components/alert.blade.php');
    }

    #[Test]
    public function make_migration_hardcodes_an_explicit_slice_connection(): void
    {
        $this->registerSlice(fn (Slice $slice) => $slice->useConnection('reporting'));

        $this->artisan('make:migration', ['name' => 'create_reports_table', '--slice' => 'shop'])->assertSuccessful();

        $migration = File::get(File::glob($this->slicePath . '/database/migrations/*_create_reports_table.php')[0]);

        $this->assertStringContainsString("protected \$connection = 'reporting';", $migration);
    }

    #[Test]
    public function make_migration_does_not_hardcode_a_config_resolved_slice_connection(): void
    {
        config()->set('shop::database.default', 'reporting');

        $this->registerSlice(fn (Slice $slice) => $slice->useConnection());

        $this->artisan('make:migration', ['name' => 'create_reports_table', '--slice' => 'shop'])->assertSuccessful();

        $migration = File::get(File::glob($this->slicePath . '/database/migrations/*_create_reports_table.php')[0]);

        $this->assertStringNotContainsString('$connection', $migration);
        $this->assertStringNotContainsString('{{ connection }}', $migration);
    }
}
