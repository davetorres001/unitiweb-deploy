<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;

class PermissionsProcess implements ProcessInterface
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

    /**
     * @var string
     */
    protected $prePost;

    public function __construct(Config $config, Env $env, DeployOutput $output, DeployProcess $process = null)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->env = $env;
        $this->output = $output;
        $this->process = $process ?? new DeployProcess($output, $env);
    }

    /**
     * Set Pre
     */
    public function setPre()
    {
        assert(valid_num_args());

        $this->prePost = 'Pre';
    }

    /**
     * Set Post
     */
    public function setPost()
    {
        assert(valid_num_args());

        $this->prePost = 'Post';
    }

    /**
     * Execute the process
     */
    public function execute()
    {
        assert(valid_num_args());
        assert(in_array($this->prePost, ['Pre', 'Post']));

        $release = $this->env->getCurrentReleasePath();
        $chown = $this->config->getChown();
        $chmod = $this->config->getChmod();

        $this->output->header("{$this->prePost} Chown");

        foreach ($chown[$this->prePost]['Paths'] as $path) {
            $command = $this->makeCommand(
                'chown',
                is_dir($release . $path) ? '-R' : '',
                $chown[$this->prePost]['Group'],
                $release . $path
            );
            $this->output->writeln("UseSudo : " . $this->env->getUseSudo());
            $this->output->writeln($command);
            $this->process->run($command, null, $this->env->getUseSudo());
        }

        $this->output->line('yellow');

        $this->output->header("{$this->prePost} Chmod");

        foreach ($chmod[$this->prePost]['Paths'] as $path) {
            $command = $this->makeCommand(
                'chmod',
                is_dir($release . $path) ? '-R' : '',
                $chmod[$this->prePost]['Permission'],
                $release . $path
            );
            $this->output->writeln("UseSudo : " . $this->env->getUseSudo());
            $this->output->writeln($command);
            $this->process->run($command, null, $this->env->getUseSudo());
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
