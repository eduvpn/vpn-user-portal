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
use LC\Portal\CA\CaInterface;

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

    /**
     * @return string
     */
    public function caCert()
    {
        return '---CaCert---';
    }

    /**
     * @param string $commonName
     *
     * @return array{cert:string, key:string, valid_from:int, valid_to:int}
     */
    public function serverCert($commonName)
    {
        return [
            'cert' => sprintf('---ServerCert [%s]---', $commonName),
            'key' => '---ServerKey---',
            'valid_from' => $this->dateTime->getTimestamp(),
            'valid_to' => date_add(clone $this->dateTime, new DateInterval('P1Y')),
        ];
    }

    /**
     * @param string    $commonName
     * @param \DateTime $expiresAt
     *
     * @return array{cert:string, key:string, valid_from:int, valid_to:int}
     */
    public function clientCert($commonName, DateTime $expiresAt)
    {
        return [
            'cert' => sprintf('---ClientCert [%s,%s]---', $commonName, $expiresAt->format(DateTime::ATOM)),
            'key' => '---ClientKey---',
            'valid_from' => $this->dateTime->getTimestamp(),
            'valid_to' => $expiresAt->getTimestamp(),
        ];
    }
}
