<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

use FullSmack\LaravelSlice\Command\SliceDefinitions;

class MakeComponent extends ComponentMakeCommand
{
    use SliceDefinitions;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new view component class in a slice';

    public function handle()
    {
        $this->defineSliceUsingOption();

        parent::handle();
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        if ($this->option('inline') || !$this->createInSlice())
        {
            parent::buildClass($name);
        }

        return str_replace(
            ['DummyView', '{{ view }}'],
            "view('{$this->sliceName}::components.{$this->getView()}')",
            GeneratorCommand::buildClass($name)
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['slice', null, InputOption::VALUE_OPTIONAL, 'Create the component in a slice'],
        ]);
    }
}
