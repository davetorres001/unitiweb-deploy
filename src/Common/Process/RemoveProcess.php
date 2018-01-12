<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;

class RemoveProcess implements ProcessInterface
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
        $remove = $this->config->getRemove();

        $this->output->header('Remove Unwanted Files');

        foreach ($remove as $file) {
            $path = $release . $file;
            if (is_file($path)) {
                $this->process->run("rm $path", null, $this->env->getUseSudo());
                $this->output->writeln("removed: $path");
            }
        }

        $this->output->line('yellow');
    }
}
