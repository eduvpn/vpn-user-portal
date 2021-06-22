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
    private string $publicKey;

    public function __construct(CaInterface $ca, string $publicKey)
    {
        $this->ca = $ca;
        $this->publicKey = $publicKey;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }
}
