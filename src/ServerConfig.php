<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use RuntimeException;

class ServerConfig
{
    /** @var array<ProfileConfig> */
    private array $profileConfigList;

    private OpenVpnServerConfig $openVpnServerConfig;
    private WgServerConfig $wgServerConfig;

    /**
     * @param array<ProfileConfig> $profileConfigList
     */
    public function __construct(array $profileConfigList, OpenVpnServerConfig $openVpnServerConfig, WgServerConfig $wgServerConfig)
    {
        $this->profileConfigList = $profileConfigList;
        $this->wgServerConfig = $wgServerConfig;
        $this->openVpnServerConfig = $openVpnServerConfig;
    }

    /**
     * XXX better name!
     *
     * @return array<string,string>
     */
    public function getProfiles(): array
    {
        // XXX fix ServerConfigCheck for WG as well!
//        ServerConfigCheck::verify($this->profileConfigList);
        $serverConfig = [];
        foreach ($this->profileConfigList as $profileConfig) {
            switch ($profileConfig->vpnType()) {
                case 'openvpn':
                    $serverConfig = array_merge($serverConfig, $this->openVpnServerConfig->getProfile($profileConfig));
                    break;
                case 'wireguard':
                    $serverConfig = array_merge($serverConfig, $this->wgServerConfig->getProfile($profileConfig));
                    break;
                default:
                    throw new RuntimeException('unsupported "vpnType"');
            }
        }

        return $serverConfig;
    }
}
