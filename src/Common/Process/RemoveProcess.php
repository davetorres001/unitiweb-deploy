<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class RemoveProcess implements ProcessInterface
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
        $remove = $this->config->getRemove();

        $this->output->header('Remove Unwanted Files');

        foreach ($remove as $file) {
            $path = $release . $file;
//            print_r($path); exit;
            if (is_file($path)) {
                $this->process->run("rm $path");
            }
        }

        $this->output->line('yellow');
    }
}
