<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test;

use FullSmack\LaravelSlice\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Schema;
use FullSmack\LaravelSlice\Slice;
use FullSmack\LaravelSlice\Test\Double\ModelFake;
use FullSmack\LaravelSlice\Test\Double\SliceServiceProviderFake;

class ConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ModelFake::resetConnection();
    }

    protected function tearDown(): void
    {
        ModelFake::resetConnection();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Set up a separate slice connection using SQLite
        config()->set('database.connections.slice_db', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    #[Test]
    public function it_can_configure_slice_with_explicit_connection(): void
    {
        $provider = (new SliceServiceProviderFake($this->app, 'connection-slice'))
            ->withConnection('slice_db');

        $provider->register();
        $provider->boot();

        $slice = $provider->getSlice();

        $this->assertTrue($slice->usesConnection());
        $this->assertSame('slice_db', $slice->connection());
    }

    #[Test]
    public function uses_connection_trait_sets_model_connection(): void
    {
        ModelFake::useConnection('slice_db');

        $this->assertSame('slice_db', ModelFake::getSliceConnection());
    }

    #[Test]
    public function uses_connection_trait_initializes_model_with_connection(): void
    {
        ModelFake::useConnection('slice_db');

        $model = new ModelFake();

        $this->assertSame('slice_db', $model->getConnectionName());
    }

    #[Test]
    public function bind_models_to_connection_sets_connection_on_models(): void
    {
        $provider = (new SliceServiceProviderFake($this->app, 'model-binding-slice'))
            ->withConnection('slice_db')
            ->withModels(ModelFake::class);

        $provider->register();
        $provider->boot();

        $this->assertSame('slice_db', ModelFake::getSliceConnection());

        $model = new ModelFake();
        $this->assertSame('slice_db', $model->getConnectionName());
    }

    #[Test]
    public function bind_models_to_connection_does_nothing_when_no_connection(): void
    {
        $provider = (new SliceServiceProviderFake($this->app, 'no-connection-slice'))
            ->withModels(ModelFake::class);

        $provider->register();
        $provider->boot();

        $this->assertNull(ModelFake::getSliceConnection());
    }

    #[Test]
    public function slice_migration_trait_uses_correct_connection(): void
    {
        // Create a migration class that uses the SliceMigration trait
        $migration = new class {
            use \FullSmack\LaravelSlice\Database\SliceMigration;

            private ?string $connection = 'slice_db';

            public function getConnection(): ?string
            {
                return $this->connection;
            }

            public function getSchemaBuilder(): \Illuminate\Database\Schema\Builder
            {
                return $this->schema();
            }
        };

        $schemaBuilder = $migration->getSchemaBuilder();

        $this->assertInstanceOf(\Illuminate\Database\Schema\Builder::class, $schemaBuilder);
    }

    #[Test]
    public function slice_migration_trait_uses_default_schema_when_no_connection(): void
    {
        $migration = new class {
            use \FullSmack\LaravelSlice\Database\SliceMigration;

            private ?string $connection = null;

            public function getConnection(): ?string
            {
                return $this->connection;
            }

            public function getSchemaBuilder(): \Illuminate\Database\Schema\Builder
            {
                return $this->schema();
            }
        };

        $schemaBuilder = $migration->getSchemaBuilder();

        $this->assertInstanceOf(\Illuminate\Database\Schema\Builder::class, $schemaBuilder);
    }
}
