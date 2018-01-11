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

        $remote = null;
        while (null === $remote) {
            $remote = $output->ask("GitHub Remote? [{$config->getGitHub('Remote')}] ", $config->getGitHub('Remote'));
        }
        $config->setGitHub('Remote', $remote);

        $branch = null;
        while (null === $branch) {
            $branch = $output->ask("GitHub Branch? [{$config->getGitHub('Branch')}] ", $config->getGitHub('Branch'));
        }
        $config->setGitHub('Branch', $branch);

        $output->line('yellow');

        return true;
    }
}
