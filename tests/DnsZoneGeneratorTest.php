<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\DnsZoneGenerator;
use Vpn\Portal\Cfg\Config;

/**
 * @internal
 *
 * @coversNothing
 */
final class DnsZoneGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $p = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.43.43.0/28',
                        'wRangeSix' => 'fd43::/64',
                        'oRangeFour' => '10.42.42.0/28',
                        'oRangeSix' => 'fd42::/64',
                    ],
                ],
            ]
        );

        $this->assertSame(
            [
                'c-10-42-42-2' => ['10.42.42.2','fd42::2'],
                'c-10-42-42-3' => ['10.42.42.3','fd42::3'],
                'c-10-42-42-4' => ['10.42.42.4','fd42::4'],
                'c-10-42-42-5' => ['10.42.42.5','fd42::5'],
                'c-10-42-42-6' => ['10.42.42.6','fd42::6'],
                'c-10-42-42-10' => ['10.42.42.10','fd42::1:2'],
                'c-10-42-42-11' => ['10.42.42.11','fd42::1:3'],
                'c-10-42-42-12' => ['10.42.42.12','fd42::1:4'],
                'c-10-42-42-13' => ['10.42.42.13','fd42::1:5'],
                'c-10-42-42-14' => ['10.42.42.14','fd42::1:6'],
                'c-10-43-43-2' => ['10.43.43.2','fd43::2'],
                'c-10-43-43-3' => ['10.43.43.3','fd43::3'],
                'c-10-43-43-4' => ['10.43.43.4','fd43::4'],
                'c-10-43-43-5' => ['10.43.43.5','fd43::5'],
                'c-10-43-43-6' => ['10.43.43.6','fd43::6'],
                'c-10-43-43-7' => ['10.43.43.7','fd43::7'],
                'c-10-43-43-8' => ['10.43.43.8','fd43::8'],
                'c-10-43-43-9' => ['10.43.43.9','fd43::9'],
                'c-10-43-43-10' => ['10.43.43.10','fd43::a'],
                'c-10-43-43-11' => ['10.43.43.11','fd43::b'],
                'c-10-43-43-12' => ['10.43.43.12','fd43::c'],
                'c-10-43-43-13' => ['10.43.43.13','fd43::d'],
                'c-10-43-43-14' => ['10.43.43.14','fd43::e'],
            ],
            DnsZoneGenerator::generateMapping($p)
        );
    }

    public function testForwardDns(): void
    {
        $p = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.43.43.0/28',
                        'wRangeSix' => 'fd43::/64',
                        'oRangeFour' => '10.42.42.0/28',
                        'oRangeSix' => 'fd42::/64',
                    ],
                ],
            ]
        );

        $result = <<< 'EOF'
            $ORIGIN vpn.example.org.
            c-10-42-42-2         IN A    10.42.42.2
                                 IN AAAA fd42::2
            c-10-42-42-3         IN A    10.42.42.3
                                 IN AAAA fd42::3
            c-10-42-42-4         IN A    10.42.42.4
                                 IN AAAA fd42::4
            c-10-42-42-5         IN A    10.42.42.5
                                 IN AAAA fd42::5
            c-10-42-42-6         IN A    10.42.42.6
                                 IN AAAA fd42::6
            c-10-42-42-10        IN A    10.42.42.10
                                 IN AAAA fd42::1:2
            c-10-42-42-11        IN A    10.42.42.11
                                 IN AAAA fd42::1:3
            c-10-42-42-12        IN A    10.42.42.12
                                 IN AAAA fd42::1:4
            c-10-42-42-13        IN A    10.42.42.13
                                 IN AAAA fd42::1:5
            c-10-42-42-14        IN A    10.42.42.14
                                 IN AAAA fd42::1:6
            c-10-43-43-2         IN A    10.43.43.2
                                 IN AAAA fd43::2
            c-10-43-43-3         IN A    10.43.43.3
                                 IN AAAA fd43::3
            c-10-43-43-4         IN A    10.43.43.4
                                 IN AAAA fd43::4
            c-10-43-43-5         IN A    10.43.43.5
                                 IN AAAA fd43::5
            c-10-43-43-6         IN A    10.43.43.6
                                 IN AAAA fd43::6
            c-10-43-43-7         IN A    10.43.43.7
                                 IN AAAA fd43::7
            c-10-43-43-8         IN A    10.43.43.8
                                 IN AAAA fd43::8
            c-10-43-43-9         IN A    10.43.43.9
                                 IN AAAA fd43::9
            c-10-43-43-10        IN A    10.43.43.10
                                 IN AAAA fd43::a
            c-10-43-43-11        IN A    10.43.43.11
                                 IN AAAA fd43::b
            c-10-43-43-12        IN A    10.43.43.12
                                 IN AAAA fd43::c
            c-10-43-43-13        IN A    10.43.43.13
                                 IN AAAA fd43::d
            c-10-43-43-14        IN A    10.43.43.14
                                 IN AAAA fd43::e

            EOF;

        $this->assertSame($result, DnsZoneGenerator::forwardDns('vpn.example.org', $p, DnsZoneGenerator::IP_BOTH));
    }

    public function testMultiNode(): void
    {
        $p = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => ['n2.vpn.example.org', 'n3.vpn.example.org'],
                        'wRangeFour' => ['10.61.60.0/29', '10.7.192.0/29'],
                        'wRangeSix' => ['fd85:f1d9:20b7:b74c::/64', 'fd89:79cb:b63c:717e::/64'],
                        'dnsServerList' => ['9.9.9.9', '2620:fe::9'],
                        'nodeUrl' => ['https://n2.vpn.example.org:41194', 'https://n3.vpn.example.org:41194'],
                        'onNode' => [1, 2],
                    ],
                ],
            ]
        );

        $this->assertSame(
            [
                'c-10-61-60-2' => ['10.61.60.2','fd85:f1d9:20b7:b74c::2'],
                'c-10-61-60-3' => ['10.61.60.3','fd85:f1d9:20b7:b74c::3'],
                'c-10-61-60-4' => ['10.61.60.4','fd85:f1d9:20b7:b74c::4'],
                'c-10-61-60-5' => ['10.61.60.5','fd85:f1d9:20b7:b74c::5'],
                'c-10-61-60-6' => ['10.61.60.6','fd85:f1d9:20b7:b74c::6'],
                'c-10-7-192-2' => ['10.7.192.2','fd89:79cb:b63c:717e::2'],
                'c-10-7-192-3' => ['10.7.192.3','fd89:79cb:b63c:717e::3'],
                'c-10-7-192-4' => ['10.7.192.4','fd89:79cb:b63c:717e::4'],
                'c-10-7-192-5' => ['10.7.192.5','fd89:79cb:b63c:717e::5'],
                'c-10-7-192-6' => ['10.7.192.6','fd89:79cb:b63c:717e::6'],
            ],
            DnsZoneGenerator::generateMapping($p)
        );
    }

    public function testReverseDns(): void
    {
        $p = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'default',
                        'displayName' => 'Default',
                        'hostName' => 'vpn.example',
                        'dnsServerList' => ['9.9.9.9', '2620:fe::fe'],
                        'wRangeFour' => '10.43.43.0/28',
                        'wRangeSix' => 'fd43::/64',
                        'oRangeFour' => '10.42.42.0/28',
                        'oRangeSix' => 'fd42::/64',
                    ],
                ],
            ]
        );

        $result = <<< 'EOF'
            $ORIGIN 42.42.10.in-addr.arpa.
            2        IN PTR c-10-42-42-2.vpn.example.org.
            3        IN PTR c-10-42-42-3.vpn.example.org.
            4        IN PTR c-10-42-42-4.vpn.example.org.
            5        IN PTR c-10-42-42-5.vpn.example.org.
            6        IN PTR c-10-42-42-6.vpn.example.org.
            10       IN PTR c-10-42-42-10.vpn.example.org.
            11       IN PTR c-10-42-42-11.vpn.example.org.
            12       IN PTR c-10-42-42-12.vpn.example.org.
            13       IN PTR c-10-42-42-13.vpn.example.org.
            14       IN PTR c-10-42-42-14.vpn.example.org.
            $ORIGIN 43.43.10.in-addr.arpa.
            2        IN PTR c-10-43-43-2.vpn.example.org.
            3        IN PTR c-10-43-43-3.vpn.example.org.
            4        IN PTR c-10-43-43-4.vpn.example.org.
            5        IN PTR c-10-43-43-5.vpn.example.org.
            6        IN PTR c-10-43-43-6.vpn.example.org.
            7        IN PTR c-10-43-43-7.vpn.example.org.
            8        IN PTR c-10-43-43-8.vpn.example.org.
            9        IN PTR c-10-43-43-9.vpn.example.org.
            10       IN PTR c-10-43-43-10.vpn.example.org.
            11       IN PTR c-10-43-43-11.vpn.example.org.
            12       IN PTR c-10-43-43-12.vpn.example.org.
            13       IN PTR c-10-43-43-13.vpn.example.org.
            14       IN PTR c-10-43-43-14.vpn.example.org.
            $ORIGIN 0.0.0.0.0.0.0.0.0.0.0.0.2.4.d.f.ip6.arpa.
            2.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-2.vpn.example.org.
            3.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-3.vpn.example.org.
            4.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-4.vpn.example.org.
            5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-5.vpn.example.org.
            6.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-6.vpn.example.org.
            2.0.0.0.1.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-10.vpn.example.org.
            3.0.0.0.1.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-11.vpn.example.org.
            4.0.0.0.1.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-12.vpn.example.org.
            5.0.0.0.1.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-13.vpn.example.org.
            6.0.0.0.1.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-42-42-14.vpn.example.org.
            $ORIGIN 0.0.0.0.0.0.0.0.0.0.0.0.3.4.d.f.ip6.arpa.
            2.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-2.vpn.example.org.
            3.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-3.vpn.example.org.
            4.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-4.vpn.example.org.
            5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-5.vpn.example.org.
            6.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-6.vpn.example.org.
            7.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-7.vpn.example.org.
            8.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-8.vpn.example.org.
            9.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-9.vpn.example.org.
            a.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-10.vpn.example.org.
            b.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-11.vpn.example.org.
            c.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-12.vpn.example.org.
            d.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-13.vpn.example.org.
            e.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0 IN PTR c-10-43-43-14.vpn.example.org.

            EOF;

        $this->assertSame($result, DnsZoneGenerator::reverseDns('vpn.example.org', $p, DnsZoneGenerator::IP_BOTH));
    }
}
