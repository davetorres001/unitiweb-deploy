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

        $environment = $this->env->getEnvironment();
        $timeout = $environment['ProcessTimeout'];

        if (null !== $workingDir) {
            if (true === $sudo) {
                $command = "cd $workingDir && sudo $command";
            } else {
                $command = "cd $workingDir && $command";
            }
        }

        $process = new Process($command);
        $process->start();
        $process->setTimeout($timeout);
        $process->wait(function ($type, $buffer) {
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $this->output->writeln('<fg=green>>></> ' . $line);
                }
            }
        });

//        if (null !== ($results = shell_exec($command))) {
//            $lines = explode("\n", $results);
//            foreach ($lines as $line) {
//                $this->output->writeln($line);
//            }
//        }

//        if (true === $sudo) {
//            if (null !== ($results = shell_exec($command))) {
//                $lines = explode("\n", $results);
//                foreach ($lines as $line) {
//                    $this->output->writeln($line);
//                }
//            }
//        } else {
//            $process = new Process($command);
//            $process->start();
//            $process->wait(function ($type, $buffer) {
//                $lines = explode("\n", $buffer);
//                foreach ($lines as $line) {
//                    $line = trim($line);
//                    if ($line !== '') {
//                        $this->output->writeln('<fg=green>>>></> ' . $line);
//                    }
//                }
//            });
//        }
    }
}
