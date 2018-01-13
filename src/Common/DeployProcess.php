<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Process\Process;

class DeployProcess
{
    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var Env
     */
    protected $env;

    public function __construct(DeployOutput $output, Env $env)
    {
        assert(valid_num_args());

        $this->output = $output;
        $this->env = $env;
    }

    /**
     * run
     */
    public function run(string $command, $workingDir = null, bool $sudo = false)
    {
        assert(valid_num_args());

        $array = [];

        if (null !== $workingDir) {
            array_push($array, "cd $workingDir &&");
        }

        if (true === $sudo) {
            array_push($array, 'sudo');
        }

        array_push($array, $command);

        $process = new Process(implode(' ', $array));
        $process->start();
        if ($this->env->getProcessTimeout() > 0) {
            $process->setTimeout($this->env->getProcessTimeout());
        }
        $process->wait(function ($type, $buffer) {
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $this->output->writeln('<fg=green>>></> ' . $line);
                }
            }
        });
    }
}
