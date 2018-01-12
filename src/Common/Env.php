<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Env
{
    use ConfigTrait;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var string
     */
    protected $configFile;

    /**
     * @var array
     */
    protected $env;

    /**
     * @var DeployProcess
     */
    protected $process;

    public function __construct(DeployOutput $output, DeployProcess $process = null, string $configDir = null)
    {
        assert(valid_num_args());

        $this->output = $output;
        $this->configFile = $configDir . '/env.yml';
        $this->process = $process ?? new DeployProcess($output, $this);
    }

    /**
     * Check for yml configuration
     */
    public function load()
    {
        assert(valid_num_args());

        // Check to see if the config file exists
        if (!file_exists($this->configFile)) {
            $this->save();
        }

        // Load the config.yml file
        try {
            $config = Yaml::parse(file_get_contents($this->configFile));
        } catch (ParseException $e) {
            $this->output->error("Unable to parse the env YAML string: {$e->getMessage()}");
        }

        // Make sure the config.yml file is a Deploy type
        if (!isset($config['Deploy'])) {
            $this->output->error("The env file appears to not be valid");
        }

        $this->env = $config['Deploy'];
    }

    /**
     * Save changes
     */
    public function save() : bool
    {
        assert(valid_num_args());

        $config = $this->prepareToSave();

        $yaml = Yaml::dump($config, 10, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        if (false === file_put_contents($this->configFile, $yaml)) {
            $this->output->error('The configuration file could not be saved');
            return false;
        }

        return true;
    }

    /**
     * Get current release path
     */
    public function getCurrentReleasePath() : string
    {
        assert(valid_num_args());

        $paths = $this->getPaths();

        return $paths['Releases'] . $this->getCurrent() . '/';
    }

    /**
     * Get current
     */
    public function getCurrent() : string
    {
        assert(valid_num_args());

        return $this->env['Current'] ?? '';
    }

    /**
     * Set current
     */
    public function setCurrent($value)
    {
        assert(valid_num_args());

        $this->assertType('Current', $value, [
            'Current' => 'string',
        ]);

        $this->env['Current'] = $value;
    }

    /**
     * Get Environment
     */
    public function getEnvironment() : array
    {
        assert(valid_num_args());

        return [
            'Name' => $this->env['Environment']['Name'] ?? 'prod',
            'MaxReleases' => $this->env['Environment']['MaxReleases'] ?? 5,
            'UseSudo' => $this->env['Environment']['UseSudo'] === true ? true : false,
            'ProcessTimeout' => $this->env['Environment']['ProcessTimeout'] ?? 120,
        ];
    }

    /**
     * Set environment
     */
    public function setEnvironment(string $key, $value)
    {
        assert(valid_num_args());

        $this->assertType($key, $value, [
            'Name' => 'string',
            'MaxReleases' => 'int',
            'UseSudo' => 'bool',
            'ProcessTimeout' => 'int'
        ]);

        $this->env['Environment'][$key] = $value;
    }

    /**
     * Get Paths
     */
    public function getPaths() : array
    {
        assert(valid_num_args());

        $default = dirname(dirname(dirname($this->configFile))) . '/';

        $data = [
            'Root' => $this->env['Paths']['Root'] ?? $default,
            'Repo' => $this->env['Paths']['Repo'] ?? $default . 'repo',
            'Releases' => $this->env['Paths']['Releases'] ?? $default . 'releases',
            'Shared' => $this->env['Paths']['Shared'] ?? $default . 'shared',
        ];

        foreach ($data as $key => $value) {
            if (null !== $data[$key]) {
                if (substr($data[$key], -1) !== '/') {
                    $data[$key] .= '/';
                }
            }
        }

        return $data;
    }

    /**
     * Set path
     */
    public function setPath(string $key, string $value)
    {
        assert(valid_num_args());
        assert(in_array($key, ['Root', 'Repo', 'Releases', 'Shared']));

        if (substr($value, -1) !== '/') {
            $value .= '/';
        }

        $this->env['Paths'][$key] = $value;
    }

    /**
     * Prepare config array for saving
     */
    protected function prepareToSave() : array
    {
        assert(valid_num_args());

        $config = [
            'Current' => $this->getCurrent(),
            'Environment' => $this->getEnvironment(),
            'Paths' => $this->getPaths(),
        ];

        return ['Deploy' => $config];
    }
}
