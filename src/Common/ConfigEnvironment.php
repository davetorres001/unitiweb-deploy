<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

class ConfigEnvironment
{
    /**
     * Check to see if directory structure exists
     */
    public function execute(DeployOutput $output, Config $config) : bool
    {
        assert(valid_num_args());

        $output->header('Environment Name');

        $name = null;
        while (null === $name) {
            $name = $output->ask("What is the environment name? [{$config->getEnvironment('Name')}] ", $config->getEnvironment('Name'));
        }

        $config->setEnvironment('Name', $name);
        $output->line('yellow');

        return true;
    }
}
