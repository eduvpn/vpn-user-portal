<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\OpenVpn\CA\CaInterface;
use Vpn\Portal\OpenVpn\TlsCrypt;

class ServerInfo
{
    private string $portalUrl;
    private string $keyDir;
    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;
    private int $wgPort;
    private string $oauthPublicKey;

    public function __construct(string $portalUrl, string $keyDir, CaInterface $ca, TlsCrypt $tlsCrypt, int $wgPort, string $oauthPublicKey)
    {
        $this->portalUrl = $portalUrl;
        $this->keyDir = $keyDir;
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->wgPort = $wgPort;
        $this->oauthPublicKey = $oauthPublicKey;
    }

    public function portalUrl(): string
    {
        return $this->portalUrl;
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
        $publicKeyFile = sprintf('%s/wireguard.%d.public.key', $this->keyDir, $nodeNumber);
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
