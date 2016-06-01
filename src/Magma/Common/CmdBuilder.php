<?php

namespace Magma\Common;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CmdBuilder
{
    /**
     * @var boolean Rsync option, deletes excluded dirs/files from remote
     */
    const RSYNC_DELETE_EXCLUDED = true;

    private static $defaultExclude = array(
        '.git/', '.gitignore', '.gitkeep', '.DS_Store', 'magma.yml',
    );

    /**
     * Chain bash commands.
     *
     * @param  array  List of separate bash commands
     * @return string Merged (chained) commands as a string
     */
    public static function chain(array $commands = array())
    {
        return implode(' && ', $commands);
    }

    /**
     * Create a command to export a variable to environment.
     *
     * @param  [type] $variable [description]
     * @param  [type] $value    [description]
     * @return [type]           [description]
     */
    public static function export($variable, $value)
    {
        return sprintf('export %s=%s', $variable, $value);
    }

    /**
     * Create a command to cd into a directory.
     *
     * @param  [type] $directory [description]
     * @return [type]            [description]
     */
    public static function cd($directory)
    {
        return 'cd '.$directory;
    }

    /**
     * Create a bash command to execute bash instruction(s) to remote.
     *
     * @param  [string] Remote $user
     * @param  [string] Remote $host
     * @param  [mixed]  String or array of bash commands
     * @return [string] Final ssh bash command
     */
    public static function ssh($user, $host, $instructions)
    {
        if (is_array($instructions)) {
            $bash = self::chain($instructions);
        } else {
            $bash = $instructions;
        }

        return sprintf('ssh -t -t %s@%s "%s"', $user, $host, $bash);
    }

    /**
     * Create bash commands to create directories recursively.
     *
     * @param  [array] List of directories
     * @return [array] Bash commands
     */
    public static function mkdirs(array $directories = array())
    {
        return array_map(function ($dir) {
            return 'mkdir -p '.$dir;
        }, $directories);
    }

    /**
     * Create a bash rsync files and folder from the current working
     * directory to the specified remote directory.
     *
     * @param  [type] $user       [description]
     * @param  [type] $host       [description]
     * @param  [type] $remotePath [description]
     * @param  array  $options    [description]
     * @return [type]             [description]
     */
    public static function rsync($user, $host, $remotePath, array $options = array())
    {
        $cwd = rtrim(getcwd(), '/').'/'; // rsync without top dir beeing created

        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'exclude' => self::$defaultExclude,
            'delete-excluded' => false,
        ));
        $options = $resolver->resolve($options);

        // handle exclude files/folders option
        if (null !== $options['exclude']) {
            // merge with default exclude list (crap list .DS_Store)
            $options['exclude'] = array_merge(self::$defaultExclude, $options['exclude']);

            // make sure exclude path is always relative
            $options['exclude'] = array_map(function ($dir) {
                return " --exclude=".trim($dir, '/').'/'; // formats to "dir/path/"
            }, $options['exclude']);

            $exclude = implode('', $options['exclude']);

        } else {
            $exclude = null;
        }

        // handle delete excluded option
        if (static::RSYNC_DELETE_EXCLUDED === $options['delete-excluded']) {
            $deleteExcluded = ' --delete-excluded';
        }

        // build the final rsync command
        $command = sprintf('rsync -avzP --stats --delete%s -v%s %s %s@%s:%s',
            $deleteExcluded, $exclude, $cwd, $user, $host, $remotePath
        );

        return $command;
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

        // create symlink from current to latest release
        $lnsf = sprintf('cd %s && rm -f current && ln -s %s current', $path, $releasePath);
        $cmd = sprintf('ssh -t %s@%s "%s"', $user, $host, $lnsf);

        return $cmd;
    }

    /**
     * Create a bash symlink command.
     *
     * @param  [type] $target  [description]
     * @param  [type] $symlink [description]
     * @return [type]          [description]
     */
    public static function symlink($target, $symlink)
    {
        $target = rtrim($target, '/');
        $symlink = rtrim($symlink, '/');

        return sprintf('ln -s %s %s', $target, $symlink);
    }
    

    /**
     * [setPermissions description]
     * @param Config $config  [description]
     * @param [type] $env     [description]
     * @param [type] $release [description]
     */
    public static function permissions(Config $config, $env, $release)
    {
        $user = $config->getParameter(sprintf('project.environments.%s.remote.user', $env));
        $host = $config->getParameter(sprintf('project.environments.%s.remote.host', $env));
        $path = $config->getParameter(sprintf('project.environments.%s.remote.path', $env));

        $writables = $config->getParameter(sprintf('project.writable_folders', $env));
        if (empty($writables)) {
            return false;
        }

        // folders permissions
        $bashes = array();
        foreach ($writables as $dir) {
            $dir = $path.'/current/'.trim($dir, '/');
            $bashes[] = "find {$dir} -type d -exec chmod 755 {} \;";
        }
        foreach ($writables as $dir) {
            $dir = $path.'/current/'.trim($dir, '/');
            $bashes[] = "find {$dir} -type f -exec chmod 644 {} \;";
        }

        $bash = implode(' && ', $bashes);
        $cmd = vsprintf('ssh -t %s@%s "%s"', array($user, $host, $bash));

        echo $cmd; exit;

        return $cmd;
    }
}
