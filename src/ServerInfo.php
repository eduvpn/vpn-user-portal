<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal;

use Vpn\Portal\OpenVpn\CA\CaInterface;
use Vpn\Portal\OpenVpn\TlsCrypt;

class ServerInfo
{
    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;
    private string $wgPublicKey;
    private int $wgPort;
    private string $oauthPublicKey;

    public function __construct(CaInterface $ca, TlsCrypt $tlsCrypt, string $wgPublicKey, int $wgPort, string $oauthPublicKey)
    {
        $this->ca = $ca;
        $this->tlsCrypt = $tlsCrypt;
        $this->wgPublicKey = $wgPublicKey;
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

    public function wgPublicKey(): string
    {
        return $this->wgPublicKey;
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
