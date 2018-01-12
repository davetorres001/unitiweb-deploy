<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ConfigDirectoryStructure
{
    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var Env
     */
    protected $env;

    public function __construct(DeployOutput $output, Env $env)
    {
        assert(valid_num_args());

        $this->output = $output;
        $this->env = $env;
    }

    /**
     * Check to see if directory structure exists
     */
    public function check() : bool
    {
        assert(valid_num_args());

        $paths = $this->env->getPaths();
        assert(array_key_exists('Root', $paths));

        if (null === $paths['Root']) {
            $this->output->error('Root path is not configured in the config.yml file');
        }

        if (false === $this->rootExists()) {
            $this->output->error('The root path either does not exist or points to a file');
        }

        $pass = true;
        foreach (['releases', 'repo', 'shared'] as $directory) {
            if (false === is_dir($paths['Root'] . $directory)) {
                $pass = false;
            }
        }

        if (false === $pass) {
            $this->output->error('The release directory structure is not value. You need to run the command: "php bin/console deploy:config"');
        }

        return true;
    }

    /**
     * Create the file structure if it doesn't exist
     */
    public function create() : bool
    {
        assert(valid_num_args());

        $this->output->header('Here are the current path settings');

        foreach (['Root', 'Repo', 'Releases', 'Shared'] as $path) {
            $this->askPath($path);
        }

        $this->output->line('yellow');

        return true;
    }

    /**
     * Ask about path
     */
    protected function askPath(string $pathName) : ?string
    {
        assert(valid_num_args());

        $paths = $this->env->getPaths();
        assert(array_key_exists($pathName, $paths));

        $newPath = null;
        while (null === $newPath) {
            $answer = $this->output->ask("$pathName's locations? [{$paths[$pathName]}] ", $paths[$pathName]);
            if (null !== $answer && $answer !== '') {
                if (true === $this->createDirectory($paths[$pathName])) {
                    $newPath =  $answer;
                }
            }
        }

        return $newPath;
    }

    /**
     * Create directory if needed
     */
    protected function createDirectory(string $path) : bool
    {
        assert(valid_num_args());

        if (is_file($path)) {
            $this->output->writeln('The path "' . $path . '" points to a file');
            return false;
        }

        $pass = null;
        if (!is_dir($path)) {
            while (null === $pass) {
                $answer = $this->output->ask('The directory does not exist. Create it? [yes]', 'yes', 'yellow');
                if ($answer === 'yes') {
                    if (mkdir($path)) {
                        $pass = true;
                    } else {
                        $this->output->error('The directory ' . $path . ' could not be created');
                    }
                } elseif ($answer === 'no') {
                    $pass = false;
                }
            }
        } else {
            $pass = true;
        }

        return $pass;
    }


    /**
     * Show paths table
     */
    protected function pathsTable()
    {
        assert(valid_num_args());

        $paths = $this->env->getPaths();

        $table = new Table($this->output->getOutput());
        $table
            ->setHeaders(['Setting', 'Path'])
            ->setRows(
                [
                    ['Root', $paths['Root']],
                    ['Repo', $paths['Repo']],
                    ['Releases', $paths['Releases']],
                    ['Shared', $paths['Shared']],
                ]
            );;
        $table->render();
    }

    /**
     * Check root path config
     */
    protected function rootExists() : bool
    {
        assert(valid_num_args());

        $paths = $this->env->getPaths();
        assert(array_key_exists('Root', $paths));

        if (null === ($paths['Root'] ?? null)) {
            return false;
        }

        return is_dir($paths['Root']);
    }
}
