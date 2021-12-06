<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use fkooman\OAuth\Server\PublicKey;
use LC\Portal\OpenVpn\CA\CaInterface;
use LC\Portal\OpenVpn\TlsCrypt;

class ServerInfo
{
    private CaInterface $ca;
    private TlsCrypt $tlsCrypt;
    private string $wgPublicKey;
    private int $wgPort;
    private PublicKey $oauthPublicKey;

    public function __construct(CaInterface $ca, TlsCrypt $tlsCrypt, string $wgPublicKey, int $wgPort, PublicKey $oauthPublicKey)
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
        // XXX we can return PublicKey here as well
        return $this->oauthPublicKey->export();
    }
}
