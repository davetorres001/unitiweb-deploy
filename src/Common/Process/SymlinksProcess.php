<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class SymlinksProcess implements ProcessInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var DeployProcess
     */
    protected $process;

    public function __construct(Config $config, DeployOutput $output, DeployProcess $process = null)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->output = $output;
        $this->process = $process ?? new DeployProcess($output, $config);
    }

    /**
     * Execute the process
     */
    public function execute()
    {
        assert(valid_num_args());

        $release = $this->config->getCurrentReleasePath();
        $paths = $this->config->getPaths();

        $this->output->header('Create Shared Symlinks');

        foreach ($this->config->getShared() as $file) {

            $origPath = $release . $file;
            $path = $paths['Shared'] . $file;

            if (!file_exists($path) && file_exists($origPath)) {
                $this->process->run("cp $origPath $path");
            }

            if (file_exists($origPath)) {
                $this->process->run("rm $origPath");
            }

            if (file_exists($path)) {
                $this->process->run("ln -s $path $origPath");
            } else {
                $this->output->error("The shared file $file does not exist");
            }
        }

        $this->output->line('yellow');
    }
}
