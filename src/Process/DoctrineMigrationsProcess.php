<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Process;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Process\ProcessInterface;

class DoctrineMigrationsProcess implements ProcessInterface
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
        $release = $this->config->getCurrentReleasePath();

        if (null === $release) {
            $this->output->error('No current release is configured');
        }

        if (!is_dir($release)) {
            $this->output->error('The release directory does not exist (' . $release . ')');
        }

        $this->output->header('Running Database Migrations');
        $this->process->run("php bin/console doctrine:migrations:migrate", $release);
        $this->output->line('yellow');
    }
}
