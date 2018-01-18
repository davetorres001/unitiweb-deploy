<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Symfony\Component\Process\Process;
use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;
use Unitiweb\Deploy\Common\DeployProcess;
use Unitiweb\Deploy\Common\Env;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class GitHubPullProcess implements ProcessInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Env
     */
    protected $env;

    /**
     * @var DeployOutput
     */
    protected $output;

    /**
     * @var DeployProcess
     */
    protected $process;

    public function __construct(Config $config, Env $env, DeployOutput $output, DeployProcess $process = null)
    {
        assert(valid_num_args());

        $this->config = $config;
        $this->env = $env;
        $this->output = $output;
        $this->process = $process ?? new DeployProcess($output, $env);
    }

    /**
     * Execute the process
     */
    public function execute()
    {
        $repoPath = $this->env->getRepoPath();
        $github = $this->config->getGitHub();

        $this->output->header('GitHub: Setup');

        if (!is_dir($repoPath . '.git')) {
            $this->process->run('git init', $repoPath);
        }

        if (false === $this->hasRemote($repoPath)) {
            $this->process->run("git remote add origin {$github['Repo']}", $repoPath);
        }

        // Fetch all releases
        $this->process->run("git fetch --all", $repoPath);
        $this->process->run("git checkout master", $repoPath);
        $this->output->line();

        if (null !== ($release = $this->askForTag($repoPath))) {
            $this->process->run("git checkout $release", $repoPath);
        } elseif (null !== ($branch = $this->askForBranch($repoPath))) {
            $parts = explode('/', $branch);
            $remote = $parts[0] ?? null;
            $branch = $parts[1] ?? null;
            if (null === $remote || null === $branch) {
                $this->output->error('Invalid GitHub remote or branch');
            }
            $this->process->run("git pull $remote $branch", $repoPath);
        }

        $this->output->line('yellow');
    }

    /**
     * List Repo remotes
     */
    protected function hasRemote(string $path) : bool
    {
        assert(valid_num_args());

        $remotes = [];

        $process = new Process("cd $path && git remote -v");
        $process->run();
        $lines = explode("\n", $process->getOutput());

        $i = 1;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                if (substr($line, 0, 2) === '* ') {
                    $line = substr($line, 2);
                }
                $remotes[$i] = $line;
                $i++;
            }
        }

        return count($remotes) > 0 ? true : false;
    }

    /**
     * Ask for tag
     */
    protected function askForTag(string $path) : ?string
    {
        assert(valid_num_args());

        $this->output->header('GitHub: Choose Release');
        $tags = $this->listTags($path);
        $this->generateTagsTable($tags);

        $release = null;
        while (true) {
            $answer = $this->output->ask('Enter the index of the tag:');
            $selectedTag = (int) $answer;
            if ($answer === '' || $answer === null || $selectedTag === 0) {
                break;
            }
            if ($selectedTag > 0) {
                if (isset($tags[$selectedTag])) {
                    $release = $tags[$selectedTag];
                    break;
                }
            }
        }

        return $release;
    }

    /**
     * List Repo tags
     */
    protected function listTags(string $path, int $limit = 10) : array
    {
        assert(valid_num_args());

        $tags = [];

        $process = new Process("cd $path && git tag -l {$this->env->getGitTagFilter()} --sort=-v:refname");
        $process->run();
        $lines = explode("\n", $process->getOutput());

        $i = 1;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $tags[$i] = $line;
                if ($i === $limit) {
                    break;
                }
                $i++;
            }
        }

        return $tags;
    }

    /**
     * Generate tags table
     */
    protected function generateTagsTable(array $tags)
    {
        assert(valid_num_args());

        $table = new Table($this->output->getOutput());
        $table->setHeaders(array('Index', 'Tag'));
        $rows = [];
        array_push($rows, [0, 'none (choose a branch)']);
        array_push($rows, new TableSeparator());
        $i = 1;
        $c = count($tags);
        foreach ($tags as $index => $tag) {
            array_push($rows, [$index, $tag]);
            if ($i < $c) {
                array_push($rows, new TableSeparator());
            }
            $i++;
        }
        $table->setRows($rows);
        $table->render();
    }

    /**
     * Ask for branches
     */
    protected function askForBranch(string $path) : ?string
    {
        assert(valid_num_args());

        $this->output->header('GitHub: Choose Branch');
        $branches = $this->listBranches($path);
        $this->generateBranchesTable($branches);

        $release = null;
        while (true) {
            $answer = $this->output->ask('Enter the index of the branch:');
            $selectedBranch = (int) $answer;
            if ($selectedBranch > 0) {
                if (isset($branches[$selectedBranch])) {
                    $release = $branches[$selectedBranch];
                    break;
                }
            }
        }

        return $release;
    }

    /**
     * List Repo branches
     */
    protected function listBranches(string $path) : array
    {
        assert(valid_num_args());

        $branches = [];

        $process = new Process("cd $path && git branch -r --sort=-v:refname");
        $process->run();
        $lines = explode("\n", $process->getOutput());

        $i = 1;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                if (substr($line, 0, 2) === '* ') {
                    $line = substr($line, 2);
                }
                $branches[$i] = $line;
                $i++;
            }
        }

        return $branches;
    }

    /**
     * Generate branches table
     */
    protected function generateBranchesTable(array $branches)
    {
        assert(valid_num_args());

        $table = new Table($this->output->getOutput());
        $table->setHeaders(array('Index', 'Remote', 'Branch'));
        $rows = [];
        $i = 1;
        $c = count($branches);
        foreach ($branches as $index => $branch) {
            $parts = explode('/', $branch);
            if (count($parts) === 2) {
                array_push($rows, [$index, $parts[0], $parts[1]]);
                if ($i < $c) {
                    array_push($rows, new TableSeparator());
                }
                $i++;
            }
        }
        $table->setRows($rows);
        $table->render();
    }
}
