<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;
use Unitiweb\Deploy\Common\OutDatedReleases;

class CleanupReleasesProcess implements ProcessInterface
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

        $chown = $this->config->getChown();
        $chmod = $this->config->getChmod();

        $this->output->header('Remove Unwanted Files');

        $outDated = new OutDatedReleases($this->config, $this->env);
        $remove = $outDated->find();

        foreach ($remove as $dir) {

            // Change files back to pre group so it can be deleted
            foreach ($chown['Pre']['Paths'] as $path) {
                $command = $this->makeCommand(
                    'chown',
                    is_dir($dir . '/' . $path) ? '-R' : '',
                    $chown['Pre']['Group'],
                    $dir . '/' . $path
                );
                $this->output->writeln($command);
                $this->process->run($command, null, $this->env->getUseSudo());
            }

            // Change files back to pre permissions so it can be deleted
            foreach ($chmod['Pre']['Paths'] as $path) {
                $command = $this->makeCommand(
                    'chmod',
                    is_dir($dir . '/' . $path) ? '-R' : '',
                    $chmod['Pre']['Permission'],
                    $dir . '/' . $path
                );
                $this->output->writeln($command);
                $this->process->run($command, null, $this->env->getUseSudo());
            }

            $this->output->writeln('Removing release '. $dir);
            $this->process->run("rm -rf $dir", null, $this->env->getUseSudo());
        }

        $this->output->line('yellow');
    }

    /**
     * Make command string
     */
    protected function makeCommand() : string
    {
        // Any number of arguments are allowed

        $parts = [];
        foreach (func_get_args() as $arg) {
            if (strlen((string) $arg)) {
                array_push($parts, $arg);
            }
        }

        return implode(' ', $parts);
    }
}
