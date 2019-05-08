<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class SamlAuthenticationConfig extends Config
{
    /**
     * @return string
     */
    public function getUserIdAttribute()
    {
        return $this->requireString('userIdAttribute');
    }

    /**
     * @return array<string>
     */
    public function getPermissionAttributeList()
    {
        if (null === $configValue = $this->optionalStringArray('permissionAttributeList')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return string|null
     */
    public function getSpEntityId()
    {
        return $this->optionalString('spEntityId');
    }

    /**
     * @return string
     */
    public function getIdpMetadata()
    {
        return $this->requireString('idpMetadata');
    }

    /**
     * @return string|null
     */
    public function getIdpEntityId()
    {
        return $this->optionalString('idpEntityId');
    }

    /**
     * @return string|null
     */
    public function getDiscoUrl()
    {
        return $this->optionalString('discoUrl');
    }

    /**
     * @return array<string>
     */
    public function getAuthnContext()
    {
        if (null === $configValue = $this->optionalStringArray('authnContext')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string,array<string>>
     */
    public function getPermissionAuthnContext()
    {
        if (null === $configValue = $this->optionalStringWithStringArray('permissionAuthnContext')) {
            return [];
        }

        return $configValue;
    }

    /**
     * @return array<string,string>
     */
    public function getPermissionSessionExpiry()
    {
        if (null === $configValue = $this->optionalStringArray('permissionSessionExpiry')) {
            return [];
        }

        return $configValue;
    }
}
