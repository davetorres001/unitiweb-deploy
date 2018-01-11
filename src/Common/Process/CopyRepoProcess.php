<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class CopyRepoProcess implements ProcessInterface
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

        $paths = $this->config->getPaths();

        $directory = date('Y-m-d-H-i-s');
        $release = $paths['Releases'] . $directory . '/';

        $this->output->header('Copy Repo to Release Directory');

        $this->output->writeln('Create release directory');
        $this->process->run("mkdir $release");

        $this->output->writeln('Copying files over');
        $this->process->run("cp -a {$paths['Repo']}/* $release");

        $this->config->setEnvironment('Current', $directory);
        $this->config->save();

        $this->output->line('yellow');
    }
}
