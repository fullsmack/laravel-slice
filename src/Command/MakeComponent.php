<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Console\Command;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

use FullSmack\LaravelSlice\Command\SliceDefinitions;
use FullSmack\LaravelSlice\SliceNotRegistered;

class MakeComponent extends ComponentMakeCommand
{
    use SliceDefinitions;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new view component class in a slice';

    /**
     * @return void
     */
    public function handle()
    {
        $sliceName = $this->option('slice');

        if (!$sliceName || !is_string($sliceName))
        {
            parent::handle();

            return;
        }

        try {
            $this->loadFromRegistry($sliceName);
        }
        catch (SliceNotRegistered $e)
        {
            $this->error($e->getMessage());

            return;
        }

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
        if ($this->option('inline') || !$this->runInSlice())
        {
            return parent::buildClass($name);
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
     * @return array<array{
     *  0: string,
     *  1: string|null,
     *  2: int,
     *  3: string,
     * }>
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['slice', null, InputOption::VALUE_OPTIONAL, 'Create the component in a slice'],
            ['dir', null, InputOption::VALUE_OPTIONAL, 'Subdirectory to create the component in'],
        ]);
    }
}
