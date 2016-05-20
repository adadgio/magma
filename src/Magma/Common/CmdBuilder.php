<?php

namespace Magma\Common;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CmdBuilder
{
    /**
     * Builds the rsync command from config arguments.
     *
     * @param [object] \Config
     * @return [string] Rsync command to be executed
     */
    public static function rsync(Config $config, $env)
    {
        $cwd = rtrim($config->getCwd(), '/').'/'; // "/" to sync all files inside the top dir

        $excl = null;
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));

        $exclude = $config->getParameter('project.exclude');
        
        if (!empty($exclude)) {
            // avoid "/" root path left trailing... with ltrim
            $excl = implode(' ', array_map(function ($e) { return '--exclude '.ltrim($e, '/'); }, $exclude));
        }

        $cmd = str_replace('  ', ' ',
            vsprintf("rsync -avzP --delete --delete-excluded -v %s %s %s@%s:%s", array($excl, $cwd, $user, $host, $path))
        );

        return $cmd;
    }
}
