<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTimeImmutable;

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
        return new DateTimeImmutable('@'.$this->validFrom);
    }

    public function validTo(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->validTo);
    }
}
