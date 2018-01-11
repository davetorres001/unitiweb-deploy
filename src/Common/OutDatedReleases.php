<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

class OutDatedReleases
{
    /**
     * @var Config
     */
    protected $config;

    public function __construct(Config $config)
    {
        assert(valid_num_args());

        $this->config = $config;
    }

    /**
     * Load out dated releases
     */
    public function find() : array
    {
        assert(valid_num_args());

        $paths = $this->config->getPaths();
        $environment = $this->config->getEnvironment();

        $remove = [];
        $data = scandir($paths['Releases']);

        if (count($data) > $environment['MaxReleases']) {
            rsort($data);
            $dirs = [];
            for ($i = 0; $i < count($data); $i++) {
                $dir = $data[$i];
                $path = $paths['Releases'] . $dir;
                if (is_dir($path) && substr($dir, 0, 1) !== '.') {
                    array_push($dirs, $path);
                    if ($i >= $environment['MaxReleases']) {
                        array_push($remove, $path);
                    }
                }
            }
        }

        return $remove;
    }
}
