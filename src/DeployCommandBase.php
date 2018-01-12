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
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;
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
        $this->env = new Env($this->output, null, $this->configPath);
        $this->env->load();

        // Load the configuration
        $this->config = new Config($this->output, $this->configPath);
        $this->config->load();

        // Check to make sure the release structure exists
        $structure = new ConfigDirectoryStructure($this->output, $this->env);
        $structure->check();

        $this->preDeploy();

        $this->prePullRepo();
        $this->pullRepo();
        $this->postPullRepo();

        $this->preCopyRepo();
        $this->copyRepo();
        $this->postCopyRepo();

        $this->prePrePermissions();
        $this->prePermissions();
        $this->postPrePermissions();

        $this->preSharedSymlinks();
        $this->sharedSymlinks();
        $this->postSharedSymlinks();

        $this->preRemoveFiles();
        $this->removeFiles();
        $this->postRemoveFiles();

        $this->postDeploy();

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

        $pull = new GitHubPullProcess($this->config, $this->env, $this->output);
        $pull->execute();
    }

    /**
     * Copy repo
     */
    protected function copyRepo()
    {
        assert(valid_num_args());

        $copy = new CopyRepoProcess($this->config, $this->env, $this->output);
        $copy->execute();
    }

    /**
     * Set pre permissions
     */
    protected function prePermissions()
    {
        assert(valid_num_args());

        $permissions = new PermissionsProcess($this->config, $this->env, $this->output);
        $permissions->setPre();
        $permissions->execute();
    }

    /**
     * Create shared symlinks
     */
    protected function sharedSymlinks()
    {
        assert(valid_num_args());

        $symlinks = new SymlinksProcess($this->config, $this->env, $this->output);
        $symlinks->execute();
    }

    /**
     * Remove unwanted files process
     */
    protected function removeFiles()
    {
        assert(valid_num_args());

        $remove = new RemoveProcess($this->config, $this->env, $this->output);
        $remove->execute();
    }

    /**
     * Rollback Process
     */
    protected function rollback()
    {
        assert(valid_num_args());

    }

    /**
     * Live Process
     */
    protected function live()
    {
        assert(valid_num_args());

        $permissions = new PermissionsProcess($this->config, $this->env, $this->output);
        $permissions->setPost();
        $permissions->execute();

        $live = new MakeLiveProcess($this->config, $this->env, $this->output);
        $live->execute();
    }

    /**
     * Cleanup Process
     */
    protected function cleanup()
    {
        assert(valid_num_args());

        $cleanup = new CleanupReleasesProcess($this->config, $this->env, $this->output);
        $cleanup->execute();
    }

    protected function preDeploy() {
        assert(valid_num_args());

    }

    protected function prePullRepo() {
        assert(valid_num_args());

    }

    protected function postPullRepo() {
        assert(valid_num_args());

    }

    protected function preCopyRepo() {
        assert(valid_num_args());

    }

    protected function postCopyRepo() {
        assert(valid_num_args());

    }

    protected function prePrePermissions() {
        assert(valid_num_args());

    }

    protected function postPrePermissions() {
        assert(valid_num_args());

    }

    protected function preSharedSymlinks() {
        assert(valid_num_args());

    }

    protected function postSharedSymlinks() {
        assert(valid_num_args());

    }

    protected function preRemoveFiles() {
        assert(valid_num_args());

    }

    protected function postRemoveFiles() {
        assert(valid_num_args());

    }

    protected function postDeploy() {
        assert(valid_num_args());

    }
}
