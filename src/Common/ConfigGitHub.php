<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common;

class ConfigGitHub
{
    /**
     * Check to see if directory structure exists
     */
    public function execute(DeployOutput $output, Config $config) : bool
    {
        assert(valid_num_args());

        $output->header('GitHub Configuration');

        $repo = null;
        while (null === $repo) {
            $repo = $output->ask("GitHub Repo HTTPS/SSH? [{$config->getGitHub('Repo')}] ", $config->getGitHub('Repo'));
        }
        $config->setGitHub('Repo', $repo);

        $output->line('yellow');

        return true;
    }
}
