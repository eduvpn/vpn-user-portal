<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class RadiusAuthenticationConfig extends Config
{
    /**
     * @return string|null
     */
    public function getRealm()
    {
        return $this->optionalString('realm');
    }

    /**
     * @return string|null
     */
    public function getNasIdentifier()
    {
        return $this->optionalString('nasIdentifier');
    }

    /**
     * @return array<RadiusServerConfig>
     */
    public function getServerList()
    {
        if (!\array_key_exists('serverList', $this->configData)) {
            return [];
        }

        $serverList = [];
        foreach ($this->configData['serverList'] as $serverConfigData) {
            $serverList[] = new RadiusServerConfig($serverConfigData);
        }

        return $serverList;
    }
}
