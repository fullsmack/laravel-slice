<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use FullSmack\LaravelSlice\Command\SliceDefinitions;
use FullSmack\LaravelSlice\SliceAlreadyExists;

class MakeSlice extends Command
{
    use SliceDefinitions;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:slice {sliceName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new slice';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->defineSliceUsingArgument();

        if(File::exists($this->slicePath))
        {
            $this->error("Slice with name \"{$this->sliceName}\" already exists");
            return;
        }

        $directories = [
            'config',
            'lang/en',
            'resources/views/components',
            'routes',
            'src',
            'tests',
        ];

        foreach ($directories as $directory)
        {
            File::makeDirectory("{$this->slicePath}/$directory", 0755, true, true);
        }

        $slicePascalName = Str::studly($this->sliceName);

        $stubPath = __DIR__ .'/../../stubs/SliceServiceProvider.stub';

        $serviceProviderContent = File::get($stubPath);

        $serviceProviderContent = Str::replace(
            ['{{slicePascalName}}', '{{sliceName}}'],
            [$slicePascalName, $this->sliceName],
            $serviceProviderContent
        );

        $serviceProviderPath = "{$this->slicePath}/src/{$slicePascalName}ServiceProvider.php";

        File::put($serviceProviderPath, $serviceProviderContent);

        $this->info("Slice \"{$this->sliceName}\" created successfully.");
    }
}
