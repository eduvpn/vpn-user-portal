<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\WireGuard;

use Vpn\Portal\FileIO;

class ServerConfig
{
    private string $baseDir;
    private int $wgPort;

    public function __construct(string $baseDir, int $wgPort)
    {
        $this->baseDir = $baseDir;
        $this->wgPort = $wgPort;
    }

    /**
     * @param array<\Vpn\Portal\ProfileConfig> $profileConfigList
     */
    public function get(array $profileConfigList, int $nodeNumber, string $publicKey): ?string
    {
        $ipFourList = [];
        $ipSixList = [];
        foreach ($profileConfigList as $profileConfig) {
            if (!$profileConfig->wSupport()) {
                // we only want WireGuard profiles
                continue;
            }
            $ipFourList[] = $profileConfig->wRangeFour($nodeNumber)->firstHostPrefix();
            $ipSixList[] = $profileConfig->wRangeSix($nodeNumber)->firstHostPrefix();
        }
        $ipList = implode(',', array_merge($ipFourList, $ipSixList));

        if (0 === \count($ipFourList) || 0 === \count($ipSixList)) {
            // apparently we did not have any WireGuard profiles...
            return null;
        }

        $publicKeyFile = sprintf('%s/data/wireguard.%d.public.key', $this->baseDir, $nodeNumber);
        if (!FileIO::exists($publicKeyFile)) {
            // XXX what should we do when file exists? compare and scream when
            // it is not the same anymore?
            FileIO::write($publicKeyFile, $publicKey);
        }

        return <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$this->wgPort}
            PrivateKey = {{PRIVATE_KEY}}
            EOF;
    }
}
