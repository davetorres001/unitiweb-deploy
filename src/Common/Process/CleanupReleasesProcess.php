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
        $environment = $this->env->getEnvironment();
        $sudo = true === $environment['UseSudo'] ? 'sudo ' : '';

        $this->output->header('Remove Unwanted Files');

        $outDated = new OutDatedReleases($this->config, $this->env);
        $remove = $outDated->find();

        foreach ($remove as $dir) {

            // Change files back to pre group so it can be deleted
            foreach ($chown['Pre']['Paths'] as $path) {
                $command = $this->makeCommand(
                    $sudo,
                    'chown',
                    is_dir($dir . '/' . $path) ? '-R' : '',
                    $chown['Pre']['Group'],
                    $dir . '/' . $path
                );
                $this->output->writeln($command);
                $this->process->run($command, null, $environment['UseSudo']);
            }

            // Change files back to pre permissions so it can be deleted
            foreach ($chmod['Pre']['Paths'] as $path) {
                $command = $this->makeCommand(
                    $sudo,
                    'chmod',
                    is_dir($dir . '/' . $path) ? '-R' : '',
                    $chmod['Pre']['Permission'],
                    $dir . '/' . $path
                );
                $this->output->writeln($command);
                $this->process->run($command, null, $environment['UseSudo']);
            }

            $this->output->writeln('Removing release '. $dir);
            $this->process->run("$sudo rm -rf $dir");
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
