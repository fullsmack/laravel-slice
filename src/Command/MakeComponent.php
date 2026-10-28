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
     * @return int
     */
    public function handle()
    {
        $sliceName = $this->option('slice');

        if (is_string($sliceName) && $sliceName !== '')
        {
            try {
                $this->loadFromRegistry($sliceName);
            }
            catch (SliceNotRegistered $e)
            {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        /** @phpstan-ignore staticMethod.void (documented as void, but returns false when the class already exists) */
        return parent::handle() === false ? self::FAILURE : self::SUCCESS;
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
            "view('{$this->sliceName}::{$this->sliceView()}')",
            GeneratorCommand::buildClass($name)
        );
    }

    /**
     * Newer Laravel versions include the 'components.' prefix (or the --path
     * option) in getView(); older Laravel 11 releases add it in buildClass().
     */
    private function sliceView(): string
    {
        $view = $this->getView();

        $usesCustomPath = $this->hasOption('path') && is_string($this->option('path'));

        if ($usesCustomPath || str_starts_with($view, 'components.'))
        {
            return $view;
        }

        return 'components.' . $view;
    }

    /**
     * Added here rather than in getOptions(), so it keeps working if Laravel
     * switches make:component to a $signature (as it did for make:test).
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('slice', null, InputOption::VALUE_REQUIRED, 'Create the component in a slice');
    }
}
