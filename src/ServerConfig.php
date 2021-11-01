<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\OpenVpn\ServerConfig as OpenVpnServerConfig;
use LC\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

class ServerConfig
{
    private OpenVpnServerConfig $openVpnServerConfig;
    private WireGuardServerConfig $wireGuardServerConfig;

    public function __construct(OpenVpnServerConfig $openVpnServerConfig, WireGuardServerConfig $wireGuardServerConfig)
    {
        $this->wireGuardServerConfig = $wireGuardServerConfig;
        $this->openVpnServerConfig = $openVpnServerConfig;
    }

    /**
     * @param array<ProfileConfig> $profileConfigList
     *
     * @return array<string,string>
     */
    public function get(array $profileConfigList, int $nodeNumber, bool $cpuHasAes): array
    {
        // XXX fix ServerConfigCheck for WG as well!
//        ServerConfigCheck::verify($profileConfigList);
        $serverConfig = [];
        foreach ($profileConfigList as $profileConfig) {
            if ($profileConfig->oSupport()) {
                $serverConfig = array_merge($serverConfig, $this->openVpnServerConfig->getProfile($profileConfig, $nodeNumber, $cpuHasAes));
            }
        }

        if (null === $wgConfig = $this->wireGuardServerConfig->get($profileConfigList, $nodeNumber)) {
            // no WireGuard profiles
            return $serverConfig;
        }

        return array_merge(
            $serverConfig,
            [
                'wg.conf' => $wgConfig,
            ]
        );
    }
}
