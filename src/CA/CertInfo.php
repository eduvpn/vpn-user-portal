<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\CA;

use DateTimeInterface;

class CertInfo
{
    /** @var string */
    private $certData;

    /** @var string */
    private $keyData;

    /** @var \DateTimeInterface */
    private $validFrom;

    /** @var \DateTimeInterface */
    private $validTo;

    public function __construct(string $certData, string $keyData, DateTimeInterface $validFrom, DateTimeInterface $validTo)
    {
        $this->certData = $certData;
        $this->keyData = $keyData;
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function getCertData(): string
    {
        return $this->certData;
    }

    public function getKeyData(): string
    {
        return $this->keyData;
    }

    public function getValidFrom(): DateTimeInterface
    {
        return $this->validFrom;
    }

    public function getValidTo(): DateTimeInterface
    {
        return $this->validTo;
    }
}
