<?php
declare(strict_types=1);

namespace Unitiweb\Deploy\Common\Process;

use Unitiweb\Deploy\Common\Config;
use Unitiweb\Deploy\Common\DeployOutput;

interface ProcessInterface
{
    /**
     * Execute the process
     */
    public function execute();
}
