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

class CaInfo
{
    private string $pemCert;
    private int $validFrom;
    private int $validTo;

    public function __construct(string $pemCert, int $validFrom, int $validTo)
    {
        $this->pemCert = $pemCert;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function pemCert(): string
    {
        return $this->pemCert;
    }

    public function validFrom(): DateTimeImmutable
    {
        return Dt::get('@'.$this->validFrom);
    }

    public function validTo(): DateTimeImmutable
    {
        return Dt::get('@'.$this->validTo);
    }

    public function fingerprint(): string
    {
        return openssl_x509_fingerprint($this->pemCert, 'sha256');
    }
}
