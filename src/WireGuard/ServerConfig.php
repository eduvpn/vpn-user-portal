<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\IP;

class ServerConfig
{
    /**
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
     */
    public function get(array $profileConfigList, int $wgPort): string
    {
        $ipFourList = [];
        $ipSixList = [];
        foreach ($profileConfigList as $profileConfig) {
            if ('wireguard' !== $profileConfig->vpnProto()) {
                // we only want WireGuard profiles
                continue;
            }
            $ipFour = IP::fromIpPrefix($profileConfig->range());
            $ipSix = IP::fromIpPrefix($profileConfig->range6());
            $ipFourList[] = $ipFour->firstHostPrefix();
            $ipSixList[] = $ipSix->firstHostPrefix();
        }
        $ipList = implode(',', array_merge($ipFourList, $ipSixList));

        // the server will replace "{{PRIVATE_KEY}}" by its local private key
        return <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$wgPort}
            PrivateKey = {{PRIVATE_KEY}}
            EOF;
    }
}
