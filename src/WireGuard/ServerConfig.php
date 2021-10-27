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
    public function get(array $profileConfigList, int $nodeNumber): ?string
    {
        $ipFourList = [];
        $ipSixList = [];
        foreach ($profileConfigList as $profileConfig) {
            if ('wireguard' !== $profileConfig->vpnProto()) {
                // we only want WireGuard profiles
                continue;
            }
            $ipFourList[] = $profileConfig->range($nodeNumber)->firstHostPrefix();
            $ipSixList[] = $profileConfig->range6($nodeNumber)->firstHostPrefix();
        }
        $ipList = implode(',', array_merge($ipFourList, $ipSixList));

        if (0 === \count($ipFourList) || 0 === \count($ipSixList)) {
            // apparently we did not have any WireGuard profiles...
            return null;
        }

        return <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$this->wgPort}
            PrivateKey = {$this->secretKey}
            EOF;
    }
}
