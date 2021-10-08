<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

class ServerConfig
{
    private string $secretKey;
    private int $wgPort;

    public function __construct(string $secretKey, int $wgPort)
    {
        $this->secretKey = $secretKey;
        $this->wgPort = $wgPort;
    }

    /**
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
     */
    public function get(array $profileConfigList): string
    {
        $ipFourList = [];
        $ipSixList = [];
        foreach ($profileConfigList as $profileConfig) {
            if ('wireguard' !== $profileConfig->vpnProto()) {
                // we only want WireGuard profiles
                continue;
            }
            $ipFourList[] = $profileConfig->range()->firstHostPrefix();
            $ipSixList[] = $profileConfig->range6()->firstHostPrefix();
        }
        $ipList = implode(',', array_merge($ipFourList, $ipSixList));

        return <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$this->wgPort}
            PrivateKey = {$this->secretKey}
            EOF;
    }
}
