<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OpenVpn\CA;

class CertInfo
{
    private string $pemCert;
    private string $pemKey;

    public function __construct(string $pemCert, string $pemKey)
    {
        $this->pemCert = $pemCert;
        $this->pemKey = $pemKey;
    }

    public function pemCert(): string
    {
        return trim($this->pemCert);
    }

    public function pemKey(): string
    {
        return trim($this->pemKey);
    }
}
