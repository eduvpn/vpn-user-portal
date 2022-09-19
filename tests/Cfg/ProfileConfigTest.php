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

    public function testOListenOn(): void
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
        static::assertSame('::', $p->oListenOn(0)->address());

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
                'oListenOn' => '10.0.99.99',
            ]
        );
        static::assertSame('10.0.99.99', $p->oListenOn(0)->address());

        $p = new ProfileConfig(
            [
                'profileId' => 'default',
                'displayName' => 'Default',
                'hostName' => ['vpn1.example', 'vpn2.example'],
                'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                'wRangeFour' => ['10.43.43.0/24', '10.44.44.0/24'],
                'wRangeSix' => ['fd43::/64', 'fd44::/64'],
                'oRangeFour' => ['10.42.42.0/24', '10.45.45.0/24'],
                'oRangeSix' => ['fd42::/64', 'fd45::/64'],
                'oListenOn' => ['10.0.99.99', '10.0.99.100'],
                'nodeUrl' => ['http://node1.example', 'http://node2.example'],
            ]
        );
        static::assertSame('10.0.99.99', $p->oListenOn(0)->address());
        static::assertSame('10.0.99.100', $p->oListenOn(1)->address());

        $p = new ProfileConfig(
            [
                'profileId' => 'default',
                'displayName' => 'Default',
                'hostName' => ['vpn1.example', 'vpn2.example'],
                'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                'wRangeFour' => ['10.43.43.0/24', '10.44.44.0/24'],
                'wRangeSix' => ['fd43::/64', 'fd44::/64'],
                'oRangeFour' => ['10.42.42.0/24', '10.45.45.0/24'],
                'oRangeSix' => ['fd42::/64', 'fd45::/64'],
                'nodeUrl' => ['http://node1.example', 'http://node2.example'],
            ]
        );
        static::assertSame('::', $p->oListenOn(0)->address());
        static::assertSame('::', $p->oListenOn(1)->address());
    }
}
