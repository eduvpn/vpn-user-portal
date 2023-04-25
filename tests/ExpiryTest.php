<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Vpn\Portal\Dt;
use Vpn\Portal\Expiry;
use Vpn\Portal\Http\UserInfo;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExpiryTest extends TestCase
{
    public function testDefaultSessionExpiry(): void
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
            $e = new Expiry(new DateInterval($dataPoint[3]), [], $dateTime, Dt::get($dataPoint[2]));
            static::assertSame($dataPoint[0], $e->expiresAt()->format(DateTimeImmutable::ATOM));
            static::assertSame($dataPoint[0], $dateTime->add($e->expiresIn())->format(DateTimeImmutable::ATOM));
        }
    }

    public function testUserInfoSessionExpiry(): void
    {
        $dataSet = [
            // expectedExpiry             dateTime                     caExpiresAt                  sessionExpiry
            ['2022-04-06T09:00:00+00:00', '2021-04-06T09:00:00+00:00', '2030-01-01T09:00:00+00:00', 'P90D'],
            ['2022-04-06T07:00:00+00:00', '2021-04-06T09:00:00+02:00', '2030-01-01T09:00:00+00:00', 'P90D'],
            ['2021-05-06T09:00:00+00:00', '2021-04-06T09:00:00+02:00', '2021-05-06T09:00:00+00:00', 'P90D'],
            ['2021-05-06T07:00:00+00:00', '2021-04-06T09:00:00+02:00', '2021-05-06T09:00:00+02:00', 'P90D'],
        ];

        $userInfo = new UserInfo('foo', ['https://eduvpn.org/expiry#P1Y']);

        foreach ($dataSet as $dataPoint) {
            $dateTime = Dt::get($dataPoint[1]);
            $e = new Expiry(new DateInterval($dataPoint[3]), ['P1Y'], $dateTime, Dt::get($dataPoint[2]));
            static::assertSame($dataPoint[0], $e->expiresAt($userInfo)->format(DateTimeImmutable::ATOM));
            static::assertSame($dataPoint[0], $dateTime->add($e->expiresIn($userInfo))->format(DateTimeImmutable::ATOM));
        }
    }
}
