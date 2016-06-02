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
    public function __construct($name = null)
    {
        if (null === $name) {
            $this->name = time();
        } else {
            $this->name = $name;
        }

        if (empty($this->name)) {
            throw new \Exception('Release name cannot be empty');
        }
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
