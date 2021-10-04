<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\WireGuard;

use LC\Portal\FileIO;
use LC\Portal\IP;

class ServerConfig
{
    private string $dataDir;

    // XXX perhaps we can give every node their own wgPort / wgPublicKey
    // instead of one globally?
    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
    }

    /**
     * @param array<\LC\Portal\ProfileConfig> $profileConfigList
     */
    public function get(array $profileConfigList, int $wgPort): string
    {
        $privateKey = $this->privateKey();
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

        return <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$wgPort}
            PrivateKey = {$privateKey}
            EOF;
    }

    public function publicKey(): string
    {
        return Key::extractPublicKey($this->privateKey());
    }

    private function privateKey(): string
    {
        $keyFile = $this->dataDir.'/wireguard.key';
        if (FileIO::exists($keyFile)) {
            return FileIO::readFile($keyFile);
        }

        $privateKey = Key::generatePrivateKey();
        FileIO::writeFile($keyFile, $privateKey);

        return $privateKey;
    }
}
