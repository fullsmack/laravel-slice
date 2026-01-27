<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Foundation\Console\TestMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;
use FullSmack\LaravelSlice\Path;

class MakeTest extends TestMakeCommand
{
    use SliceDefinitions;

    /**
     * @var string
     */
    protected $name = 'make:test';

    /**
     * @var string
     */
    protected $signature = 'make:test {name} {--force} {--unit} {--pest} {--phpunit} {--slice=} {--dir=}';

    /**
     * @return bool|null
     */
    public function handle()
    {
        $this->resolveSliceFromOption();

        return parent::handle();
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        if (!$this->runInSlice())
        {
            return parent::getPath($name);
        }

        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->slicePath('tests') . DIRECTORY_SEPARATOR .
            Path::normalize($name) . '.php';
    }

    /**
     * @param string $rootNamespace
     * @return string
     */
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
        if (!$this->runInSlice())
        {
            return parent::rootNamespace();
        }

        return $this->sliceTestNamespace();
    }

    /**
     * @return array<array{
     *  0: string,
     *  1: string,
     *  2: int,
     *  3: string,
     * }>
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the test already exists'],
            ['unit', 'u', InputOption::VALUE_NONE, 'Create a unit test'],
            ['pest', 'p', InputOption::VALUE_NONE, 'Create a Pest test'],
            ['phpunit', null, InputOption::VALUE_NONE, 'Create a PHPUnit test'],
            ['slice', 's', InputOption::VALUE_OPTIONAL, 'Create a test in a slice or module'],
            ['dir', null, InputOption::VALUE_OPTIONAL, 'Subdirectory to create the test in'],
        ];
    }
}
