<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;

class CopyRepoProcess implements ProcessInterface
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

        $directory = date('Y-m-d-H-i-s');
        $release = $this->env->getReleasesPath() . $directory . '/';

        $this->output->header('Copy Repo to Release Directory');

        $this->output->writeln('Create release directory');
        $this->process->run("mkdir $release");

        $this->output->writeln('Copying files over');
        $this->process->run("cp -a {$this->env->getRepoPath()}/* $release");

        $this->env->setCurrent($directory);
        $this->env->save();

        $this->output->line('yellow');
    }
}
