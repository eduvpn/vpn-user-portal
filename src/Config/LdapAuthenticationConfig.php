<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class LdapAuthenticationConfig extends Config
{
    /**
     * @return string
     */
    public function getLdapUri()
    {
        return $this->requireString('ldapUri');
    }

    /**
     * @return string
     */
    public function getBindDnTemplate()
    {
        return $this->requireString('bindDnTemplate');
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
    public function getBaseDn()
    {
        return $this->optionalString('baseDn');
    }

    /**
     * @return string|null
     */
    public function getUserFilterTemplate()
    {
        return $this->optionalString('userFilterTemplate');
    }
}
