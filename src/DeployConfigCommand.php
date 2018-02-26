<?php
declare(strict_types=1);

namespace Unitiweb\Deploy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\delete;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployQuestion;
use Unitiweb\Deploy\Common\Env;

class DeployConfigCommand extends Command
{
    use LockableTrait;

    /**
     * @var string
     */
    protected $configDir;

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
     * @var DeployQuestion
     */
    protected $question;

    /**
     * @var array
     */
    protected $processes;

    public function __construct(string $configDir = null)
    {
        parent::__construct(null);

        $this->configDir = $configDir;
    }

    /**
     * Configure the deploy command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            ->setName('config')
            ->setDescription('Configure for Deploy')
            ->setHelp('This command will help you configure the deploy process');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        $this->output = new DeployOutput($output, $input);
        $this->question = new DeployQuestion($this->output);

        // Make sure the deployment is not current running
        if (!$this->lock()) {
            $this->output->error('The command is already running in another process.');
        }

        // Load the environment configuration
        $this->env = new Env($this->output, null, $this->configDir);
        $this->env->load();

        // Load the configuration
        $this->config = new Config($this->output, $this->configDir);
        $this->config->load();

        $this->beginQuestions();

        $this->output->blank();
    }

    /**
     * Show page setup
     */
    protected function showPage()
    {
        assert(valid_num_args());

        for ($i = 1; $i <= 75; $i++) {
            $this->output->blank();
        }

        $this->output->header('Instructions');
        $this->output->writeln('Answer each question:');
        $this->output->writeln('  - Options will be in parenthesis ()');
        $this->output->writeln('  - Current or Default values will be in brancets []');
        $this->output->writeln('  - For an explaination on a setting just type ?');
        $this->output->writeln('  - For more detailed explainations see documentation"');
        $this->output->line('yellow');

        $this->configTable('Current Settings');
    }

    /**
     * Start asking the questions
     */
    protected function beginQuestions()
    {
        assert(valid_num_args());

        $this->askEnvironment();
        $this->askPaths();
        $this->askGit();

        if (true === $this->askSave()) {
            $this->output->header('Configuration Saved');
            $this->output->writeln('For further configuration options see:');
            $this->output->writeln('  - Documentation');
            $this->output->writeln('  - env.yml');
            $this->output->line('yellow');
        } else {
            $this->output->header('Configuration Cancelled', 'red');
            $this->output->writeln('For further configuration options see:');
            $this->output->writeln('  - Documentation');
            $this->output->writeln('  - env.yml');
            $this->output->line('red');
        }
    }

    /**
     * Ask for environment settings
     */
    protected function askEnvironment()
    {
        assert(valid_num_args());

        $this->showPage();

        $this->output->header('Environment Settings');
        $this->output->subHeader('Settings related to the deploy environment');
        $this->question->ask($this->env->getEnvironment(), [
            'Name' => [
                'description' => 'The name of the environment (ie: alpha, beta, dev, prod)',
                'type' => 'string',
                'callable' => function($key, $value) { $this->env->setEnvironment($key, $value); },
            ],
            'MaxReleases' => [
                'description' => 'Max number of releases to keep available for rollback',
                'type' => 'int',
                'default' => 4,
                'callable' => function($key, $value) { $this->env->setEnvironment($key, $value); },
            ],
            'UseSudo' => [
                'description' => 'Use sudo on chown and chmod commands',
                'type' => 'bool',
                'default' => false,
                'options' => 'true | false',
                'callable' => function($key, $value) { $this->env->setEnvironment($key, $value); }
            ],
            'ProcessTimeout' => [
                'description' => 'A timeout limit in seconds for deploy processes',
                'type' => 'int',
                'default' => 120,
                'callable' => function($key, $value) { $this->env->setEnvironment($key, $value); }
            ],
        ]);
    }

    /**
     * Ask for environment settings
     */
    protected function askPaths()
    {
        assert(valid_num_args());

        $this->showPage();

        $validate = function($key, $value) {
            if (!is_dir($value)) {
                $done = false;
                while (false === $done) {
                    $this->output->writeln('The directory does not exist', 'red');
                    $answer = $this->output->ask('Do you want to create it (yes | no)?', null, 'yellow');
                    if ($answer === 'yes') {
                        if (!mkdir($value)) {
                            $this->output->writeln('Directory could not be created');
                        }
                        $done = true;
                    } elseif ($answer === 'no') {
                        $done = true;
                    } else {
                        $this->output->writeln('You must answer either yes or no');
                    }
                }
            }

            return null;
        };

        $this->output->header('Directory Structure Settings');
        $this->output->subHeader('Paths to important directoryes: root, repo, releases, and shared');
        $this->question->ask($this->env->getPaths(), [
            'Root' => [
                'description' => 'The root path to your account (not where your final deploy will reside). See documentation for a better description',
                'callable' => function($key, $value) { $this->env->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Repo' => [
                'description' => 'Path where the github repo will be pulled down. Defaults to {Root}/repo',
                'callable' => function($key, $value) { $this->env->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Releases' => [
                'description' => 'Path where the your releases will be located. Defaults to {Root}/releases',
                'callable' => function($key, $value) { $this->env->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Shared' => [
                'description' => 'Path where the your shared files will be located. Defaults to {Root}/shared',
                'callable' => function($key, $value) { $this->env->setpath($key, $value); },
                'validate' => $validate,
            ],
        ]);
    }

    /**
     * Ask for Git settings
     */
    protected function askGit()
    {
        assert(valid_num_args());

        $this->showPage();

        $this->output->header('Git Settings');
        $this->output->subHeader('Settings related to the git tags and branches');
        $this->output->subHeader("If you don't want to use a filter just set it to *");
        $this->question->ask([
            'Tag Filter' => $this->env->getGitTagFilter(),
        ], [
            'Tag Filter' => [
                'description' => 'The filter used when getting the list of tags (example: *alpha*, *beta*, 1.0.0-alpha*',
                'type' => 'string',
                'nullable' => true,
                'callable' => function($key, $value) { $this->env->setGitTagFilter($value); },
            ],
        ]);
    }

    /**
     * Ask to save
     */
    protected function askSave() : bool
    {
        assert(valid_num_args());

        $this->showPage();

        $this->output->header('Save Confirmation');
        $this->output->writeln('If you choose not to save all new settings will be lost');
        $this->output->writeln('');

        $pass = false;
        while (false === $pass) {
            $answer = $this->output->ask('Are you ready to save your changes? (<fg=green>save</> | <fg=red>cancel</> | <fg=yellow>restart</>)', null, 'green');

            if ($answer === 'cancel') {
                $answer = $this->output->ask('Are you sure you want to cancel? Changes will not be saved. (<fg=green>save</> | <fg=red>cancel</> | <fg=yellow>restart</>)', null, 'red');
                if ($answer === 'save') {
                    $pass = true;
                }
            }

            if ($answer === 'restart') {
                $this->beginQuestions();
                $pass = true;
            } elseif ($answer === 'save') {
                $this->env->save();
                $pass = true;
            }
        }

        return $answer === 'save' ? true : false;
    }

    /**
     * Build config tabler
     */
    protected function configTable(string $title)
    {
        assert(valid_num_args());

        $this->output->header($title);
        $table = new Table($this->output->getOutput());
        $rows = [];

        $current = $this->env->getCurrent();
        $rows[] = [new TableCell('<info>Current</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        $rows[] = ['', "Current:", new TableCell($this->env->getCurrent(), ['colspan' => 2])];

        $environment = $this->env->getEnvironment();
        $rows[] = [new TableCell('<info>Environment</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Name', 'MaxReleases', 'UseSudo'] as $key) {
            $value = $environment[$key];
            if ($key === 'UseSudo') {
                $value = true === $environment['UseSudo'] ? 'true' : 'false';
            }
            $rows[] = ['', "$key:", new TableCell($value, ['colspan' => 2])];
        }

        $paths = $this->env->getPaths();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Paths</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Root', 'Repo', 'Releases', 'Shared'] as $key) {
            $rows[] = ['', "$key:", new TableCell((string) $paths[$key], ['colspan' => 2])];
        }

        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Git</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        $rows[] = ['', 'Tag Filter: ', new TableCell((string) $this->env->getGitTagFilter(), ['colspan' => 2])];
//        $rows[] = ['', 'Branch Filter: ', new TableCell((string) $this->env->getGitBranchFilter(), ['colspan' => 2])];

        $table->setRows($rows);
        $table->setStyle('compact');
        $table->render();
    }
}
