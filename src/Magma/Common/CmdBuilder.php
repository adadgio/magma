<?php

namespace Magma\Common;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CmdBuilder
{
    private static $defaultExclude = array(
        '.git/', '.gitignore', '.gitkeep', '.DS_Store', 'magma.yml',
    );

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
        $exclude = array_merge(self::$defaultExclude, $exclude);

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

        $releasePath = $path.'/releases/'.$release;
        $symlinkPath = $path.'/current';

        // create symlink from current to latest release
        $lnsf = sprintf('ln -s %s %s', $releasePath, $symlinkPath);
        $cmd = sprintf('ssh -t %s@%s "%s"', $user, $host, $lnsf);

        return $cmd;
    }

    /**
     * [sharedDirs description]
     * @param  Config $config  [description]
     * @param  [type] $env     [description]
     * @param  [type] $release [description]
     * @return [type]          [description]
     */
    public static function sharedDirs(Config $config, $env, $release)
    {
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));
        $shared = $config->getParameter('project.shared');

        $releasePath = $path.'/releases/'.$release;

        // add other symlinks for shared directories
        // create symlinks for each shared directory
        $lnlses = array();
        foreach ($shared as $dir) {
            $target = $path.'/shared/'.rtrim($dir, '/');
            $origin = $releasePath.'/'.rtrim($dir, '/');
            $lnlses[] = "ln -s {$target} {$origin}";
        }

        if (empty($lnlses)) {
            return false;
        }

        $lnsf = implode(' && ', $lnlses);
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
        $shared = $config->getParameter('project.shared');

        $releasePath = $path.'/releases/'.$release;

        $pathes = array(
            $path.'/shared',
            $releasePath,
        );

        // also create all the shared folders
        foreach($shared as $sharedDir) {
            $pathes[] = $path.'/shared/'.$sharedDir;
        }

        $dirs = implode(' ', $pathes);
        $bash = vsprintf('mkdir -p %s', array($dirs)); // not used: .' && touch '.$path.'/revisions.log'

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
