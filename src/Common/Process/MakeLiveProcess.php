<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Symfony\Component\Console\Input\ArrayInput;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class MakeLiveProcess implements ProcessInterface
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

        $environment = $this->config->getEnvironment();
        $paths = $this->config->getPaths();
        $source = $this->config->getCurrentReleasePath();
        $destination = $paths['Root'] . 'current';

        $this->output->header('Make Release Live');

        $this->output->writeln("Setting repo {$environment['Current']} to Live");

        if (is_file($destination)) {
            $this->process->run("rm $destination");
        }

        $this->process->run("rm $destination");

        if (substr($source, -1) === '/') {
            $source = substr($source, 0, -1);
        }

        $this->process->run("ln -s $source $destination");

        $this->output->line('yellow');
    }
}
