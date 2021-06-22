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
    private string $keyId;

    public function __construct(CaInterface $ca, string $publicKey, string $keyId)
    {
        $this->ca = $ca;
        $this->publicKey = $publicKey;
        $this->keyId = $keyId;
    }

    public function ca(): CaInterface
    {
        return $this->ca;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    public function keyId(): string
    {
        return $this->keyId;
    }
}
