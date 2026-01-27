<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\SliceRegistry;
use FullSmack\LaravelSlice\Command\SliceDefinitions;
use FullSmack\LaravelSlice\Command\SliceMakeDefinitions;

final class SliceDefinitionsTest extends TestCase
{
    protected string $testSliceName = 'test-slice';
    protected string $testSlicePath;

    protected function setUp(): void
    {
        parent::setUp();

        $sliceRootFolder = config('laravel-slice.root.folder', 'src');
        $this->testSlicePath = base_path($sliceRootFolder . '/' . $this->testSliceName);

        File::ensureDirectoryExists(dirname($this->testSlicePath));
        File::ensureDirectoryExists($this->testSlicePath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testSlicePath))
        {
            File::deleteDirectory($this->testSlicePath);
        }

        $sliceRootFolder = config('laravel-slice.root.folder', 'src');
        $srcDir = base_path($sliceRootFolder);

        if (File::exists($srcDir) && count(File::directories($srcDir)) === 0 && count(File::files($srcDir)) === 0) {
            File::deleteDirectory($srcDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_define_slice_from_registry(): void
    {
        // Create and register a slice
        $slice = (new Slice($this->testSliceName, $this->testSlicePath))
            ->setName($this->testSliceName)
            ->setPath($this->testSlicePath)
            ->setNamespace('Domain\\TestSlice');

        SliceRegistry::register($slice);

        // Create a test command that uses SliceDefinitions
        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:command {--slice=}';

            public function handle()
            {
                $this->resolveSliceFromOption();
                return 0;
            }

            // Expose private properties for testing
            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSlicePath(): ?string
            {
                return $this->slicePath ?? null;
            }

            public function getSliceFolderName(): ?string
            {
                return $this->sliceFolderName ?? null;
            }

            public function getSliceNamespace(): ?string
            {
                return $this->sliceNamespace ?? null;
            }

            public function getSliceTestNamespace(): ?string
            {
                return $this->sliceTestNamespace ?? null;
            }

            public function isRunInSlice(): bool
            {
                return $this->runInSlice();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        // Execute the command with --slice option
        $this->artisan('test:command', ['--slice' => $this->testSliceName])
            ->assertSuccessful();

        // Verify all properties are set correctly from registry
        $this->assertEquals($this->testSliceName, $command->getSliceName());
        $this->assertEquals($this->testSlicePath, $command->getSlicePath());
        $this->assertTrue($command->isRunInSlice());
    }

    #[Test]
    public function it_can_define_slice_using_argument(): void
    {
        $command = new class extends Command {
            use SliceDefinitions;
            use SliceMakeDefinitions;

            protected $signature = 'test:command {sliceName} {--dir=}';

            public function handle()
            {
                $this->defineSliceFromArgument();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSlicePath(): ?string
            {
                return $this->slicePath ?? null;
            }

            public function isRunInSlice(): bool
            {
                return $this->runInSlice();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command', ['sliceName' => $this->testSliceName])
            ->assertSuccessful();

        $this->assertEquals($this->testSliceName, $command->getSliceName());
        $this->assertTrue($command->isRunInSlice());
    }

    #[Test]
    public function it_validates_slice_identifier_with_backslashes(): void
    {
        $command = new class extends Command {
            use SliceDefinitions;
            use SliceMakeDefinitions;

            protected $signature = 'test:command {sliceName} {--dir=}';

            public function handle()
            {
                $this->defineSliceFromArgument();

                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        // Test with backslashes - should convert to forward slashes
        $this->artisan('test:command', ['sliceName' => 'api\\posts'])
            ->expectsOutput('Please use forward slashes (/) instead of backslashes (\\) in slice paths.')
            ->assertSuccessful();

        // Should normalize to dot notation
        $this->assertEquals('api.posts', $command->getSliceName());
    }

    #[Test]
    public function it_parses_nested_slice_identifiers_with_slashes(): void
    {
        $command = new class extends Command {
            use SliceDefinitions;
            use SliceMakeDefinitions;

            protected $signature = 'test:command {sliceName} {--dir=}';

            public function handle()
            {
                $this->defineSliceFromArgument();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSliceProjectPath(): ?string
            {
                return $this->sliceProjectPath();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command', ['sliceName' => 'api/posts'])
            ->assertSuccessful();

        $this->assertEquals('api.posts', $command->getSliceName());
        $this->assertEquals('src/api/posts', $command->getSliceProjectPath());
    }

    #[Test]
    public function it_parses_nested_slice_identifiers_with_dots(): void
    {
        $command = new class extends Command {
            use SliceDefinitions;
            use SliceMakeDefinitions;

            protected $signature = 'test:command {sliceName} {--dir=}';

            public function handle()
            {
                $this->defineSliceFromArgument();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSliceProjectPath(): ?string
            {
                return $this->sliceProjectPath();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command', ['sliceName' => 'api.posts'])
            ->assertSuccessful();

        $this->assertEquals('api.posts', $command->getSliceName());
        $this->assertEquals('src/api/posts', $command->getSliceProjectPath());
    }

    #[Test]
    public function it_returns_null_when_no_slice_option_provided(): void
    {
        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:command {--slice=}';

            public function handle()
            {
                $this->resolveSliceFromOption();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function isRunInSlice(): bool
            {
                return $this->runInSlice();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command')
            ->assertSuccessful();

        $this->assertNull($command->getSliceName());
        $this->assertFalse($command->isRunInSlice());
    }

    #[Test]
    public function it_checks_slice_uses_connection(): void
    {
        $slice = (new Slice($this->testSliceName, $this->testSlicePath))
            ->setName($this->testSliceName)
            ->setPath($this->testSlicePath)
            ->setNamespace('Domain\\TestSlice')
            ->useConnection('mysql');

        SliceRegistry::register($slice);

        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:command {--slice=}';

            public function handle()
            {
                $this->resolveSliceFromOption();
                return 0;
            }

            public function checkUsesConnection(): bool
            {
                return $this->sliceUsesConnection();
            }

            public function getConnection(): ?string
            {
                return $this->sliceConnection();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command', ['--slice' => $this->testSliceName])
            ->assertSuccessful();

        $this->assertTrue($command->checkUsesConnection());
        $this->assertEquals('mysql', $command->getConnection());
    }

    #[Test]
    public function it_returns_null_connection_when_slice_does_not_use_connection(): void
    {
        $slice = (new Slice($this->testSliceName, $this->testSlicePath))
            ->setName($this->testSliceName)
            ->setPath($this->testSlicePath)
            ->setNamespace('Domain\\TestSlice');

        SliceRegistry::register($slice);

        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:command {--slice=}';

            public function handle()
            {
                $this->resolveSliceFromOption();
                return 0;
            }

            public function checkUsesConnection(): bool
            {
                return $this->sliceUsesConnection();
            }

            public function getConnection(): ?string
            {
                return $this->sliceConnection();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:command', ['--slice' => $this->testSliceName])
            ->assertSuccessful();

        $this->assertFalse($command->checkUsesConnection());
        $this->assertNull($command->getConnection());
    }

    /**
     * Data provider for slice path variations.
     *
     * Format: [inputPath, expectedSliceName, expectedProjectPath, expectedNamespace, expectedTestNamespace]
     *
     * @return array<string, array{
     *     inputPath: string,
     *     expectedSliceName: string,
     *     expectedProjectPath: string,
     *     expectedNamespace: string,
     *     expectedTestNamespace: string,
     *     expectedFolderName: string
     * }>
     */
    public static function sliceLocationProvider(): array
    {
        return [
            'flat slice - simple name' => [
                'inputPath' => 'blog',
                'expectedSliceName' => 'blog',
                'expectedProjectPath' => 'src/blog',
                'expectedNamespace' => 'Slice\\Blog',
                'expectedTestNamespace' => 'Test\\Blog',
                'expectedFolderName' => 'blog',
            ],
            'flat slice - kebab case' => [
                'inputPath' => 'user-management',
                'expectedSliceName' => 'user-management',
                'expectedProjectPath' => 'src/user-management',
                'expectedNamespace' => 'Slice\\UserManagement',
                'expectedTestNamespace' => 'Test\\UserManagement',
                'expectedFolderName' => 'user-management',
            ],
            'flat slice - pascal case input' => [
                'inputPath' => 'UserManagement',
                'expectedSliceName' => 'user-management',
                'expectedProjectPath' => 'src/user-management',
                'expectedNamespace' => 'Slice\\UserManagement',
                'expectedTestNamespace' => 'Test\\UserManagement',
                'expectedFolderName' => 'user-management',
            ],
            'nested slice - two levels with slash' => [
                'inputPath' => 'api/posts',
                'expectedSliceName' => 'api.posts',
                'expectedProjectPath' => 'src/api/posts',
                'expectedNamespace' => 'Slice\\Api\\Posts',
                'expectedTestNamespace' => 'Test\\Api\\Posts',
                'expectedFolderName' => 'posts',
            ],
            'nested slice - two levels with dot' => [
                'inputPath' => 'api.users',
                'expectedSliceName' => 'api.users',
                'expectedProjectPath' => 'src/api/users',
                'expectedNamespace' => 'Slice\\Api\\Users',
                'expectedTestNamespace' => 'Test\\Api\\Users',
                'expectedFolderName' => 'users',
            ],
            'nested slice - three levels' => [
                'inputPath' => 'admin/api/users',
                'expectedSliceName' => 'admin.api.users',
                'expectedProjectPath' => 'src/admin/api/users',
                'expectedNamespace' => 'Slice\\Admin\\Api\\Users',
                'expectedTestNamespace' => 'Test\\Admin\\Api\\Users',
                'expectedFolderName' => 'users',
            ],
            'nested slice - four levels' => [
                'inputPath' => 'modules/admin/api/v2',
                'expectedSliceName' => 'modules.admin.api.v2',
                'expectedProjectPath' => 'src/modules/admin/api/v2',
                'expectedNamespace' => 'Slice\\Modules\\Admin\\Api\\V2',
                'expectedTestNamespace' => 'Test\\Modules\\Admin\\Api\\V2',
                'expectedFolderName' => 'v2',
            ],
            'nested slice - kebab case segments' => [
                'inputPath' => 'user-facing/blog-posts',
                'expectedSliceName' => 'user-facing.blog-posts',
                'expectedProjectPath' => 'src/user-facing/blog-posts',
                'expectedNamespace' => 'Slice\\UserFacing\\BlogPosts',
                'expectedTestNamespace' => 'Test\\UserFacing\\BlogPosts',
                'expectedFolderName' => 'blog-posts',
            ],
            'nested slice - pascal parent with kebab slice' => [
                'inputPath' => 'Api/user-management',
                'expectedSliceName' => 'Api.user-management',
                'expectedProjectPath' => 'src/Api/user-management',
                'expectedNamespace' => 'Slice\\Api\\UserManagement',
                'expectedTestNamespace' => 'Test\\Api\\UserManagement',
                'expectedFolderName' => 'user-management',
            ],
        ];
    }

    #[Test]
    #[DataProvider('sliceLocationProvider')]
    public function it_computes_correct_paths_and_namespaces_for_slice_creation(
        string $inputPath,
        string $expectedSliceName,
        string $expectedProjectPath,
        string $expectedNamespace,
        string $expectedTestNamespace,
        string $expectedFolderName
    ): void
    {
        $command = new class extends Command {
            use SliceDefinitions;
            use SliceMakeDefinitions;

            protected $signature = 'test:slice-creation {sliceName} {--dir=}';

            public function handle(): int {
                $this->defineSliceFromArgument();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSlicePath(): ?string
            {
                return $this->slicePath ?? null;
            }

            public function getSliceFolderName(): ?string
            {
                return $this->sliceFolderName ?? null;
            }

            public function getSliceNamespace(): ?string
            {
                return $this->sliceNamespace ?? null;
            }

            public function getSliceTestNamespace(): ?string
            {
                return $this->sliceTestNamespace ?? null;
            }

            public function getSliceProjectPath(): string {
                return $this->sliceProjectPath();
            }

            public function getSliceSourcePath(?string $dir = null): string {
                return $this->sliceSourcePath($dir);
            }

            public function getSliceMigrationPath(): string {
                return $this->sliceMigrationPath();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:slice-creation', ['sliceName' => $inputPath])
            ->assertSuccessful();

        // Assert slice identification
        $this->assertEquals($expectedSliceName, $command->getSliceName(), 'Slice name mismatch');
        $this->assertEquals($expectedFolderName, $command->getSliceFolderName(), 'Folder name mismatch');

        // Assert paths (normalize separators for cross-platform compatibility)
        $this->assertEquals($expectedProjectPath, $command->getSliceProjectPath(), 'Project path mismatch');
        $this->assertPathEquals(
            base_path($expectedProjectPath),
            $command->getSlicePath(),
            'Absolute path mismatch'
        );
        $this->assertPathEquals(
            base_path($expectedProjectPath . '/src'),
            $command->getSliceSourcePath(),
            'Source path mismatch'
        );
        $this->assertPathEquals(
            base_path($expectedProjectPath . '/database/migrations'),
            $command->getSliceMigrationPath(),
            'Migration path mismatch'
        );

        // Assert namespaces
        $this->assertEquals($expectedNamespace, $command->getSliceNamespace(), 'Namespace mismatch');
        $this->assertEquals($expectedTestNamespace, $command->getSliceTestNamespace(), 'Test namespace mismatch');
    }

    /**
     * Data provider for registry-based slice lookups.
     *
     * @return array<string, array{
     *     sliceName: string,
     *     slicePath: string,
     *     namespace: string,
     *     expectedTestNamespace: string
     * }>
     */
    public static function registrySliceProvider(): array
    {
        return [
            'flat slice from registry' => [
                'sliceName' => 'blog',
                'slicePath' => 'src/blog',
                'namespace' => 'Slice\\Blog',
                'expectedTestNamespace' => 'Test\\Blog',
            ],
            'nested slice from registry' => [
                'sliceName' => 'api.posts',
                'slicePath' => 'src/api/posts',
                'namespace' => 'Slice\\Api\\Posts',
                'expectedTestNamespace' => 'Test\\Api\\Posts',
            ],
            'deeply nested slice from registry' => [
                'sliceName' => 'admin.api.v2.users',
                'slicePath' => 'src/admin/api/v2/users',
                'namespace' => 'Slice\\Admin\\Api\\V2\\Users',
                'expectedTestNamespace' => 'Test\\Admin\\Api\\V2\\Users',
            ],
        ];
    }

    #[Test]
    #[DataProvider('registrySliceProvider')]
    public function it_retrieves_correct_values_from_registered_slice(
        string $sliceName,
        string $slicePath,
        string $namespace,
        string $expectedTestNamespace
    ): void
    {
        $absolutePath = base_path($slicePath);

        // Create the directory structure
        File::ensureDirectoryExists($absolutePath);

        // Register the slice
        $slice = (new Slice())
            ->setName($sliceName)
            ->setPath($absolutePath)
            ->setNamespace($namespace);

        SliceRegistry::register($slice);

        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:registry-lookup {--slice=}';

            public function handle(): int {
                $this->resolveSliceFromOption();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSlicePath(): ?string
            {
                return $this->slicePath ?? null;
            }

            public function getSliceNamespace(): ?string
            {
                return $this->sliceNamespace ?? null;
            }

            public function getSliceTestNamespace(): ?string
            {
                return $this->sliceTestNamespace ?? null;
            }

            public function getSliceProjectPath(): string {
                return $this->sliceProjectPath();
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:registry-lookup', ['--slice' => $sliceName])
            ->assertSuccessful();

        // Assert values match what was registered (normalize paths for cross-platform)
        $this->assertEquals($sliceName, $command->getSliceName(), 'Slice name mismatch');
        $this->assertPathEquals($absolutePath, $command->getSlicePath(), 'Path mismatch');
        $this->assertEquals($namespace, $command->getSliceNamespace(), 'Namespace mismatch');
        $this->assertEquals($expectedTestNamespace, $command->getSliceTestNamespace(), 'Test namespace mismatch');
        $this->assertEquals($slicePath, $command->getSliceProjectPath(), 'Project path mismatch');

        // Cleanup
        File::deleteDirectory($absolutePath);

        // Clean up parent directories if empty
        $this->cleanupEmptyParentDirectories($absolutePath);
    }

    /**
     * Assert two paths are equal, normalizing directory separators.
     */
    private function assertPathEquals(string $expected, string $actual, string $message = ''): void
    {
        $normalizedExpected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $expected);
        $normalizedActual = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $actual);

        $this->assertEquals($normalizedExpected, $normalizedActual, $message);
    }

    /**
     * Data provider for registry slices with custom (non-conventional) configurations.
     *
     * Tests scenarios where slice name, path, and namespace don't follow default conventions.
     *
     * @return array<string, array{
     *     sliceName: string,
     *     slicePath: string,
     *     namespace: string,
     *     expectedTestNamespace: string,
     *     expectedFolderName: string
     * }>
     */
    public static function customRegistrySliceProvider(): array
    {
        return [
            'custom name and namespace - deeply nested path' => [
                'sliceName' => 'api-user',
                'slicePath' => 'src/admin/api/v2/users',
                'namespace' => 'Module\\User',
                'expectedTestNamespace' => 'Test\\Module\\User',
                'expectedFolderName' => 'users',
            ],
            'simple name with complex path' => [
                'sliceName' => 'auth',
                'slicePath' => 'src/platform/services/authentication/core',
                'namespace' => 'Auth\\Core',
                'expectedTestNamespace' => 'Test\\Auth\\Core',
                'expectedFolderName' => 'core',
            ],
            'dotted name with flat path' => [
                'sliceName' => 'acme.billing.subscriptions',
                'slicePath' => 'src/billing',
                'namespace' => 'Acme\\Billing\\Subscriptions',
                'expectedTestNamespace' => 'Test\\Acme\\Billing\\Subscriptions',
                'expectedFolderName' => 'billing',
            ],
            'vendor-style namespace' => [
                'sliceName' => 'payments',
                'slicePath' => 'src/integrations/stripe',
                'namespace' => 'Vendor\\Stripe\\Payments',
                'expectedTestNamespace' => 'Test\\Vendor\\Stripe\\Payments',
                'expectedFolderName' => 'stripe',
            ],
            'kebab name with pascal namespace' => [
                'sliceName' => 'user-profile-settings',
                'slicePath' => 'src/users/profile',
                'namespace' => 'App\\Domain\\UserProfileSettings',
                'expectedTestNamespace' => 'Test\\App\\Domain\\UserProfileSettings',
                'expectedFolderName' => 'profile',
            ],
        ];
    }

    #[Test]
    #[DataProvider('customRegistrySliceProvider')]
    public function it_preserves_custom_slice_configuration_from_registry(
        string $sliceName,
        string $slicePath,
        string $namespace,
        string $expectedTestNamespace,
        string $expectedFolderName
    ): void
    {
        $absolutePath = base_path($slicePath);

        // Create the directory structure
        File::ensureDirectoryExists($absolutePath);

        // Register the slice with custom (non-conventional) configuration
        $slice = (new Slice())
            ->setName($sliceName)
            ->setPath($absolutePath)
            ->setNamespace($namespace);

        SliceRegistry::register($slice);

        $command = new class extends Command {
            use SliceDefinitions;

            protected $signature = 'test:custom-registry {--slice=}';

            public function handle(): int {
                $this->resolveSliceFromOption();
                return 0;
            }

            public function getSliceName(): ?string
            {
                return $this->sliceName ?? null;
            }

            public function getSlicePath(): ?string
            {
                return $this->slicePath ?? null;
            }

            public function getSliceFolderName(): ?string
            {
                return $this->sliceFolderName ?? null;
            }

            public function getSliceNamespace(): ?string
            {
                return $this->sliceNamespace ?? null;
            }

            public function getSliceTestNamespace(): ?string
            {
                return $this->sliceTestNamespace ?? null;
            }

            public function getSliceProjectPath(): string {
                return $this->sliceProjectPath();
            }

            public function getSliceSourcePath(?string $dir = null): string {
                return $this->sliceSourcePath($dir);
            }
        };

        $this->app->make('Illuminate\Contracts\Console\Kernel')->registerCommand($command);

        $this->artisan('test:custom-registry', ['--slice' => $sliceName])
            ->assertSuccessful();

        // Assert custom values are preserved from registry
        $this->assertEquals($sliceName, $command->getSliceName(), 'Slice name should match registered value');
        $this->assertPathEquals($absolutePath, $command->getSlicePath(), 'Path should match registered value');
        $this->assertEquals($namespace, $command->getSliceNamespace(), 'Namespace should match registered value');
        $this->assertEquals($expectedTestNamespace, $command->getSliceTestNamespace(), 'Test namespace should be derived from registered namespace');
        $this->assertEquals($expectedFolderName, $command->getSliceFolderName(), 'Folder name should be derived from path');
        $this->assertEquals($slicePath, $command->getSliceProjectPath(), 'Project path should match registered path');

        // Verify source path is correctly derived
        $this->assertPathEquals(
            $absolutePath . '/src',
            $command->getSliceSourcePath(),
            'Source path should be derived from registered path'
        );

        // Cleanup
        File::deleteDirectory($absolutePath);
        $this->cleanupEmptyParentDirectories($absolutePath);
    }

    /**
     * Helper to clean up empty parent directories after test.
     */
    private function cleanupEmptyParentDirectories(string $path): void
    {
        $srcDir = base_path(config('laravel-slice.root.folder', 'src'));
        $parent = dirname($path);

        while ($parent !== $srcDir && File::exists($parent))
        {
            if (count(File::directories($parent)) === 0 && count(File::files($parent)) === 0)
            {
                File::deleteDirectory($parent);
                $parent = dirname($parent);
            }
            else
            {
                break;
            }
        }

        // Clean up src dir if empty
        if (File::exists($srcDir) && count(File::directories($srcDir)) === 0 && count(File::files($srcDir)) === 0)
        {
            File::deleteDirectory($srcDir);
        }
    }
}

