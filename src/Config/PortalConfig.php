<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

use DateInterval;
use LC\Portal\Config\Exception\ConfigException;

class PortalConfig extends Config
{
    /**
     * @return string|null
     */
    public function getStyleName()
    {
        return $this->optionalString('styleName');
    }

    /**
     * @return bool
     */
    public function getSecureCookie()
    {
        if (null === $configValue = $this->optionalBool('secureCookie')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return string
     */
    public function getAuthMethod()
    {
        if (null === $configValue = $this->optionalString('authMethod')) {
            return 'DbAuthentication';
        }

        return $configValue;
    }

    /**
     * @return \DateInterval
     */
    public function getSessionExpiry()
    {
        if (null === $configValue = $this->optionalString('sessionExpiry')) {
            return new DateInterval('P90D');
        }

        return new DateInterval($configValue);
    }

    /**
     * @return array<string>
     */
    public function getAdminPermissionList()
    {
        if (null === $configValue = $this->optionalStringArray('adminPermissionList')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getAdminUserIdList()
    {
        if (null === $configValue = $this->optionalStringArray('adminUserIdList')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return bool
     */
    public function getRequireTwoFactor()
    {
        if (null === $configValue = $this->optionalBool('requireTwoFactor')) {
            return false;
        }

        return $configValue;
    }

    /**
     * @return array<string>
     */
    public function getTwoFactorMethods()
    {
        if (null === $configValue = $this->optionalStringArray('twoFactorMethods')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string,string>
     */
    public function getSupportedLanguages()
    {
        if (null === $configValue = $this->optionalStringStringArray('supportedLanguages')) {
            return ['en_US' => 'English'];
        }

        return $configValue;
    }

    /**
     * @return SamlAuthenticationConfig
     */
    public function getSamlAuthenticationConfig()
    {
        if (!\array_key_exists('SamlAuthentication', $this->configData)) {
            throw new ConfigException('key "SamlAuthentication" is missing');
        }

        return new SamlAuthenticationConfig($this->configData['SamlAuthentication']);
    }

    /**
     * @return LdapAuthenticationConfig
     */
    public function getLdapAuthenticationConfig()
    {
        if (!\array_key_exists('LdapAuthentication', $this->configData)) {
            throw new ConfigException('key "LdapAuthentication" is missing');
        }

        return new LdapAuthenticationConfig($this->configData['LdapAuthentication']);
    }

    /**
     * @return RadiusAuthenticationConfig
     */
    public function getRadiusAuthenticationConfig()
    {
        if (!\array_key_exists('RadiusAuthentication', $this->configData)) {
            throw new ConfigException('key "RadiusAuthentication" is missing');
        }

        return new RadiusAuthenticationConfig($this->configData['RadiusAuthentication']);
    }

    /**
     * @return bool
     */
    public function getEnableApi()
    {
        if (null === $configValue = $this->optionalBool('enableApi')) {
            return true;
        }

        return $configValue;
    }

    /**
     * @return ApiConfig
     */
    public function getApiConfig()
    {
        $apiConfigData = [];
        if (\array_key_exists('Api', $this->configData)) {
            $apiConfigData = $this->configData['Api'];
        }

        return new ApiConfig($apiConfigData);
    }

    /**
     * @return array<string,ProfileConfig>
     */
    public function getProfileConfigList()
    {
        if (!\array_key_exists('ProfileList', $this->configData)) {
            return [];
        }

        if (!\is_array($this->configData['ProfileList'])) {
            // XXX
            throw new ConfigException('');
        }

        $profileConfigList = [];
        foreach ($this->configData['ProfileList'] as $profileId => $profileConfigData) {
            // XXX make sure profileId = string
            // XXX make sure profileConfigData = array
            $profileConfigList[$profileId] = new ProfileConfig($profileConfigData);
        }

        return $profileConfigList;
    }
}
