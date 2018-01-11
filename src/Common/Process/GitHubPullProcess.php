<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;

class GitHubPullProcess implements ProcessInterface
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
        $paths = $this->config->getPaths();
        $github = $this->config->getGitHub();

        $this->output->header('Pulling the Git Repo');

        if (!is_dir($paths['Repo'] . '.git')) {

            // Initialize git in the repo directory
            $this->process->run('git init', $paths['Repo']);

            // Create the git remote for the repo
            $this->process->run("git remote add {$github['Remote']} {$github['Repo']}", $paths['Repo']);
        }

        // Pull the git repository
        $this->process->run("git pull {$github['Remote']} {$github['Branch']}", $paths['Repo']);

        $this->output->line('yellow');
    }
}
