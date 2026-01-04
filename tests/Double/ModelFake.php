<?php
declare(strict_types=1);

namespace Tests\Double;

use Illuminate\Database\Eloquent\Model;
use FullSmack\LaravelSlice\Database\UsesConnection;

class ModelFake extends Model
{
    use UsesConnection;

    protected $table = 'fake_models';

    protected $fillable = ['name'];

    public $timestamps = false;

    /**
     * Reset the static connection for testing.
     */
    public static function resetConnection(): void
    {
        static::$sliceConnection = null;
    }

    /**
     * Get the current static slice connection for testing.
     */
    public static function getSliceConnection(): ?string
    {
        return static::$sliceConnection;
    }
}
