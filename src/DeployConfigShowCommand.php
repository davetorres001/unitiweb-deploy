<?php
declare(strict_types=1);

namespace Unitiweb\Deploy;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;

class DeployConfigShowCommand extends Command
{
    use LockableTrait;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $data = [
        'Environment:Name' => [
            'type' => 'string',
            'getter' => 'getEnvironmentName',
            'setter' => 'setEnvironmentName',
        ],
        'MaxReleases' => [
            'type' => 'int',
            'getter' => 'getMaxReleases',
            'setter' => 'setMaxReleases',
        ],
        'UseSudo' => [
            'type' => 'bool',
            'getter' => 'getUseSudo',
            'setter' => 'setUseSudo',
        ],
        'GitHub:Repo' => [
            'type' => 'string',
            'getter' => 'getGitHubRepo',
            'setter' => 'setGitHubRepo',
        ],
        'GitHub:Remote' => [
            'type' => 'string',
            'getter' => 'getGitHubRemote',
            'setter' => 'setGitHubRemote',
        ],
        'GitHub:Branch' => [
            'type' => 'string',
            'getter' => 'getGitHubBranch',
            'setter' => 'setGitHubBranch',
        ],
        'Paths:Root' => [
            'type' => 'string',
            'getter' => 'getPathRoot',
            'setter' => 'setPathRoot',
        ],
        'Paths:Repo' => [
            'type' => 'string',
            'getter' => 'getPathRepo',
            'setter' => 'setPathRepo',
        ],
        'Paths:Releases' => [
            'type' => 'string',
            'getter' => 'getPathReleases',
            'setter' => 'setPathReleases',
        ],
        'Paths:Shared' => [
            'type' => 'string',
            'getter' => 'getPathShared',
            'setter' => 'setPathShared',
        ],
    ];

    /**
     * Configure the deploy command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            ->setName('deploy:config:show')
            ->setDescription('Show/Modify Configuration')
            ->setHelp('This command will show current configuration and let you modify');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        // Make sure the deployment is not current running
        if (!$this->lock()) {
            $output->error('The command is already running in another process.');
        }

        $this->output = new DeployOutput($output, $input);

        // Load the configuration
        $this->config = new Config;
        $this->config->load($this->output);

        $this->ask();

        $this->output->line('yellow');
        $this->output->blank();
    }

    /**
     * Render table
     */
    protected function renderTable()
    {
        assert(valid_num_args());

        $this->output->writeln('');

        $table = new Table($this->output->getOutput());
        $table->setHeaders(array('Setting', 'Value'));

        foreach ($this->data as $key => $value) {
            $field = $this->data[$key];
            $value = $this->config->{$value['getter']}();
            $value = $this->toString($value, $field['type']);
            $table->addRow([$key, $value]);
        }
        $table->render();
    }

    /**
     * Ask to edit
     */
    protected function ask()
    {
        assert(valid_num_args());

        $quit = false;
        $error = null;
        while (false === $quit) {

            $this->output->header('Current Configuration');
            $this->renderTable();
            $this->output->writeln('Commands: [setting name] | save | show | cancel', 'yellow');
            $this->output->writeln('');

            if (is_string($error)) {
                $this->output->writeln($error, 'red');
                $error = null;
            }

            if (null !== ($answer = $this->output->ask('Choose Setting to Edit: '))) {
                if (strtolower($answer) === 'save') {
                    $this->config->save();
                    $quit = true;
                } elseif (strtolower($answer) === 'cancel') {
                    $quit = true;
                } elseif (strtolower($answer) === 'show') {
                    $this->renderTable();
                } elseif (null === ($this->data[$answer] ?? null)) {
                    $error = 'The field ' . $answer . ' does not exist';
                } else {
                    if (null !== ($field = $this->data[$answer] ?? null)) {
                        $this->edit($answer, $field);
                    }
                }
            }
        }
    }

    /**
     * Edit setting
     */
    public function edit(string $key, array $field) : bool
    {
        assert(valid_num_args());

        $quit = false;
        while (false === $quit) {
            $value = $this->config->{$field['getter']}();
            $valueString = $this->toString($value, $field['type']);
            if ($answer = $this->output->ask("$key [$valueString]: ", $value, 'yellow')) {
                $answer = $this->castValue($answer, $field['type']);
                $this->config->{$field['setter']}($answer);
                $quit = true;
            }
        }

        return true;
    }

    /**
     * Return value in the proper type
     */
    protected function castValue($value, string $type)
    {
        assert(valid_num_args());

        if ($type === 'int') {
            return (int) $value;
        } elseif ($type === 'bool') {
            if ($value === 'true' || $value === '1' || $value === true) {
                return true;
            } else {
                return false;
            }
        } else {
            return $value;
        }
    }

    /**
     * Value to string
     */
    protected function toString($value, string $type) : string
    {
        assert(valid_num_args());

        if (null === $value) {
            return 'null';
        } elseif (is_bool($value)) {
            return true === $value ? 'true' : 'false';
        } else {
            return "$value";
        }
    }

}
