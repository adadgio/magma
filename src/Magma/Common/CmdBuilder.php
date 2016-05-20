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
        $path = rtrim($config->getParameter(sprintf('project.environments.%s.remote.path', $env)), '/');

        $latestReleasePath = $path.'/releases/'.$release;
        $symlinkPath = $path.'/current';

        // create symlink from current to latest release
        $lnsf = vsprintf('cd %s && find -type l -delete && ln -s %s %s', array($path, $latestReleasePath, $symlinkPath));

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

        $dirs = implode(' ', $pathes);
        $bash = vsprintf('mkdir -p %s', array($dirs));

        $cmd = vsprintf('ssh -t %s@%s "%s"', array($user, $host, $bash));

        return $cmd;
    }

    /**
     * Builds the rsync command from config arguments.
     *
     * @param [object] \Config
     * @return [string] Rsync command to be executed
     */
    public static function postDeploy(Config $config, $env, $release)
    {
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));
        $tasks = $config->getParameter(sprintf('project.environments.%s.remote.post_deploy', $env));
        $releasePath = $path.'/releases/'.$release;

        if (empty($tasks)) {
            return false;
        }

        // cd into latest release
        $taskCd = sprintf('cd %s', $releasePath);
        $bash = $taskCd.' && '.implode(' && ', $tasks);
        
        $cmd = vsprintf('ssh -t %s@%s "%s"', array($user, $host, $bash));
        return $cmd;
    }
}
