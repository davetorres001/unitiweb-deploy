<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Symfony\Component\Console\Input\ArrayInput;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;

class MakeLiveProcess implements ProcessInterface
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

        $current = $this->env->getCurrent();
        $paths = $this->env->getPaths();
        $source = $this->env->getCurrentReleasePath();
        $destination = $paths['Root'] . 'current';

        $this->output->header('Make Release Live');

        $this->output->writeln("Setting repo $current to Live");

        if (is_file($destination)) {
            $this->process->run("rm $destination");
        }

        $this->process->run("rm $destination");

        if (substr($source, -1) === '/') {
            $source = substr($source, 0, -1);
        }

        if (substr($source, 0, 3) === '../') {
            $source = substr($source, 3);
        }

        $this->process->run("ln -s $source $destination");

        $this->output->line('yellow');
    }
}
