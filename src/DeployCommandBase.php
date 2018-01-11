<?php
declare(strict_types=1);

namespace Unitiweb\Deploy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\ConfigDirectoryStructure;
use Unitiweb\Deploy\Common\DeployContainerTrait;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\Process\CleanupReleasesProcess;
use Unitiweb\Deploy\Common\Process\CopyRepoProcess;
use Unitiweb\Deploy\Common\Process\GitHubPullProcess;
use Unitiweb\Deploy\Common\Process\MakeLiveProcess;
use Unitiweb\Deploy\Common\Process\PermissionsProcess;
use Unitiweb\Deploy\Common\Process\ProcessInterface;
use Unitiweb\Deploy\Common\Process\RemoveProcess;
use Unitiweb\Deploy\Common\Process\SymlinksProcess;

class DeployCommandBase extends Command
{
    use LockableTrait;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var array
     */
    protected $processes;

    public function __construct(string $configPath = null)
    {
        parent::__construct(null);

        $this->configPath = $configPath;
    }

    /**
     * Configure the deploy command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            ->setName('deploy')
            ->setDescription('Deploys the new repo.')
            ->setHelp('This command will pull new version, create release, and set to live');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        $this->output = new DeployOutput($output, $input);

        // Make sure the deployment is not current running
        if (!$this->lock()) {
            $this->output->error('The command is already running in another process.');
        }

        // Load the configuration
        $this->config = new Config($this->output, null, $this->configPath);
        $this->config->load();
        $this->processes = $this->config->getProcesses();

        // Check to make sure the release structure exists
        $structure = new ConfigDirectoryStructure($this->output, $this->config);
        $structure->check();

        // Deploy Process
        $this->deployProcesses('Deploy', 'Pre');
        $this->pullRepo();
        $this->copyRepo();
        $this->prePermissions();
        $this->sharedSymlinks();
        $this->removeFiles();
        $this->deployProcesses('Deploy', 'Post');

        $this->rollback();
        $this->cleanup();
        $this->live();

        $this->output->header('Deploy Complete');
        $this->output->line('yellow');
        $this->output->blank();
    }

    /**
     * Deploy pull guithub repo
     */
    protected function pullRepo()
    {
        assert(valid_num_args());

        $pull = new GitHubPullProcess($this->config, $this->output);
        $pull->execute();
    }

    /**
     * Copy repo
     */
    protected function copyRepo()
    {
        assert(valid_num_args());

        $copy = new CopyRepoProcess($this->config, $this->output);
        $copy->execute();
    }

    /**
     * Set pre permissions
     */
    protected function prePermissions()
    {
        assert(valid_num_args());

        $permissions = new PermissionsProcess($this->config, $this->output);
        $permissions->setPre();
        $permissions->execute();
    }

    /**
     * Create shared symlinks
     */
    protected function sharedSymlinks()
    {
        assert(valid_num_args());

        $symlinks = new SymlinksProcess($this->config, $this->output);
        $symlinks->execute();
    }

    /**
     * Remove unwanted files process
     */
    protected function removeFiles()
    {
        assert(valid_num_args());

        $remove = new RemoveProcess($this->config, $this->output);
        $remove->execute();
    }

    /**
     * Rollback Process
     */
    protected function rollback()
    {
        assert(valid_num_args());

        $this->deployProcesses('Rollback', 'Pre');
        $this->deployProcesses('Rollback', 'Post');
    }

    /**
     * Live Process
     */
    protected function live()
    {
        assert(valid_num_args());

        $this->deployProcesses('Live', 'Pre');

        $permissions = new PermissionsProcess($this->config, $this->output);
        $permissions->setPost();
        $permissions->execute();

        $live = new MakeLiveProcess($this->config, $this->output);
        $live->execute();

        $this->deployProcesses('Live', 'Post');
    }

    /**
     * Cleanup Process
     */
    protected function cleanup()
    {
        assert(valid_num_args());

        $this->deployProcesses('Cleanup', 'Pre');

        $cleanup = new CleanupReleasesProcess($this->config, $this->output);
        $cleanup->execute();

        $this->deployProcesses('Cleanup', 'Post');
    }

    /**
     * Pre Deploy Hook
     */
    protected function deployProcesses(string $stage, string $prePost)
    {
        assert(valid_num_args());

        $env = $this->config->getEnvironment();
        $namespace = $env['Namespace'] ?? '';

        if (substr($namespace, -1) !== '\\') {
            $namespace = $namespace . '\\';
        }

        foreach ($this->processes[$stage][$prePost] as $class) {
            $fullClass = $namespace . 'Process\\' . $class;
            $process = new $fullClass($this->config, $this->output);
            assert($process instanceof ProcessInterface);
            $process->execute();
        }
    }
}
