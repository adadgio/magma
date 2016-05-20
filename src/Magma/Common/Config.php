<?php

namespace Magma\Common;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\PropertyAccess\PropertyAccess;

class Config
{
    /**
     * @const Local config file name
     */
    const CONFIG_FILENAME = 'magma.yml';

    /**
     * Class contructor, reads the config.
     */
    public function __construct()
    {
        $this->read();
    }
    
    /**
     * Read and parse the local config file.
     *
     * @return [object] \Config
     */
    public function read()
    {
        $configFile = $this->getCwd().'/'.static::CONFIG_FILENAME;

        if (!is_file($configFile)) {
            throw new \Exception(sprintf('Not a Magma project (config file "magma.yml" not found at "%s"', $configFile));
        }

        $this->config = Yaml::parse(file_get_contents($configFile));

        return $this;
    }

    /**
     * Get all config tree.
     *
     * @return [array] Config tree
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get current working directory path.
     *
     * @return [string] Server cwd path
     */
    public function getCwd()
    {
        return getcwd();
    }

    /**
     * Get config parameter via property access.
     *
     * @param  [string] $property Property accessor
     * @return [mixed] Config parameter value
     */
    public function getParameter($dotsNotation)
    {
        // transform dots access to property access
        $propertyAccessor = $this->dotsToPropertyAccess($dotsNotation);

        $accessor = PropertyAccess::createPropertyAccessor();

        return $accessor->getValue($this->config, $propertyAccessor);
    }

    /**
     * Transforms dot notation "word1.word2" into property access notation [word1][word2].
     *
     * @param  [string] $dotsNotation The dot notation standard string
     * @return [string] A symfony readable property accessor string
     */
    private function dotsToPropertyAccess($dotsNotation)
    {
        $words = explode('.', $dotsNotation);

        return implode('', array_map(function ($w) { return sprintf('[%s]', $w); }, $words));
    }

    /**
     * Checks the config array for validity.
     *
     * @return [boolean] True or false
     * @todo Check important stuff are here
     */
    private function isValid()
    {
        return true;
    }
}
