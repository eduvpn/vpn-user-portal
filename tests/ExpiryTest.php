<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use LC\Portal\Expiry;
use PHPUnit\Framework\TestCase;

class ExpiryTest extends TestCase
{
    public function testLong(): void
    {
        $dataSet = [
            // sessionExpiry, expiresAt, currentDate
            ['P90D',  '2021-07-05T04:00:00+02:00', '2021-04-06T09:00:00+02:00'],
            ['P7D',   '2021-04-13T04:00:00+02:00', '2021-04-06T09:00:00+02:00'],
            ['PT12H', '2021-04-06T21:00:00+02:00', '2021-04-06T09:00:00+02:00'],
            ['P3D',   '2021-04-09T09:00:00+02:00', '2021-04-06T09:00:00+02:00'],
            ['P7D',   '2021-04-12T04:00:00+02:00', '2021-04-06T00:00:00+02:00'],
            ['P7D',   '2021-04-12T04:00:00+02:00', '2021-04-06T01:00:00+02:00'],
            ['P7D',   '2021-04-12T04:00:00+02:00', '2021-04-06T02:00:00+02:00'],
            ['P7D',   '2021-04-12T04:00:00+02:00', '2021-04-06T03:00:00+02:00'],
            ['P7D',   '2021-04-13T04:00:00+02:00', '2021-04-06T04:00:00+02:00'],
            ['P7D',   '2021-04-13T04:00:00+02:00', '2021-04-06T05:00:00+02:00'],
        ];

        foreach ($dataSet as $dataPoint) {
            $dateTime = new DateTimeImmutable($dataPoint[2]);
            $expiryInterval = Expiry::calculate(new DateInterval($dataPoint[0]), $dateTime);
            $this->assertSame($dataPoint[1], $dateTime->add($expiryInterval)->format(DateTimeImmutable::ATOM));
        }
    }
}
