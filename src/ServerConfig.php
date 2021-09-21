<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\OpenVpn\OpenVpnServerConfig;
use LC\Portal\WireGuard\WgServerConfig;

class ServerConfig
{
    private OpenVpnServerConfig $openVpnServerConfig;
    private WgServerConfig $wgServerConfig;

    public function __construct(OpenVpnServerConfig $openVpnServerConfig, WgServerConfig $wgServerConfig)
    {
        $this->wgServerConfig = $wgServerConfig;
        $this->openVpnServerConfig = $openVpnServerConfig;
    }

    /**
     * @param array<ProfileConfig> $profileConfigList
     *
     * @return array<string,string>
     */
    public function get(array $profileConfigList, bool $cpuHasAes): array
    {
        // XXX fix ServerConfigCheck for WG as well!
//        ServerConfigCheck::verify($profileConfigList);
        $serverConfig = [];
        foreach ($profileConfigList as $profileConfig) {
            if ('openvpn' === $profileConfig->vpnProto()) {
                $serverConfig = array_merge($serverConfig, $this->openVpnServerConfig->getProfile($profileConfig, $cpuHasAes));
            }
        }

        return array_merge($serverConfig, $this->wgServerConfig->get($profileConfigList));
    }
}
