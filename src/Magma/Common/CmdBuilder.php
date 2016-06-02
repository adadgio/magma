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
     * Set up symfony permissions, (for Debian only!)
     * @link http://symfony.com/doc/current/book/installation.html#book-installation-permissions
     */
    public static function sfPerms()
    {
        return array(
            //'rm -rf app/cache/* app/logs/*',
            'chmod -R 775 app/logs',
            'chmod -R 775 app/cache',
            // "HTTPDUSER=`ps axo user,comm | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`",
            // 'setfacl -R -m u:\"$HTTPDUSER\":rwX -m u:`whoami`:rwX app/cache app/logs',
            // 'setfacl -dR -m u:\"$HTTPDUSER\":rwX -m u:`whoami`:rwX app/cache app/logs',
        );
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
     * -a Use archive mode, quicker uploads
     * -p preserver (destination) permissions (a priori)
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
                return " --exclude=".trim($dir, '/'); // formats to "dir/path/"
            }, $options['exclude']);

            $exclude = implode('', $options['exclude']);

        } else {
            $exclude = null;
        }

        // handle delete excluded option
        if (static::RSYNC_DELETE_EXCLUDED === $options['delete-excluded']) {
            $deleteExcluded = ' --delete-excluded';
        }
        
        //print_r($exclude); exit;

        // build the final rsync command
        $command = sprintf('rsync -avzP --stats --delete%s -v%s %s %s@%s:%s',
            $deleteExcluded, $exclude, $cwd, $user, $host, $remotePath
        );

        return $command;
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

        return sprintf('ln -sfn %s %s', $target, $symlink); // "-f" force, "-n" prevents nested symlinks to be created
    }

    /**
     * @deprecated Not used anywhere
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
