<?php
declare(strict_types=1);

namespace Unitiweb\Deploy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\ConfigDirectoryStructure;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Process\CleanupReleasesProcess;
use Unitiweb\Deploy\Common\Process\CopyRepoProcess;
use Unitiweb\Deploy\Common\Process\GitHubPullProcess;
use Unitiweb\Deploy\Common\Process\MakeLiveProcess;
use Unitiweb\Deploy\Common\Process\RemoveProcess;
use Unitiweb\Deploy\Common\Process\SymlinksProcess;
use Unitiweb\Deploy\Process\ComposerInstallProcess;
use Unitiweb\Deploy\Process\ProcessInterface;

class DeployRollbackCommand extends Command
{
    use LockableTrait;

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
     * Configure the deploy command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            ->setName('deploy:rollback')
            ->setDescription('Rollback to the previous deploy.')
            ->setHelp('This command will rollback to the previous deploy and delete the current.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        // Make sure the deployment is not current running
        if (!$this->lock()) {
            $this->output->error('The command is already running in another process.');
        }

        $this->output = new DeployOutput($output, $input);
        $this->config = new Config;
        $this->config->load($this->output);
        $this->process = new DeployProcess($this->output, $this->config);

        $root = $this->config->getPath('Root');
        $releases = $this->config->getPath('Releases');
        $sudo = true === $this->config->getUseSudo() ? 'sudo' : '';
        $group = $this->config->getPermissionsProcess();

        $this->output->header('Rolling Back Release');

        $dirs = [];
        foreach (scandir($releases) as $dir) {
            $path = $releases . $dir;
            if (is_dir($path) && substr($dir, 0, 1) !== '.') {
                array_push($dirs, $path);
            }
        }

        if (count($dirs) <= 1) {
            $this->output->error('There is no release to rollback to');
        }

        rsort($dirs);

        $current = $dirs[0];
        $source = $dirs[1];

        $this->output->writeln('Create symlink to previous release');
        $this->process->run("$sudo rm current", $root);
        $this->process->run("$sudo ln -s $source $root/current");

        $this->output->writeln('Removing current release');
        if ($group !== '') {
            $this->process->run("$sudo chown -R $group var/cache var/logs var/sessions", $current);
        }
        $this->process->run("$sudo rm -rf $current", $root);

        $this->output->line('yellow');

        $this->output->header('Rollback Complete');
        $this->output->line('yellow');
        $this->output->blank();
    }
}
