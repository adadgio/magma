<?php

namespace Magma\Common;

class Release
{
    /**
     * @const Local config file name
     */
    protected $name;

    /**
     * Class contructor, reads the config.
     */
    public function __construct()
    {
        $this->name = microtime();
    }

    /**
     * Get release name.
     *
     * @return [string] Release name
     */
    public function getName()
    {
        return $this->name;
    }
}
