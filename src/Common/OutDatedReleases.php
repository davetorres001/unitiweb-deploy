<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

class OutDatedReleases
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Env
     */
    protected $env;

    public function __construct(Config $config, Env $env)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->env = $env;
    }

    /**
     * Load out dated releases
     */
    public function find() : array
    {
        assert(valid_num_args());

        $remove = [];
        $data = scandir($this->env->getReleasesPath());

        if (count($data) > $this->env->getMaxReleases()) {
            rsort($data);
            $dirs = [];
            for ($i = 0; $i < count($data); $i++) {
                $dir = $data[$i];
                $path = $this->env->getReleasesPath() . $dir;
                if (is_dir($path) && substr($dir, 0, 1) !== '.') {
                    array_push($dirs, $path);
                    if ($i >= $this->env->getMaxReleases()) {
                        array_push($remove, $path);
                    }
                }
            }
        }

        return $remove;
    }
}
