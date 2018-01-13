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

        return $this->getReleasesPath() . $this->getCurrent() . '/';
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
     * Get Environment
     */
    public function getEnvironment() : array
    {
        assert(valid_num_args());

        $environment = [];

        $environment['Name'] = 'prod';
        if (isset($this->env['Environment']['Name']) && $this->env['Environment']['Name'] !== '') {
            $environment['Name'] = $this->env['Environment']['Name'];
        }

        $environment['MaxReleases'] = 5;
        if (isset($this->env['Environment']['MaxReleases']) && (int) $this->env['Environment']['MaxReleases'] > 0) {
            $environment['MaxReleases'] = (int) $this->env['Environment']['MaxReleases'];
        }

        $environment['UseSudo'] = false;
        if (isset($this->env['Environment']['UseSudo'])) {
            $sudo = $this->env['Environment']['UseSudo'];
            if ($sudo === 'true' || $sudo === true || $sudo === 1) {
                $environment['UseSudo'] = true;
            }
        }

        $environment['ProcessTimeout'] = 120;
        if (isset($this->env['Environment']['ProcessTimeout']) && (int) $this->env['Environment']['ProcessTimeout'] > 0) {
            $environment['ProcessTimeout'] = (int) $this->env['Environment']['ProcessTimeout'];
        }

        return $environment;
    }

    /**
     * Get name
     */
    public function getName() : string
    {
        assert(valid_num_args());

        $environment = $this->getEnvironment();

        return $environment['Name'];
    }

    /**
     * Get max releases
     */
    public function getMaxReleases() : int
    {
        assert(valid_num_args());

        $environment = $this->getEnvironment();

        return $environment['MaxReleases'];
    }

    /**
     * Get sudo
     */
    public function getUseSudo() : bool
    {
        assert(valid_num_args());

        $environment = $this->getEnvironment();

        return $environment['UseSudo'];
    }

    /**
     * Get process timeout
     */
    public function getProcessTimeout() : int
    {
        assert(valid_num_args());

        $environment = $this->getEnvironment();

        return $environment['ProcessTimeout'];
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
     * Get root path
     */
    public function getRootPath() : string
    {
        assert(valid_num_args());

        $environment = $this->getPaths();

        return $environment['Root'];
    }

    /**
     * Get repo path
     */
    public function getRepoPath() : string
    {
        assert(valid_num_args());

        $environment = $this->getPaths();

        return $environment['Repo'];
    }

    /**
     * Get releases path
     */
    public function getReleasesPath() : string
    {
        assert(valid_num_args());

        $environment = $this->getPaths();

        return $environment['Releases'];
    }

    /**
     * Get shared path
     */
    public function getSharedPath() : string
    {
        assert(valid_num_args());

        $environment = $this->getPaths();

        return $environment['Shared'];
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
