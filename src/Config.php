<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\Exception\ConfigException;

class Config
{
    /** @var array */
    protected $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
        $this->configData = array_merge(static::defaultConfig(), $configData);
    }

    /**
     * @return array
     */
    public static function defaultConfig()
    {
        return [];
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasSection($key)
    {
        if (!\array_key_exists($key, $this->configData)) {
            return false;
        }

        return \is_array($this->configData[$key]);
    }

    /**
     * @param string $key
     *
     * @return Config
     */
    public function getSection($key)
    {
        if (false === $this->hasSection($key)) {
            throw new ConfigException(sprintf('"%s" is not a section', $key));
        }

        // do not return the parent object if we were subclassed, but an actual
        // "Config" object to avoid copying in the defaults if set
        return new self($this->configData[$key]);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasItem($key)
    {
        return \array_key_exists($key, $this->configData);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getItem($key)
    {
        if (false === $this->hasItem($key)) {
            throw new ConfigException(sprintf('item "%s" not available', $key));
        }

        return $this->configData[$key];
    }

    /**
     * @param string     $key
     * @param mixed|null $defaultValue
     *
     * @return mixed
     */
    public function optionalItem($key, $defaultValue = null)
    {
        if (!$this->hasItem($key)) {
            return $defaultValue;
        }

        return $this->getItem($key);
    }

    /**
     * @psalm-suppress UnresolvableInclude
     *
     * @param string $configFile
     *
     * @return Config
     */
    public static function fromFile($configFile)
    {
        if (false === FileIO::exists($configFile)) {
            throw new ConfigException(sprintf('unable to read "%s"', $configFile));
        }

        return new self(require $configFile);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->configData;
    }

    /**
     * @param string $configFile
     * @param int    $mode
     *
     * @return void
     */
    public static function toFile($configFile, array $configData, $mode = 0600)
    {
        $fileData = sprintf('<?php return %s;', var_export($configData, true));
        FileIO::writeFile($configFile, $fileData, $mode);
    }
}
