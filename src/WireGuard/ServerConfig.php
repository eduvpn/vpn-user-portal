<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\WireGuard;

use Vpn\Portal\Cfg\WireGuardConfig;
use Vpn\Portal\Exception\ServerConfigException;
use Vpn\Portal\FileIO;

class ServerConfig
{
    private string $keyDir;
    private WireGuardConfig $wgConfig;

    public function __construct(string $keyDir, WireGuardConfig $wgConfig)
    {
        // make sure "keyDir" exists
        FileIO::mkdir($keyDir);
        $this->keyDir = $keyDir;
        $this->wgConfig = $wgConfig;
    }

    /**
     * @param array<\Vpn\Portal\Cfg\ProfileConfig> $profileConfigList
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

        $this->registerPublicKey($nodeNumber, $publicKey);

        return <<< EOF
            [Interface]
            MTU = {$this->wgConfig->setMtu()}
            Address = {$ipList}
            ListenPort = {$this->wgConfig->listenPort()}
            PrivateKey = {{PRIVATE_KEY}}
            EOF;
    }

    private function registerPublicKey(int $nodeNumber, string $publicKey): void
    {
        $publicKeyFile = sprintf('%s/wireguard.%d.public.key', $this->keyDir, $nodeNumber);
        if (!FileIO::exists($publicKeyFile)) {
            // we do not yet know this node's public key, write it
            FileIO::write($publicKeyFile, $publicKey);

            return;
        }

        // we already know this node's public key... compare it to what we get,
        // it MUST be the same!
        if ($publicKey !== FileIO::read($publicKeyFile)) {
            throw new ServerConfigException(sprintf('node "%d" already registered a public key, but it does not match anymore, delete the existing public key first from "/var/lib/vpn-user-portal/keys"', $nodeNumber));
        }
    }
}
