<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var DeployProcess
     */
    protected $process;

    public function __construct(DeployOutput $output, DeployProcess $process = null, string $configPath = null)
    {
        assert(valid_num_args());

        $this->output = $output;
        $this->configPath = $configPath ?? dirname(dirname(__DIR__)) . '/config.yml';
        $this->process = $process ?? new DeployProcess($output, $this);
    }

    /**
     * Check for yml configuration
     */
    public function load()
    {
        assert(valid_num_args());

        // Check to see if the config file exists
        if (!file_exists($this->configPath)) {
            $this->save();
        }

        // Load the config.yml file
        try {
            $config = Yaml::parse(file_get_contents($this->configPath));
        } catch (ParseException $e) {
            $this->output->error("Unable to parse the config YAML string: {$e->getMessage()}");
        }

        // Make sure the config.yml file is a Deploy type
        if (!isset($config['Deploy'])) {
            $this->output->error("The config file appears to not be valid");
        }

        $this->config = $config['Deploy'];
    }

    /**
     * Save changes
     */
    public function save() : bool
    {
        assert(valid_num_args());

        $config = $this->prepareToSave();

        $yaml = Yaml::dump($config, 10, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        if (false === file_put_contents($this->configPath, $yaml)) {
            $this->output->error('The configuration file could not be saved');
            return false;
        }

        return true;
    }

    /**
     * Create new config.yml file from config.yml.dist
     */
    public function create()
    {
        assert(valid_num_args());

        $base = dirname($this->configPath);
        $this->process->run("cp $base/config.yml.dist $base/config.yml");
        $this->load();
        $this->save();
    }

    /**
     * Get current release path
     */
    public function getCurrentReleasePath() : string
    {
        assert(valid_num_args());

        $paths = $this->getPaths();
        $environment = $this->getEnvironment();

        return $paths['Releases'] . $environment['Current'] . '/';
    }

    /**
     * Get Environment
     */
    public function getEnvironment() : array
    {
        assert(valid_num_args());

        return [
            'Name' => $this->config['Environment']['Name'] ?? 'prod',
            'Current' => $this->config['Environment']['Current'] ?? null,
            'MaxReleases' => (int) $this->config['Environment']['MaxReleases'] ?? 5,
            'UseSudo' => (bool) $this->config['Environment']['UseSudo'] ?? false,
            'ProcessTimeout' => (int) $this->config['Environment']['ProcessTimeout'] ?? 120,
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
            'Current' => 'string',
            'MaxReleases' => 'int',
            'UseSudo' => 'bool',
            'ProcessTimeout' => 'int'
        ]);

        $this->config['Environment'][$key] = $value;
    }

    /**
     * Get Paths
     */
    public function getPaths() : array
    {
        assert(valid_num_args());

        $default = dirname(dirname(dirname(__DIR__))) . '/';

        $data = [
            'Root' => $this->config['Paths']['Root'] ?? $default,
            'Repo' => $this->config['Paths']['Repo'] ?? $default . 'repo',
            'Releases' => $this->config['Paths']['Releases'] ?? $default . 'releases',
            'Shared' => $this->config['Paths']['Shared'] ?? $default . 'shared',
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

        $this->config['Paths'][$key] = $value;
    }

    /**
     * Get shared file paths
     */
    public function getShared() : array
    {
        assert(valid_num_args());

        return isset($this->config['Shared']) && is_array($this->config['Shared']) ? $this->config['Shared'] : [];
    }

    /**
     * Add to shared array
     */
    public function pushShared(string $path)
    {
        assert(valid_num_args());

        if (!isset($this->config['Shared'])) {
            $this->config['Shared'] = [];
        }

        if (!in_array($path, $this->config['Shared'])) {
            array_push($this->config['Shared'], trim($path));
        }
    }

    /**
     * Remove from shared array
     */
    public function popShared(string $path)
    {
        assert(valid_num_args());

        $shared = $this->getShared();

        for ($i = 0; $i < count($shared); $i++) {
            if ($shared[$i] === $path) {
                array_splice($this->config['Shared'], $i, 1);
            }
        }
    }

    /**
     * Get files to remove after deploy
     */
    public function getRemove() : array
    {
        assert(valid_num_args());

        return isset($this->config['Remove']) && is_array($this->config['Remove']) ? $this->config['Remove'] : [];
    }

    /**
     * Add to remove array
     */
    public function pushRemove(string $path)
    {
        assert(valid_num_args());

        if (!isset($this->config['Remove'])) {
            $this->config['Remove'] = [];
        }

        if (!in_array($path, $this->config['Remove'])) {
            array_push($this->config['Remove'], trim($path));
        }
    }

    /**
     * Remove from the remove array
     */
    public function popRemove(string $path)
    {
        assert(valid_num_args());

        $remove = $this->getRemove();

        for ($i = 0; $i < count($remove); $i++) {
            if ($remove[$i] === $path) {
                array_splice($this->config['Remove'], $i, 1);
            }
        }
    }

    /**
     * Get github
     */
    public function getGitHub() : array
    {
        assert(valid_num_args());

        return [
            'Repo' => $this->config['GitHub']['Repo'] ?? null,
            'Remote' => $this->config['GitHub']['Remote'] ?? 'origin',
            'Branch' => $this->config['GitHub']['Branch'] ?? 'master',
        ];
    }

    /**
     * Set github
     */
    public function setGitHub(string $key, string $value)
    {
        assert(valid_num_args());
        assert(in_array($key, ['Repo', 'Remote', 'Branch']));

        $this->config['GitHub'][$key] = $value;
    }

    /**
     * Get chown
     */
    public function getChown() : array
    {
        assert(valid_num_args());

        return [
            'Pre' => [
                'Group' => $this->config['Chown']['Pre']['Group'] ?? null,
                'Paths' => $this->config['Chown']['Pre']['Paths'] ?? [],
            ],
            'Post' => [
                'Group' => $this->config['Chown']['Post']['Group'] ?? null,
                'Paths' => $this->config['Chown']['Post']['Paths'] ?? [],
            ],
        ];
    }

    /**
     * Set Chown Group
     */
    public function setChownGroup(string $prePost, ?string $group)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));

        $this->config['Chown'][$prePost]['Group'] = $group;
    }

    /**
     * Add path to chown
     */
    public function pushChownPath(string $prePost, string $path)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));

        if (!isset($this->config['Chown'][$prePost]['Paths'])) {
            $this->config['Chown'][$prePost]['Paths'] = [];
        }

        array_push($this->config['Chown'][$prePost]['Paths'], trim($path));
    }

    /**
     * Remove path from chown
     */
    public function popChownPath(string $prePost, ?string $path)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));

        $chown = $this->getChown();

        for ($i = 0; $i < count($chown[$prePost]['Paths']); $i++) {
            if ($chown[$prePost]['Paths'][$i] === $path) {
                array_splice($this->config['Chown'][$prePost]['Paths'], $i, 1);
            }
        }
    }

    /**
     * Get chmod
     */
    public function getChmod() : array
    {
        assert(valid_num_args());

        return [
            'Pre' => [
                'Permission' => $this->config['Chmod']['Pre']['Permission'] ?? null,
                'Paths' => $this->config['Chmod']['Pre']['Paths'] ?? [],
            ],
            'Post' => [
                'Permission' => $this->config['Chmod']['Post']['Permission'] ?? null,
                'Paths' => $this->config['Chmod']['Post']['Paths'] ?? [],
            ],
        ];
    }

    /**
     * Set Chmod Group
     */
    public function setChmodPermission(string $prePost, $permission)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));
        assert(is_string($permission) || is_int($permission || is_null($permission)));

        $this->config['Chmod'][$prePost]['Permission'] = $permission;
    }

    /**
     * Add path to chmod
     */
    public function pushChmodPath(string $prePost, string $path)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));

        if (!isset($this->config['Chmod'][$prePost]['Paths'])) {
            $this->config['Chmod'][$prePost]['Paths'] = [];
        }

        $this->config['Chmod'][$prePost]['Paths'] = $this->config['Chmod'][$prePost]['Paths'] ?? [];
        array_push($this->config['Chmod'][$prePost]['Paths'], trim($path));
    }

    /**
     * Remove path from chown
     */
    public function popChmodPath(string $prePost, string $path)
    {
        assert(valid_num_args());
        assert(in_array($prePost, ['Pre', 'Post']));

        $chown = $this->getChmod();

        for ($i = 0; $i < count($chown[$prePost]['Paths']); $i++) {
            if ($chown[$prePost]['Paths'][$i] === $path) {
                array_splice($this->config['Chmod'][$prePost]['Paths'], $i, 1);
            }
        }
    }

    /**
     * Get processes
     */
    public function getProcesses(bool $array = true) : array
    {
        assert(valid_num_args());

        $default = true === $array ? [] : null;

        return [
            'Deploy' => [
                'Pre' => $this->config['Processes']['Deploy']['Pre'] ?? $default,
                'Post' => $this->config['Processes']['Deploy']['Post'] ?? $default,
            ],
            'Rollback' => [
                'Pre' => $this->config['Processes']['Rollback']['Pre'] ?? $default,
                'Post' => $this->config['Processes']['Rollback']['Post'] ?? $default,
            ],
            'Live' => [
                'Pre' => $this->config['Processes']['Live']['Pre'] ?? $default,
                'Post' => $this->config['Processes']['Live']['Post'] ?? $default,
            ],
            'Cleanup' => [
                'Pre' => $this->config['Processes']['Cleanup']['Pre'] ?? $default,
                'Post' => $this->config['Processes']['Cleanup']['Post'] ?? $default,
            ],
        ];
    }

    /**
     * Add path to chmod
     */
    public function pushProcess(string $stage, string $prePost, string $class)
    {
        assert(valid_num_args());
        assert(in_array($stage, ['Deploy', 'Rollback', 'Live', 'Cleanup']));
        assert(in_array($prePost, ['Pre', 'Post']));

        if (!isset($this->config['Processes'][$stage][$prePost])) {
            $this->config['Processes'][$stage][$prePost] = [];
        }

        array_push($this->config['Processes'][$stage][$prePost], trim($class));
    }

    /**
     * Remove path from chown
     */
    public function popProcess(string $stage, string $prePost, string $class)
    {
        assert(valid_num_args());
        assert(in_array($stage, ['Deploy', 'Rollback', 'Live', 'Cleanup']));
        assert(in_array($prePost, ['Pre', 'Post']));

        $processes = $this->getProcesses();

        for ($i = 0; $i < count($processes[$stage][$prePost]); $i++) {
            if ($processes[$stage][$prePost][$i] === $class) {
                array_splice($this->config['Processes'][$stage][$prePost], $i, 1);
            }
        }
    }

    /**
     * Assert that the value is the correct type
     */
    protected function assertType(string $key, $value, array $config)
    {
        assert(valid_num_args());
        assert(array_key_exists($key, $config));

        $type = $config[$key];

        if ($type === 'string') {
            assert(is_string($value));
        } elseif ($type === 'int') {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            assert(is_int($value));
        } elseif ($type === 'bool') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            assert(is_bool($value));
        }
    }

    /**
     * Prepare config array for saving
     */
    protected function prepareToSave() : array
    {
        assert(valid_num_args());

        $config = [
            'Environment' => $this->getEnvironment(),
            'Paths' => $this->getPaths(),
            'Shared' => $this->getShared(),
            'Remove' => $this->getRemove(),
            'GitHub' => $this->getGitHub(),
            'Chown' => $this->getChown(),
            'Chmod' => $this->getChmod(),
            'Processes' => $this->getProcesses(),
        ];

//        $config = $this->prepareArraytoSave($config);

        return ['Deploy' => $config];
    }

    /**
     * Prepare an array for saving
     */
    protected function prepareArraytoSave(array $array) : array
    {
        assert(valid_num_args());

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (count($value) === 0) {
                    $array[$key] = null;
                } else {
                    $array[$key] = $this->prepareArraytoSave($array[$key]);
                }
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
