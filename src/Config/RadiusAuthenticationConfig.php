<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Config;

class RadiusAuthenticationConfig extends Config
{
    public function getRealm(): ?string
    {
        return $this->optionalString('realm');
    }

    public function getNasIdentifier(): ?string
    {
        return $this->optionalString('nasIdentifier');
    }

    /**
     * @return array<RadiusServerConfig>
     */
    public function getServerList(): array
    {
        if (!\array_key_exists('ServerList', $this->configData)) {
            return [];
        }

        $serverList = [];
        foreach ($this->configData['ServerList'] as $serverConfigData) {
            $serverList[] = new RadiusServerConfig($serverConfigData);
        }

        return $serverList;
    }
}
