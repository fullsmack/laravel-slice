<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Foundation\Console\TestMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class MakeTest extends TestMakeCommand
{
    use SliceDefinitions;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:test';

    protected $signature = 'make:test {name} {--force} {--unit} {--pest} {--slice=}';

    public function handle()
    {
        $this->defineSliceUsingOption();

        parent::handle();
    }
    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        if(!$this->sliceName)
        {
            return parent::getPath($name);
        }

        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return "{$this->slicePath}/tests".
            str_replace('\\', '/', $name).'.php';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    protected function rootNamespace()
    {
        if(!isset($this->sliceName))
        {
            return parent::rootNamespace();
        }

        return 'Test\\'. Str::studly($this->sliceName);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the test already exists'],
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test'],
            ['pest', 'p', InputOption::VALUE_NONE, 'Create a Pest test'],
            ['slice', 's', InputOption::VALUE_NONE, 'Create a test in a slice or module'],
        ];
    }
}