<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\OpenVpn\CA\CaInterface;

class ServerInfo
{
    private CaInterface $ca;
    private string $wgPublicKey;
    private int $wgPort;
    private string $oauthPublicKey;

    public function __construct(CaInterface $ca, string $wgPublicKey, int $wgPort, string $oauthPublicKey)
    {
        $this->ca = $ca;
        $this->wgPublicKey = $wgPublicKey;
        $this->wgPort = $wgPort;
        $this->oauthPublicKey = $oauthPublicKey;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
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
