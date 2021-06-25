<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

use LC\Portal\CA\CaInterface;

class ServerInfo
{
    private CaInterface $ca;
    private string $wgPublicKey;

    public function __construct(CaInterface $ca, string $wgPublicKey)
    {
        $this->ca = $ca;
        $this->wgPublicKey = $wgPublicKey;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
    }

    public function wgPublicKey(): string
    {
        return $this->wgPublicKey;
    }
}
