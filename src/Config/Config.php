<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

use LC\Portal\Config\Exception\ConfigException;
use LC\Portal\FileIO;

abstract class Config
{
    /** @var array */
    protected $configData;

    public function __construct(array $configData)
    {
        $this->configData = $configData;
    }

    /**
     * @psalm-suppress UnresolvableInclude
     *
     * @return static
     */
    public static function fromFile(string $configFile)
    {
        if (false === FileIO::exists($configFile)) {
            throw new ConfigException(sprintf('unable to read "%s"', $configFile));
        }

        return new static(require $configFile);
    }

    public function optionalString(string $configKey): ?string
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_string($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "string"', $configKey));
        }

        return $this->configData[$configKey];
    }

    public function requireString(string $configKey): string
    {
        if (null === $configValue = $this->optionalString($configKey)) {
            throw new ConfigException(sprintf('key "%s" is missing', $configKey));
        }

        return $configValue;
    }

    public function optionalBool(string $configKey): ?bool
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_bool($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "bool"', $configKey));
        }

        return $this->configData[$configKey];
    }

    public function requireBool(string $configKey): bool
    {
        if (null === $configValue = $this->optionalBool($configKey)) {
            throw new ConfigException(sprintf('key "%s" is missing', $configKey));
        }

        return $configValue;
    }

    public function optionalInt(string $configKey): ?int
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_int($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "int"', $configKey));
        }

        return $this->configData[$configKey];
    }

    public function requireInt(string $configKey): int
    {
        if (null === $configValue = $this->optionalInt($configKey)) {
            throw new ConfigException(sprintf('key "%s" is missing', $configKey));
        }

        return $configValue;
    }

    /**
     * @return array<string>|null
     */
    public function optionalStringArray(string $configKey): ?array
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_array($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "array"', $configKey));
        }

        foreach ($this->configData[$configKey] as $arrayValue) {
            if (!\is_string($arrayValue)) {
                throw new ConfigException(
                    sprintf('not all values of key "%s" are of type "string"', $configKey)
                );
            }
        }

        return $this->configData[$configKey];
    }

    /**
     * @return array<string>
     */
    public function requireStringArray(string $configKey): array
    {
        if (null === $configValue = $this->optionalStringArray($configKey)) {
            throw new ConfigException(sprintf('key "%s" is missing', $configKey));
        }

        return $configValue;
    }

    /**
     * @return array<string,string>|null
     */
    public function optionalStringStringArray(string $configKey): ?array
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_array($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "array"', $configKey));
        }

        foreach ($this->configData[$configKey] as $arrayKey => $arrayValue) {
            if (!\is_string($arrayKey)) {
                throw new ConfigException(
                    sprintf('not all keys of key "%s" are of type "string"', $configKey)
                );
            }
            if (!\is_string($arrayValue)) {
                throw new ConfigException(
                    sprintf('not all values of key "%s" are of type "string"', $configKey)
                );
            }
        }

        return $this->configData[$configKey];
    }

    /**
     * @return array<string,string>
     */
    public function requireStringStringArray(string $configKey): array
    {
        if (null === $configValue = $this->optionalStringStringArray($configKey)) {
            throw new ConfigException(sprintf('key "%s" is missing', $configKey));
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string>>|null
     */
    public function optionalStringWithStringArray(string $configKey): ?array
    {
        if (!\array_key_exists($configKey, $this->configData)) {
            return null;
        }

        if (!\is_array($this->configData[$configKey])) {
            throw new ConfigException(sprintf('value of key "%s" is not of type "array"', $configKey));
        }

        foreach ($this->configData[$configKey] as $arrayKey => $arrayValue) {
            if (!\is_string($arrayKey)) {
                throw new ConfigException(
                    sprintf('not all keys of key "%s" are of type "string"', $configKey)
                );
            }
            if (!\is_array($arrayValue)) {
                throw new ConfigException(
                    sprintf('not all values of key "%s" are of type "array"', $configKey)
                );
            }
            foreach ($arrayValue as $aV) {
                if (!\is_string($aV)) {
                    throw new ConfigException(
                        sprintf('not all values of key "%s" are of type "array<string>"', $configKey)
                    );
                }
            }
        }

        return $this->configData[$configKey];
    }
}
