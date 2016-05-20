<?php

namespace Magma\Common;

class RsyncOutput
{
    /**
     * [readToCheck description]
     * @param  [type] $buffer [description]
     * @return [type]         [description]
     */
    public static function toCheck($buffer)
    {
        if (preg_match('~(xfer#([0-9]+), to-check=([0-9]+)/([0-9]+))~', $buffer, $matches)) {
            return array(
                'nth_transfer'  => $matches[2],
                'to_check'      => $matches[3],
                'total'         => $matches[4],
            );
        } else {
            return false;
        }
    }

    /**
     * [inTranser description]
     * @param  [type] $buffer [description]
     * @return [type]         [description]
     */
    public static function inTranser($buffer)
    {
        if (preg_match('~^([a-z0-9\/\.]+)$~i', trim($buffer), $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * [toConsider description]
     * @param  [type] $buffer [description]
     * @return [type]         [description]
     */
    public static function toConsider($buffer)
    {
        if (preg_match('~([0-9]+) files to consider~', $buffer, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * [readUptodate description]
     * @return [type] [description]
     */
    public static function upToDate($buffer)
    {
        if (preg_match('~(.*) is uptodate~', $buffer, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }
}
