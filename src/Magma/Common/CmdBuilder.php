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
    public static function rsync(Config $config, $env, $release)
    {
        $cwd = rtrim($config->getCwd(), '/').'/'; // "/" to sync all files inside the top dir

        $excl = null;
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = rtrim($config->getParameter(sprintf('project.environments.%s.remote.path', $env)), '/').'/releases/'.$release;

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

    /**
     * Builds the rsync command from config arguments.
     *
     * @param [object] \Config
     * @return [string] Rsync command to be executed
     */
    public static function releaseSymlink(Config $config, $env, $release)
    {
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $latestReleasePath = rtrim($config->getParameter(sprintf('project.environments.%s.remote.path', $env)), '/').'/releases/'.$release;
        $targetDirectory = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));

        // create symlink from current to latest release
        $lnsf = vsprintf('cd %s && ln -s %s current', array($targetDirectory, $latestReleasePath));

        $cmd = vsprintf('ssh -t %s@%s "%s"', array($user, $host, $lnsf));
        
        return $cmd;
    }

    /**
     * Builds the remote dirs prepare command.
     *
     */
    public static function remoteDirs(Config $config, $env, $release)
    {
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));

        $pathes = array(
            // $path.'/releases/latest',
            $path.'/releases/'.$release,
        );

        $mkdirDirs = implode(' ', $pathes);
        $mkdir = vsprintf('mkdir -p %s', array($mkdirDirs));

        $cmd = vsprintf('ssh -t %s@%s "%s"', array($user, $host, $mkdir));

        return $cmd;
    }
}
