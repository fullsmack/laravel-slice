<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Test\Double;

use Illuminate\Console\Command;

final class CommandFake extends Command
{
    protected $signature = 'fake:command';

    protected $description = 'A fake command for testing purposes';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
