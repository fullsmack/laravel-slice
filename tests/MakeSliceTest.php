<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

final class MakeSliceTest extends TestCase
{
    private string $projectPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectPath = sys_get_temp_dir() . '/laravel-slice-project-' . uniqid();
        mkdir($this->projectPath, 0777, true);

        $this->app->setBasePath($this->projectPath);

        File::put($this->projectPath . '/composer.json', <<<'JSON'
{
    "name": "acme/app",
    "authors": [{"name": "Søren Ærø"}],
    "require-dev": {},
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
JSON);

        // Don't shell out to composer in tests
        $this->app->instance('composer', new class(new Filesystem()) extends Composer {
            /**
             * @param string|array<int, string> $extra
             * @param string|null $composerBinary
             */
            public function dumpAutoloads($extra = '', $composerBinary = null)
            {
                return 0;
            }
        });
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->projectPath);

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function composerJson(): array
    {
        /** @var array<string, mixed> */
        return json_decode(File::get($this->projectPath . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function it_scaffolds_a_slice_and_registers_it_in_composer_json(): void
    {
        $this->artisan('make:slice', ['sliceName' => 'pizza'])->assertSuccessful();

        $provider = $this->projectPath . '/src/pizza/src/PizzaServiceProvider.php';

        $this->assertFileExists($provider);
        $this->assertStringContainsString("\$slice->setIdentifier('pizza');", File::get($provider));
        $this->assertStringContainsString('namespace Slice\\Pizza;', File::get($provider));

        $composer = $this->composerJson();

        $this->assertSame('src/pizza/src/', $composer['autoload']['psr-4']['Slice\\Pizza\\']);
        $this->assertSame('src/pizza/tests/', $composer['autoload-dev']['psr-4']['Test\\Pizza\\']);
        $this->assertContains('Slice\\Pizza\\PizzaServiceProvider', $composer['extra']['laravel']['providers']);
    }

    #[Test]
    public function it_preserves_empty_objects_unicode_and_the_trailing_newline_in_composer_json(): void
    {
        $this->artisan('make:slice', ['sliceName' => 'pizza'])->assertSuccessful();

        $raw = File::get($this->projectPath . '/composer.json');

        $this->assertStringContainsString('"require-dev": {}', $raw);
        $this->assertStringContainsString('Søren Ærø', $raw);
        $this->assertStringEndsWith("}\n", $raw);
    }

    #[Test]
    public function it_rejects_invalid_slice_identifiers(): void
    {
        $this->artisan('make:slice', ['sliceName' => 'api/1pizza'])
            ->expectsOutputToContain('Invalid slice path segment "1pizza"')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->projectPath . '/src');
    }

    #[Test]
    public function it_fails_when_the_slice_already_exists(): void
    {
        $this->artisan('make:slice', ['sliceName' => 'pizza'])->assertSuccessful();

        $this->artisan('make:slice', ['sliceName' => 'pizza'])->assertFailed();
    }

    #[Test]
    public function it_fails_when_composer_json_is_missing(): void
    {
        File::delete($this->projectPath . '/composer.json');

        $this->artisan('make:slice', ['sliceName' => 'pizza'])->assertFailed();

        $this->assertDirectoryDoesNotExist($this->projectPath . '/src');
    }
}
