<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\OpenVpn\ServerConfig as OpenVpnServerConfig;
use Vpn\Portal\WireGuard\ServerConfig as WireGuardServerConfig;

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
     * @param array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList
     *
     * @return array<string,string>
     */
    public function get(array $profileConfigList, int $nodeNumber, string $publicKey, bool $cpuHasAes): array
    {
        $serverConfig = [];
        foreach ($profileConfigList as $profileConfig) {
            if ($profileConfig->oSupport()) {
                $serverConfig = array_merge($serverConfig, $this->openVpnServerConfig->getProfile($profileConfig, $nodeNumber, $cpuHasAes));
            }
        }

        if (null === $wgConfig = $this->wireGuardServerConfig->get($profileConfigList, $nodeNumber, $publicKey)) {
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
