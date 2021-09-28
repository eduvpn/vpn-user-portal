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

class WgServerConfig
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
     *
     * @return array<string,string>
     */
    public function get(array $profileConfigList, int $wgPort): array
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

        $wgConfig = <<< EOF
            [Interface]
            Address = {$ipList}
            ListenPort = {$wgPort}
            PrivateKey = {$privateKey}
            EOF;

        return ['wg0.conf' => $wgConfig];
    }

    public function publicKey(): string
    {
        return self::extractPublicKey($this->privateKey());
    }

    private function privateKey(): string
    {
        $keyFile = $this->dataDir.'/wireguard.key';
        if (FileIO::exists($keyFile)) {
            return FileIO::readFile($keyFile);
        }

        $privateKey = self::generatePrivateKey();
        FileIO::writeFile($keyFile, $privateKey);

        return $privateKey;
    }

    /**
     * XXX duplicate in Wg.php.
     */
    private static function generatePrivateKey(): string
    {
        ob_start();
        passthru('/usr/bin/wg genkey');

        return trim(ob_get_clean());
    }

    private static function extractPublicKey(string $privateKey): string
    {
        ob_start();
        passthru("echo {$privateKey} | /usr/bin/wg pubkey");

        return trim(ob_get_clean());
    }
}
