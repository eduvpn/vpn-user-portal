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
use LC\Portal\Expiry;
use PHPUnit\Framework\TestCase;

class ExpiryTest extends TestCase
{
    /**
     * @return void
     */
    public function testDoNotOutliveCa()
    {
        $dataSet = [
            // sessionExpiresAt, caExpiresAt, sessionExpiry, currentDate
            ['2025-01-01T03:00:00+02:00', '2025-01-01T01:00:00+00:00', 'P10Y', '2021-05-11T12:22:26+02:00'],  // sessionExpiry outlives CA
            ['2021-08-09T10:50:00+02:00', '2025-01-01T09:00:00+02:00', 'P90D', '2021-05-11T10:50:00+02:00'], // sessionExpiry does not outlive CA
        ];

        foreach ($dataSet as $dataPoint) {
            $dateTime = new DateTime($dataPoint[3]);
            $expiryInterval = Expiry::doNotOutliveCa(new DateTime($dataPoint[1]), new DateInterval($dataPoint[2]), $dateTime);
            $this->assertSame($dataPoint[0], $dateTime->add($expiryInterval)->format(DateTime::ATOM));
        }
    }
}
