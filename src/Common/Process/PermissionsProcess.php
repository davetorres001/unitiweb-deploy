<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class PermissionsProcess implements ProcessInterface
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

    /**
     * @var string
     */
    protected $prePost;

    public function __construct(Config $config, DeployOutput $output, DeployProcess $process = null)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->output = $output;
        $this->process = $process ?? new DeployProcess($output, $config);
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

        $release = $this->config->getCurrentReleasePath();
        $environment = $this->config->getEnvironment();
        $chown = $this->config->getChown();
        $chmod = $this->config->getChmod();
        $sudo = true === $environment['UseSudo'] ? 'sudo ' : '';

        $this->output->header("{$this->prePost} Chown");

        foreach ($chown[$this->prePost]['Paths'] as $path) {
            $command = $this->makeCommand(
                $sudo,
                'chown',
                is_dir($release . $path) ? '-R' : '',
                $chown[$this->prePost]['Group'],
                $release . $path
            );
            $this->output->writeln($command);
            $this->process->run($command, null, $environment['UseSudo']);
        }

        $this->output->line('yellow');

        $this->output->header("{$this->prePost} Chmod");

        foreach ($chmod[$this->prePost]['Paths'] as $path) {
            $command = $this->makeCommand(
                $sudo,
                'chmod',
                is_dir($release . $path) ? '-R' : '',
                $chmod[$this->prePost]['Permission'],
                $release . $path
            );
            $this->output->writeln($command);
            $this->process->run($command, null, $environment['UseSudo']);
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
