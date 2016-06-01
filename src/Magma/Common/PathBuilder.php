<?php

namespace Magma\Common;

class PathBuilder
{
    const DIRECTORY_SEPARATOR = '/';

    /**
     * [create description]
     * @param  [type] $basePath    [description]
     * @param  array  $directories [description]
     * @return [type]              [description]
     */
    public static function create($path, array $directories = array())
    {
        $pathes = array();
        $path = self::rtrimSlash($path);

        foreach ($directories as $directory) {
            $pathes[] = $path.static::DIRECTORY_SEPARATOR.self::rtrimSlash($directory);
        }

        return $pathes;
    }

    public static function rtrimSlash($path)
    {
        return rtrim($path, '/');
    }
}
