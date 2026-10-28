<?php
declare(strict_types=1);

namespace FullSmack\LaravelSlice\Command;

use Illuminate\Foundation\Console\TestMakeCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;
use FullSmack\LaravelSlice\Path;
use FullSmack\LaravelSlice\SliceNotRegistered;

class MakeTest extends TestMakeCommand
{
    use SliceDefinitions;

    /**
     * Returns an exit code, since Laravel casts the parent's `false` to 0 (success).
     *
     * @return int
     */
    public function handle() // @phpstan-ignore method.childReturnType
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

        return parent::handle() === false ? self::FAILURE : self::SUCCESS;
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

        $name = ltrim(Str::replaceFirst($this->rootNamespace(), '', $name), '\\');

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
     * Added here rather than in getOptions(), because Laravel 13 defines
     * make:test with a $signature, which makes getOptions() unused.
     */
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('slice', 's', InputOption::VALUE_REQUIRED, 'Create a test in a slice or module');
    }
}
