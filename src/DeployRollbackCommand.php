<?php
declare(strict_types=1);

namespace Unitiweb\Deploy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;
use Unitiweb\Deploy\Process\ComposerInstallProcess;
use Unitiweb\Deploy\Process\ProcessInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class DeployRollbackCommand extends Command
{
    use LockableTrait;

    const RELEASE_DATE_FORMAT = 'M jS, Y h:i:s a';

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @var Env
     */
    protected $env;

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
            ->setName('rollback')
            ->setDescription('Rollback to the previous deploy.')
            ->setHelp('This command will rollback to the previous deploy and delete the current.');
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

        $this->process = new DeployProcess($this->output, $this->env);

        $this->releases();
    }

    /**
     * Slow a list of current releases in revers order and allow the user to choose one
     */
    protected function releases()
    {
        assert(valid_num_args());

        $release = $this->askForRelease();

        if (null !== $release) {
            $this->rollbackTo($release);
        }

        $this->output->header('Deploy Complete');
        $this->output->line('yellow');
        $this->output->blank();
    }

    /**
     * Ask to select a release and return
     */
    protected function askForRelease() : ?string
    {
        assert(valid_num_args());

        $current = $this->env->getCurrent();

        $this->output->header('Rollback: Choose Release (listed: newest to oldest');
        $releases = $this->listReleases();
        $this->generateReleasesTable($releases);

        $release = null;
        $count = count($releases);

        while (true) {
            $answer = $this->output->ask('Enter the index of the release to restore:');
            $selected = (int) $answer;

            if ($selected === 0) {
                $this->output->writeln('');
                $this->output->writeln('Rollback Cancelling');
                $this->output->line();
                break;
            }

            if ($selected > $count) {
                $this->output->writeln('');
                $this->output->writeln('Selection is out of range');
                $this->output->line();
                continue;
            }

            if ($releases[$selected] === $current) {
                $this->output->writeln('');
                $this->output->writeln('Selected Release is already live');
                $this->output->line();
                break;
            }

            if ($selected > 0) {
                if (isset($releases[$selected])) {
                    $release = $releases[$selected];
                    break;
                }
            }
        }

        return $release;
    }

    /**
     * List the current releases
     */
    protected function listReleases() : array
    {
        assert(valid_num_args());

        $releases = [];

        $path = $this->env->getReleasesPath();

        $process = new Process("ls -r $path");
        $process->run();
        $lines = explode("\n", $process->getOutput());

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line !== '' && substr($line, 0, 1) !== '.') {
                $releases[$index + 1] = $line;
            }
        }

        return $releases;
    }


    /**
     * Generate tags table
     */
    protected function generateReleasesTable(array $releases)
    {
        assert(valid_num_args());

        $current = $this->env->getCurrent();

        $table = new Table($this->output->getOutput());
        $table->setHeaders(array('Index', 'Release Date <fg=yellow>(current in yellow)</>'));
        $rows = [];
        array_push($rows, [0, "Cancel Rollback"]);
        array_push($rows, new TableSeparator());
        $c = count($releases);
        foreach ($releases as $index => $release) {
            $date = $this->releaseToDate($release);
            if ($release === $current) {
                array_push($rows, ["<fg=yellow>$index</>", "<fg=yellow>$release : {$date->format(self::RELEASE_DATE_FORMAT)}</>"]);
            } else {
                array_push($rows, [$index, "$release : {$date->format(self::RELEASE_DATE_FORMAT)}"]);
            }
            if ($index < $c) {
                array_push($rows, new TableSeparator());
            }
        }
        $table->setRows($rows);
        $table->render();
    }

    /**
     * Convert the release directory name to a date string
     */
    protected function releaseToDate(string $release) : \DateTime
    {
        assert(valid_num_args());

        $parts = explode('-', $release);
        if (count($parts) === 6) {
            $dateString = "{$parts[1]}/{$parts[2]}/{$parts[0]} {$parts[3]}:{$parts[4]}:{$parts[5]}";
            return new \DateTime($dateString);
        }

        throw new \Exception('Invalid Release Date: ' . $release);
    }

    /**
     * Remove the current symlink and create a new one that points to the chosen release
     */
    protected function rollbackTo(string $release)
    {
        assert(valid_num_args());

        $releasePath = $this->env->getReleasesPath() . $release;

        if (!file_exists($releasePath) || !is_dir($releasePath)) {
            $this->output->error('The selected release directory (' . $release . ') does not exist');
        }

        $rootPath = $this->env->getRootPath();
        $currentPath = $rootPath . 'current';

        $this->output->writeln('Removing old symlink');
        $this->process->run("rm $currentPath");

        $this->output->writeln('Create new symlink');
        $this->process->run("ln -s $releasePath $currentPath");

        $this->env->setCurrent($release);
        $this->env->save();

        $this->output->line();
    }
}
