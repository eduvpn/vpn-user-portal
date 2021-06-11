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
use LC\Portal\Dt;
use LC\Portal\Expiry;
use PHPUnit\Framework\TestCase;

class ExpiryTest extends TestCase
{
    public function testLong(): void
    {
        $dataSet = [
            // sessionExpiry, expiresAt, caExpiresAt, currentDate
            ['P90D',  '2021-07-05T02:00:00+00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
//            ['P90D',  '2021-07-05T04:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],

//            ['P7D',   '2021-04-13T04:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
//            ['PT12H', '2021-04-06T21:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
////            ['P1D',   '2021-04-07T04:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
////            ['PT24H', '2021-04-07T04:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
////            ['P3D',   '2021-04-09T04:00:00', '2030-01-01T09:00:00', '2021-04-06T09:00:00'],
//            ['P7D',   '2021-04-12T04:00:00', '2030-01-01T09:00:00', '2021-04-06T00:00:00'],
////            ['P7D',   '2021-04-12T04:00:00', '2030-01-01T09:00:00', '2021-04-06T01:00:00'],
////            ['P7D',   '2021-04-12T04:00:00', '2030-01-01T09:00:00', '2021-04-06T02:00:00'],
////            ['P7D',   '2021-04-12T04:00:00', '2030-01-01T09:00:00', '2021-04-06T03:00:00'],
////            ['P7D',   '2021-04-13T04:00:00', '2030-01-01T09:00:00', '2021-04-06T04:00:00'],
////            ['P7D',   '2021-04-13T04:00:00', '2030-01-01T09:00:00', '2021-04-06T05:00:00'],
//            // PT12H, outlives CA
//            ['PT12H', '2021-04-05T20:59:59', '2021-04-05T20:59:59', '2021-04-06T09:00:00'],
//            // P90D, outlives CA
//            ['P90D',  '2021-07-01T09:00:00', '2021-07-01T09:00:00', '2021-04-06T09:00:00'],
//            ['P90D',  '2021-07-01T09:00:00', '2021-07-01T09:00:00', '2021-04-06T09:00:00'],
        ];

//        date_default_timezone_set('Europe/Amsterdam');

        foreach ($dataSet as $dataPoint) {
            $dateTime = Dt::get($dataPoint[3]);
            $expiryInterval = Expiry::calculate(new DateInterval($dataPoint[0]), Dt::get($dataPoint[2]), $dateTime);
            $this->assertSame($dataPoint[1], $dateTime->add($expiryInterval)->format(DateTimeImmutable::ATOM));
        }
    }
}
