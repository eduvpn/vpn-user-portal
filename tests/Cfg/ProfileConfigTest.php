<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Cfg\ProfileConfig;

/**
 * @internal
 *
 * @coversNothing
 */
final class ProfileConfigTest extends TestCase
{
    public function testDefault(): void
    {
        $p = new ProfileConfig(
            [
                'profileId' => 'default',
                'displayName' => 'Default',
                'hostName' => 'vpn.example',
                'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                'wRangeFour' => '10.43.43.0/24',
                'wRangeSix' => 'fd43::/64',
                'oRangeFour' => '10.42.42.0/24',
                'oRangeSix' => 'fd42::/64',
            ]
        );

        static::assertSame([0], $p->onNode());
    }

    public function testRangeSix(): void
    {
        $p = new ProfileConfig(
            [
                'nodeUrl' => ['http://a.vpn.example.org:41194', 'http://b.vpn.example.org:41194'],
                'wRangeSix' => ['10.0.0.0/24', '10.0.1.0/24'],
            ]
        );

        static::assertSame([0, 1], $p->onNode());
        static::assertSame('10.0.0.0/24', (string) $p->wRangeSix(0));
        static::assertSame('10.0.1.0/24', (string) $p->wRangeSix(1));
    }

    public function testRangeSixOnSomeNodes(): void
    {
        $p = new ProfileConfig(
            [
                'onNode' => [2, 3],
                'nodeUrl' => ['http://c.vpn.example.org:41194', 'http://d.vpn.example.org:41194'],
                'wRangeSix' => ['10.0.0.0/24', '10.0.1.0/24'],
            ]
        );

        static::assertSame([2, 3], $p->onNode());
        static::assertSame('http://c.vpn.example.org:41194', $p->nodeUrl(2));
        static::assertSame('http://d.vpn.example.org:41194', $p->nodeUrl(3));
        static::assertSame('10.0.0.0/24', (string) $p->wRangeSix(2));
        static::assertSame('10.0.1.0/24', (string) $p->wRangeSix(3));
    }
}
