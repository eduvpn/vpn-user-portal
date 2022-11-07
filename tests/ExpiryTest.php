<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExpiryTest extends TestCase
{
    public function testLong(): void
    {
        $dataSet = [
            // expectedExpiry             dateTime                     caExpiresAt                  sessionExpiry
            ['2021-07-05T09:00:00+00:00', '2021-04-06T09:00:00+00:00', '2030-01-01T09:00:00+00:00', 'P90D'],
            ['2021-07-05T07:00:00+00:00', '2021-04-06T09:00:00+02:00', '2030-01-01T09:00:00+00:00', 'P90D'],
            ['2021-05-06T09:00:00+00:00', '2021-04-06T09:00:00+02:00', '2021-05-06T09:00:00+00:00', 'P90D'],
            ['2021-05-06T07:00:00+00:00', '2021-04-06T09:00:00+02:00', '2021-05-06T09:00:00+02:00', 'P90D'],
        ];

        foreach ($dataSet as $dataPoint) {
            $dateTime = Dt::get($dataPoint[1]);
            $expiryInterval = Expiry::calculate($dateTime, Dt::get($dataPoint[2]), new DateInterval($dataPoint[3]));
            static::assertSame($dataPoint[0], $dateTime->add($expiryInterval)->format(DateTimeImmutable::ATOM));
        }
    }
}
