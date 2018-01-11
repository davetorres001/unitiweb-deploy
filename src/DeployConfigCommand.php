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
use Unitiweb\Deploy\Common\ConfigDirectoryStructure;
use Unitiweb\Deploy\Common\ConfigEnvironment;
use Unitiweb\Deploy\Common\ConfigGitHub;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployQuestion;

class DeployConfigCommand extends Command
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
     * @var DeployQuestion
     */
    protected $question;

    /**
     * @var array
     */
    protected $processes;

    /**
     * Configure the deploy command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            ->setName('deploy:config')
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

        // Load the configuration
        $this->config = new Config($this->output);
        $this->config->load();
        $this->processes = $this->config->getProcesses();

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

        $this->showPage();
        $this->askEnvironment();
        $this->askPaths();
        $this->askGitHub();
        $this->askShared();
        $this->askRemove();
        $this->askChown();
        $this->askChmod();
        $this->askProcesses();

        if (true === $this->askSave()) {
            $this->output->header('Configuration Saved');
            $this->output->writeln('For further configuration options see:');
            $this->output->writeln('  - Documentation');
            $this->output->writeln('  - config.yml');
            $this->output->line('yellow');
        } else {
            $this->output->header('Configuration Cancelled', 'red');
            $this->output->writeln('For further configuration options see:');
            $this->output->writeln('  - Documentation');
            $this->output->writeln('  - config.yml');
            $this->output->line('red');
        }
    }

    /**
     * Ask for environment settings
     */
    protected function askEnvironment()
    {
        assert(valid_num_args());

        $this->output->header('Environment Settings');
        $this->output->subHeader('Settings related to the deploy environment');
        $this->question->ask($this->config->getEnvironment(), [
            'Name' => [
                'description' => 'The name of the environment (ie: alpha, beta, dev, prod)',
                'type' => 'string',
                'callable' => function($key, $value) { $this->config->setEnvironment($key, $value); },
            ],
            'MaxReleases' => [
                'description' => 'Max number of releases to keep available for rollback',
                'type' => 'int',
                'default' => 4,
                'callable' => function($key, $value) { $this->config->setEnvironment($key, $value); },
            ],
            'UseSudo' => [
                'description' => 'Use sudo on chown and chmod commands',
                'type' => 'bool',
                'default' => false,
                'options' => 'true | false',
                'callable' => function($key, $value) { $this->config->setEnvironment($key, $value); }
            ],
            'ProcessTimeout' => [
                'description' => 'A timeout limit in seconds for deploy processes',
                'type' => 'int',
                'default' => 120,
                'callable' => function($key, $value) { $this->config->setEnvironment($key, $value); }
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
        $this->question->ask($this->config->getPaths(), [
            'Root' => [
                'description' => 'The root path to your account (not where your final deploy will reside). See documentation for a better description',
                'callable' => function($key, $value) { $this->config->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Repo' => [
                'description' => 'Path where the github repo will be pulled down. Defaults to {Root}/repo',
                'callable' => function($key, $value) { $this->config->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Releases' => [
                'description' => 'Path where the your releases will be located. Defaults to {Root}/releases',
                'callable' => function($key, $value) { $this->config->setpath($key, $value); },
                'validate' => $validate,
            ],
            'Shared' => [
                'description' => 'Path where the your shared files will be located. Defaults to {Root}/shared',
                'callable' => function($key, $value) { $this->config->setpath($key, $value); },
                'validate' => $validate,
            ],
        ]);
    }

    /**
     * Ask for environment settings
     */
    protected function askGitHub()
    {
        assert(valid_num_args());

        $this->showPage();

        $this->output->header('GitHub Settings');
        $this->output->subHeader('GitHub settings that will be used when pulling the repo');
        $this->question->ask($this->config->getGitHub(), [
            'Repo' => [
                'description' => 'The GitHub HTTPS or SSH url',
                'callable' => function($key, $value) { $this->config->setGitHub($key, $value); }
            ],
            'Remote' => [
                'description' => 'The GitHub remote to pull from (ie: origin)',
                'default' => 'origin',
                'callable' => function($key, $value) { $this->config->setGitHub($key, $value); }
            ],
            'Branch' => [
                'description' => 'The GitHub branch to pull (ie: master)',
                'default' => 'master',
                'callable' => function($key, $value) { $this->config->setGitHub($key, $value); }
            ],
        ]);
    }

    /**
     * Ask for environment settings
     */
    protected function askShared()
    {
        assert(valid_num_args());

        $this->question->askList('Shared Files', [
            'description' => 'Files that are shared and wont be overritten when deploying',
            'load' => function() {
                $this->showPage();
                return $this->config->getShared();
            },
            'add' => function($value) { $this->config->pushShared($value); },
            'remove' => function($value) { $this->config->popShared($value); },
        ]);
    }

    /**
     * Ask for environment settings
     */
    protected function askRemove()
    {
        assert(valid_num_args());

        $this->showPage();

        $this->question->askList('Files to Remove', [
            'description' => 'Files that will be removed after deploy',
            'load' => function() {
                $this->showPage();
                return $this->config->getRemove();
            },
            'add' => function($value) { $this->config->pushRemove($value); },
            'remove' => function($value) { $this->config->popRemove($value); },
        ]);
    }

    /**
     * Ask for chown settings
     */
    protected function askChown()
    {
        assert(valid_num_args());

        $this->showPage();

        $chown = $this->config->getChown();

        $this->output->header('Chown Pre Deploy Settings');
        $this->output->writeln('--> Group', 'yellow');
        $this->output->writeln('     The group (ie: ubuntu:ubuntu) to use for chown on files BEFORE deploy');
        $this->output->writeln('     NOTE: Files will be added next');
        $this->question->ask($chown['Pre'], [
            'Group' => [
                'nullable' => true,
                'callable' => function($key, $value) { $this->config->setChownGroup('Pre', $value); }
            ],
        ]);

        $this->showPage();

        $this->question->askList('Chown Pre Deloy Files', [
            'description' => 'Files to run chown on BEFORE deploy',
            'load' => function() {
                $this->showPage();
                return $this->config->getChown()['Pre']['Paths'];
            },
            'add' => function($value) { $this->config->pushChownPath('Pre', $value); },
            'remove' => function($value) { $this->config->popChownPath('Pre', $value); },
        ]);

        $this->showPage();

        $this->output->header('Chown Post Deploy Settings');
        $this->output->writeln('--> Group', 'yellow');
        $this->output->writeln('     The group (ie: www-data:www-data) to use for chown on files AFTER deploy');
        $this->output->writeln('     NOTE: Files will be added next');
        $this->question->ask($chown['Post'], [
            'Group' => [
                'nullable' => true,
                'callable' => function($key, $value) { $this->config->setChownGroup('Post', $value); }
            ],
        ]);

        $this->showPage();

        $this->question->askList('Chown Post Files', [
            'description' => 'Files to run chown on AFTER deploy',
            'load' => function() { return $this->config->getChown()['Post']['Paths']; },
            'add' => function($value) { $this->config->pushChownPath('Post', $value); },
            'remove' => function($value) { $this->config->popChownPath('Post', $value); },
        ]);
    }

    /**
     * Ask for chmod settings
     */
    protected function askChmod()
    {
        assert(valid_num_args());

        $this->showPage();

        $chmod = $this->config->getChmod();

        $this->output->header('Chmod Pre Deploy Settings');
        $this->output->writeln('--> Permission', 'yellow');
        $this->output->writeln('     The permission (ie: 777 or +x) to use for chmod on files BEFORE deploy');
        $this->output->writeln('     NOTE: Files will be added next');
        $this->question->ask($chmod['Pre'], [
            'Permission' => [
                'nullable' => true,
                'callable' => function($key, $value) { $this->config->setChmodPermission('Pre', $value); }
            ],
        ]);

        $this->showPage();

        $this->question->askList('Chmod Pre Deloy Files', [
            'description' => 'Files to run chmod on BEFORE deploy',
            'load' => function() { return $this->config->getChmod()['Pre']['Paths']; },
            'add' => function($value) { $this->config->pushChmodPath('Pre', $value); },
            'remove' => function($value) { $this->config->popChmodPath('Pre', $value); },
        ]);

        $this->showPage();

        $this->output->header('Chmod Post Deploy Settings');
        $this->output->writeln('--> Permission', 'yellow');
        $this->output->writeln('     The permission (ie: 777 or +x) to use for chmod on files AFTER deploy');
        $this->output->writeln('     NOTE: Files will be added next');
        $this->question->ask($chmod['Post'], [
            'Permission' => [
                'nullable' => true,
                'callable' => function($key, $value) { $this->config->setChmodPermission('Post', $value); }
            ],
        ]);

        $this->showPage();

        $this->question->askList('Chmod Post Files', [
            'description' => 'Files to run chmod on AFTER deploy',
            'load' => function() { return $this->config->getChmod()['Post']['Paths']; },
            'add' => function($value) { $this->config->pushChmodPath('Post', $value); },
            'remove' => function($value) { $this->config->popChmodPath('Post', $value); },
        ]);
    }

    /**
     * Ask for processes list
     */
    protected function askProcesses()
    {
        assert(valid_num_args());

        $displayTable = function() {

            $this->showPage();

            $this->output->header('Processes');

            $addStage = function(array &$rows, int &$number, string $stage, string $hook, array $processes) {
                $values = $processes[$stage][$hook];
                $items = [];
                foreach ($values as $process) {
                    array_push($items, $process);
                    $number++;
                }
                $rows[] = ["<fg=green>$stage:</> <fg=yellow>$hook</>", implode("\n", $items)];
            };

            $processes = $this->config->getProcesses();
            $table = new Table($this->output->getOutput());

            $number = 1;
            $rows = [];
            $addStage($rows, $number, 'Deploy', 'Pre', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Deploy', 'Post', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Rollback', 'Pre', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Rollback', 'Post', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Live', 'Pre', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Live', 'Post', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Cleanup', 'Pre', $processes);
            $rows[] = new TableSeparator();
            $addStage($rows, $number, 'Cleanup', 'Post', $processes);
            $table->setRows($rows);
            $table->setColumnWidths([15, 59]);
            $table->render();

            $this->output->writeln('');
            $this->output->writeln('   Commands:', 'green');
            $this->output->writeln('     <fg=yellow>Add Process</>:      add stage hook process-name');
            $this->output->writeln('       - <fg=yellow>stage</>:        deploy | rollback | live | cleanup');
            $this->output->writeln('       - <fg=yellow>hook</>:         pre | post');
            $this->output->writeln('       - <fg=yellow>process-name</>: the process to add');
            $this->output->writeln('       - EXAMPLE:              add deploy pre ProcessOne ProcessTwo ProcessThree');
            $this->output->writeln('');
            $this->output->writeln('     <fg=yellow>Remove Process</>: remove <fg=cyan>#</>');
            $this->output->writeln('');
            $this->output->writeln('     <fg=yellow>Done?</>: type <fg=green>done</> or just hit enter');
            $this->output->writeln('');
        };

        $error = '';
        $done = false;
        while (false === $done) {

            $displayTable();

            if ($error !== '') {
                $this->output->writeln($error, 'red');
                $error = '';
            }

            $answer = $this->output->ask("What would you like to do?", null, 'green');

            $parts = [];
            if (is_string($answer)) {
                $answer = trim($answer);
                $parts = explode(' ', $answer);
            }

            if (null === $answer || $answer === 'done') {
                $done = true;
            } elseif (substr($answer, 0, 3) === 'add') {
                if (count($parts) < 4) {
                    $error = 'Invalid Input: a valid entry would be "add deploy pre processName"';
                } else {
                    $stage = strtolower($parts[1]);
                    $hook = strtolower($parts[2]);
                    if (!in_array($stage, ['deploy', 'rollback', 'live', 'cleanup'])) {
                        $error = "Invalid stage entered: $stage. Must be either deploy, rollback, live, or cleanup";
                    } elseif (!in_array($hook, ['pre', 'post'])) {
                        $error = "Invalid hook entered: $hook. Must be either pre or post";
                    } else {
                        for ($i = 3; $i < count($parts); $i++) {
                            $this->config->pushProcess(ucfirst($stage), ucfirst($hook), $parts[$i]);
                        }
                    }
                }
            } elseif (substr($answer, 0, 6) === 'remove') {
                if (count($parts) < 4) {
                    $error = 'Invalid Input: You must include the number of the process to delete';
                } else {
                    $stage = strtolower($parts[1]);
                    $hook = strtolower($parts[2]);
                    if (!in_array($stage, ['deploy', 'rollback', 'live', 'cleanup'])) {
                        $error = "Invalid stage entered: $stage. Must be either deploy, rollback, live, or cleanup";
                    } elseif (!in_array($hook, ['pre', 'post'])) {
                        $error = "Invalid hook entered: $hook. Must be either pre or post";
                    } else {
                        for ($i = 3; $i < count($parts); $i++) {
                            $this->config->popProcess(ucfirst($stage), ucfirst($hook), $parts[$i]);
                        }
                    }
                }
            }
        }
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
                $this->config->save();
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

        $environment = $this->config->getEnvironment();
        $rows[] = [new TableCell('<info>Environment</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Name', 'MaxReleases', 'UseSudo'] as $key) {
            $value = $environment[$key];
            if ($key === 'UseSudo') {
                $value = true === $environment['UseSudo'] ? 'true' : 'false';
            }
            $rows[] = ['', "$key:", new TableCell($value, ['colspan' => 2])];
        }

        $paths = $this->config->getPaths();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Paths</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Root', 'Repo', 'Releases', 'Shared'] as $key) {
            $rows[] = ['', "$key:", new TableCell((string) $paths[$key], ['colspan' => 2])];
        }

        $github = $this->config->getGitHub();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>GitHub</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Repo', 'Remote', 'Branch'] as $key) {
            $rows[] = ['', "$key:", new TableCell((string) $github[$key], ['colspan' => 2])];
        }

        $shared = $this->config->getShared();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Shared Files</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        for ($i = 0; $i < count($shared); $i++) {
            $heading = $i === 0 ? 'Paths:' : '';
            $rows[] = ['', $heading, new TableCell((string) $shared[$i], ['colspan' => 2])];
        }

        $remove = $this->config->getRemove();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Files to Remove</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        for ($i = 0; $i < count($remove); $i++) {
            $heading = $i === 0 ? 'Paths:' : '';
            $rows[] = ['', $heading, new TableCell((string) $remove[$i], ['colspan' => 2])];
        }

        $chown = $this->config->getChown();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Chown</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        $rows[] = ['', 'Pre', '', ''];
        $rows[] = ['', '   Group:', $chown['Pre']['Group'], ''];
        $rows[] = ['', '   Paths:', '', ''];
        for ($i = 0; $i < count($chown['Pre']['Paths']); $i++) {
            $rows[] = ['', '', new TableCell('- ' . $chown['Pre']['Paths'][$i], ['colspan' => 2])];
        }
        $rows[] = ['', 'Post', '', ''];
        $rows[] = ['', '   Group:', $chown['Post']['Group'], ''];
        $rows[] = ['', '   Paths:', '', ''];
        for ($i = 0; $i < count($chown['Post']['Paths']); $i++) {
            $rows[] = ['', '', new TableCell('- ' . $chown['Post']['Paths'][$i], ['colspan' => 2])];
        }

        $chmod = $this->config->getChmod();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Chmod</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        $rows[] = ['', 'Pre', '', ''];
        $rows[] = ['', '   Group:', $chmod['Pre']['Permission'], ''];
        if (count($chmod) === 0) {
            $rows[] = ['', '   Paths:', '', ''];
        }
        for ($i = 0; $i < count($chmod['Pre']['Paths']); $i++) {
            $rows[] = ['', '', new TableCell('- ' . $chmod['Pre']['Paths'][$i], ['colspan' => 2])];
        }
        $rows[] = ['', 'Post', '', ''];
        $rows[] = ['', '   Group:', $chmod['Post']['Permission'], ''];
        $rows[] = ['', '   Paths:', '', ''];
        for ($i = 0; $i < count($chmod['Post']['Paths']); $i++) {
            $rows[] = ['', '', new TableCell('- ' . $chmod['Post']['Paths'][$i], ['colspan' => 2])];
        }

        $processes = $this->config->getProcesses();
        $rows[] = new TableSeparator();
        $rows[] = [new TableCell('<info>Processes</info>', ['colspan' => 4])];
        $rows[] = new TableSeparator();
        foreach (['Deploy', 'Rollback', 'Live', 'Cleanup'] as $label) {
            $rows[] = ['', "$label:", '', ''];
            $rows[] = ['', '   Pre:', '', ''];
            for ($i = 0; $i < count($processes[$label]['Pre']); $i++) {
                $rows[] = ['', '', new TableCell('- '. $processes[$label]['Pre'][$i], ['colspan' => 2])];
            }
            $rows[] = ['', '   Post:', '', ''];
            for ($i = 0; $i < count($processes[$label]['Post']); $i++) {
                $rows[] = ['', '', new TableCell('- '. $processes[$label]['Post'][$i], ['colspan' => 2])];
            }
        }

        $table->setRows($rows);
        $table->setStyle('compact');
        $table->render();
    }
}
