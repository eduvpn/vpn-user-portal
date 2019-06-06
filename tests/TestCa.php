<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use DateInterval;
use DateTime;
use DateTimeInterface;
use LC\Portal\CA\CaInterface;
use LC\Portal\CA\CertInfo;

class TestCa implements CaInterface
{
    /** @var \DateTime */
    private $dateTime;

    /**
     * @param \DateTime $dateTime
     */
    public function __construct(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    public function caCert(): string
    {
        return '---CaCert---';
    }

    public function serverCert(string $commonName): CertInfo
    {
        return new CertInfo(
            sprintf('---ServerCert [%s]---', $commonName),
            '---ServerKey---',
            $this->dateTime,
            date_add(clone $this->dateTime, new DateInterval('P1Y'))
        );
    }

    public function clientCert(string $commonName, DateTimeInterface $expiresAt): CertInfo
    {
        return new CertInfo(
            sprintf('---ClientCert [%s,%s]---', $commonName, $expiresAt->format(DateTime::ATOM)),
            '---ClientKey---',
            $this->dateTime,
            $expiresAt
        );
    }
}
