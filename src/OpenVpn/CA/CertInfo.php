<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\OpenVpn\CA;

use DateTimeImmutable;
use Vpn\Portal\Dt;

class CertInfo
{
    private string $pemCert;
    private string $pemKey;
    private int $validFrom;
    private int $validTo;

    public function __construct(string $pemCert, string $pemKey, int $validFrom, int $validTo)
    {
        $this->pemCert = $pemCert;
        $this->pemKey = $pemKey;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function pemCert(): string
    {
        return $this->pemCert;
    }

    public function pemKey(): string
    {
        return $this->pemKey;
    }

    public function validFrom(): DateTimeImmutable
    {
        return Dt::get('@'.$this->validFrom);
    }

    public function validTo(): DateTimeImmutable
    {
        return Dt::get('@'.$this->validTo);
    }
}
