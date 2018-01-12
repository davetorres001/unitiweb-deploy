<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;

class SymlinksProcess implements ProcessInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Env
     */
    protected $env;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var DeployProcess
     */
    protected $process;

    public function __construct(Config $config, Env $env, DeployOutput $output, DeployProcess $process = null)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->env = $env;
        $this->output = $output;
        $this->process = $process ?? new DeployProcess($output, $env);
    }

    /**
     * Execute the process
     */
    public function execute()
    {
        assert(valid_num_args());

        $release = $this->env->getCurrentReleasePath();

        $this->output->header('Create Shared Symlinks');

        foreach ($this->config->getShared() as $file) {

            $origPath = $release . $file;
            $path = $this->env->getSharedPath() . $file;

            // Create shared symliny directory if it doesn't exist
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            // Create the shared file if it doesn't exists.
            if (!file_exists($origPath)) {
                file_put_contents($origPath, '');
            }

            if (!file_exists($path) && file_exists($origPath)) {
                $this->process->run("cp $origPath $path");
            }

            if (file_exists($origPath)) {
                $this->process->run("rm $origPath");
            }

            if (file_exists($path)) {
                $this->process->run("ln -s $path $origPath");
                $this->output->writeln('symlink: ' . basename($origPath) . ' -> ' . $path);
            } else {
                $this->output->error("The shared file $file does not exist");
            }
        }

        $this->output->line('yellow');
    }
}
