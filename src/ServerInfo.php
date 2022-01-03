<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\OpenVpn\CA\CaInterface;
use Vpn\Portal\OpenVpn\TlsCrypt;

class ServerInfo
{
    private string $baseDir;
    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;
    private int $wgPort;
    private string $oauthPublicKey;

    public function __construct(string $baseDir, CaInterface $ca, TlsCrypt $tlsCrypt, int $wgPort, string $oauthPublicKey)
    {
        $this->baseDir = $baseDir;
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->wgPort = $wgPort;
        $this->oauthPublicKey = $oauthPublicKey;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
    }

    public function tlsCrypt(): TlsCrypt
    {
        return $this->tlsCrypt;
    }

    public function publicKey(int $nodeNumber): ?string
    {
        $publicKeyFile = sprintf('%s/data/wireguard.%d.public.key', $this->baseDir, $nodeNumber);
        if (!FileIO::exists($publicKeyFile)) {
            return null;
        }

        return FileIO::read($publicKeyFile);
    }

    public function wgPort(): int
    {
        return $this->wgPort;
    }

    public function oauthPublicKey(): string
    {
        return $this->oauthPublicKey;
    }
}
