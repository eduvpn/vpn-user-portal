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
use Vpn\Portal\Exception\IpException;
use Vpn\Portal\Ip;

/**
 * @internal
 * @coversNothing
 */
final class IpTest extends TestCase
{
    public function testIPv4One(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.0/24');
        $splitRange = $ip->split(1);
        static::assertCount(1, $splitRange);
        static::assertSame('192.168.1.0/24', (string) $splitRange[0]);
    }

    public function testNetmask(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.0/24');
        static::assertSame('255.255.255.0', $ip->netmask());
        $ip = Ip::fromIpPrefix('10.0.0.0/8');
        static::assertSame('255.0.0.0', $ip->netmask());
        $ip = Ip::fromIpPrefix('10.0.0.128/25');
        static::assertSame('255.255.255.128', $ip->netmask());
        // it makes no sense for IPv6, but still fun :-P
        $ip = Ip::fromIpPrefix('fd00::/64');
        static::assertSame('ffff:ffff:ffff:ffff::', $ip->netmask());
    }

    public function testNetwork(): void
    {
        $ip = Ip::fromIpPrefix('8.8.8.8/0');
        static::assertSame('0.0.0.0/0', (string) $ip->network());

        $ip = Ip::fromIpPrefix('192.168.1.5/24');
        static::assertSame('192.168.1.0/24', (string) $ip->network());

        $ip = Ip::fromIpPrefix('192.168.1.5/32');
        static::assertSame('192.168.1.5/32', (string) $ip->network());

        $ip = Ip::fromIpPrefix('fd00::1:2:3:4/64');
        static::assertSame('fd00::/64', (string) $ip->network());

        $ip = Ip::fromIpPrefix('fd00::1234:1:2:3:4/64');
        static::assertSame('fd00:0:0:1234::/64', (string) $ip->network());

        $ip = Ip::fromIpPrefix('fd00::1234:1:2:3:4/128');
        static::assertSame('fd00::1234:1:2:3:4/128', (string) $ip->network());

        $ip = Ip::fromIpPrefix('fd00::1234:1:2:3:ffff/124');
        static::assertSame('fd00::1234:1:2:3:fff0/124', (string) $ip->network());

        $ip = Ip::fromIpPrefix('fd00::1234:1:2:3:ffff/127');
        static::assertSame('fd00::1234:1:2:3:fffe/127', (string) $ip->network());

        $ip = Ip::fromIpPrefix('2001:1234:1234:1234:1234:1234:1234:1234/0');
        static::assertSame('::/0', (string) $ip->network());
    }

    public function testIPv4Two(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.0/24');
        $splitRange = $ip->split(2);
        static::assertCount(2, $splitRange);
        static::assertSame('192.168.1.0/25', (string) $splitRange[0]);
        static::assertSame('192.168.1.128/25', (string) $splitRange[1]);

        $ip = Ip::fromIpPrefix('0.0.0.0/0');
        $splitRange = $ip->split(2);
        static::assertCount(2, $splitRange);
        static::assertSame('0.0.0.0/1', (string) $splitRange[0]);
        static::assertSame('128.0.0.0/1', (string) $splitRange[1]);
    }

    public function testIPv4Four(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.0/24');
        $splitRange = $ip->split(4);
        static::assertCount(4, $splitRange);
        static::assertSame('192.168.1.0/26', (string) $splitRange[0]);
        static::assertSame('192.168.1.64/26', (string) $splitRange[1]);
        static::assertSame('192.168.1.128/26', (string) $splitRange[2]);
        static::assertSame('192.168.1.192/26', (string) $splitRange[3]);
    }

    public function testIPv4ThirtyTwo(): void
    {
        $ip = Ip::fromIpPrefix('10.0.0.0/8');
        $splitRange = $ip->split(32);
        static::assertCount(32, $splitRange);
        static::assertSame('10.0.0.0/13', (string) $splitRange[0]);
        static::assertSame('10.8.0.0/13', (string) $splitRange[1]);
        static::assertSame('10.16.0.0/13', (string) $splitRange[2]);
        static::assertSame('10.24.0.0/13', (string) $splitRange[3]);
        static::assertSame('10.32.0.0/13', (string) $splitRange[4]);
        static::assertSame('10.40.0.0/13', (string) $splitRange[5]);
        static::assertSame('10.48.0.0/13', (string) $splitRange[6]);
        static::assertSame('10.56.0.0/13', (string) $splitRange[7]);
        static::assertSame('10.64.0.0/13', (string) $splitRange[8]);
        static::assertSame('10.72.0.0/13', (string) $splitRange[9]);
        static::assertSame('10.80.0.0/13', (string) $splitRange[10]);
        static::assertSame('10.88.0.0/13', (string) $splitRange[11]);
        static::assertSame('10.96.0.0/13', (string) $splitRange[12]);
        static::assertSame('10.104.0.0/13', (string) $splitRange[13]);
        static::assertSame('10.112.0.0/13', (string) $splitRange[14]);
        static::assertSame('10.120.0.0/13', (string) $splitRange[15]);
        static::assertSame('10.128.0.0/13', (string) $splitRange[16]);
        static::assertSame('10.136.0.0/13', (string) $splitRange[17]);
        static::assertSame('10.144.0.0/13', (string) $splitRange[18]);
        static::assertSame('10.152.0.0/13', (string) $splitRange[19]);
        static::assertSame('10.160.0.0/13', (string) $splitRange[20]);
        static::assertSame('10.168.0.0/13', (string) $splitRange[21]);
        static::assertSame('10.176.0.0/13', (string) $splitRange[22]);
        static::assertSame('10.184.0.0/13', (string) $splitRange[23]);
        static::assertSame('10.192.0.0/13', (string) $splitRange[24]);
        static::assertSame('10.200.0.0/13', (string) $splitRange[25]);
        static::assertSame('10.208.0.0/13', (string) $splitRange[26]);
        static::assertSame('10.216.0.0/13', (string) $splitRange[27]);
        static::assertSame('10.224.0.0/13', (string) $splitRange[28]);
        static::assertSame('10.232.0.0/13', (string) $splitRange[29]);
        static::assertSame('10.240.0.0/13', (string) $splitRange[30]);
        static::assertSame('10.248.0.0/13', (string) $splitRange[31]);
    }

    public function testIPv6One(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/64');
        $splitRange = $ip->split(1);
        static::assertCount(1, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
    }

    public function testIPv6OneWithMinSpace(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/112');
        $splitRange = $ip->split(1);
        static::assertCount(1, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
    }

    public function testIPv6Two(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/64');
        $splitRange = $ip->split(2);
        static::assertCount(2, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
        static::assertSame('1111:2222:3333:4444::1:0/112', (string) $splitRange[1]);
    }

    public function testIPv6Four(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/64');
        $splitRange = $ip->split(4);
        static::assertCount(4, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
        static::assertSame('1111:2222:3333:4444::1:0/112', (string) $splitRange[1]);
        static::assertSame('1111:2222:3333:4444::2:0/112', (string) $splitRange[2]);
        static::assertSame('1111:2222:3333:4444::3:0/112', (string) $splitRange[3]);
    }

    public function testIPv6ThirtyTwo(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/64');
        $splitRange = $ip->split(32);
        static::assertCount(32, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
        static::assertSame('1111:2222:3333:4444::1:0/112', (string) $splitRange[1]);
        static::assertSame('1111:2222:3333:4444::2:0/112', (string) $splitRange[2]);
        static::assertSame('1111:2222:3333:4444::3:0/112', (string) $splitRange[3]);
        static::assertSame('1111:2222:3333:4444::4:0/112', (string) $splitRange[4]);
        static::assertSame('1111:2222:3333:4444::5:0/112', (string) $splitRange[5]);
        static::assertSame('1111:2222:3333:4444::6:0/112', (string) $splitRange[6]);
        static::assertSame('1111:2222:3333:4444::7:0/112', (string) $splitRange[7]);
        static::assertSame('1111:2222:3333:4444::8:0/112', (string) $splitRange[8]);
        static::assertSame('1111:2222:3333:4444::9:0/112', (string) $splitRange[9]);
        static::assertSame('1111:2222:3333:4444::a:0/112', (string) $splitRange[10]);
        static::assertSame('1111:2222:3333:4444::b:0/112', (string) $splitRange[11]);
        static::assertSame('1111:2222:3333:4444::c:0/112', (string) $splitRange[12]);
        static::assertSame('1111:2222:3333:4444::d:0/112', (string) $splitRange[13]);
        static::assertSame('1111:2222:3333:4444::e:0/112', (string) $splitRange[14]);
        static::assertSame('1111:2222:3333:4444::f:0/112', (string) $splitRange[15]);
        static::assertSame('1111:2222:3333:4444::10:0/112', (string) $splitRange[16]);
        static::assertSame('1111:2222:3333:4444::11:0/112', (string) $splitRange[17]);
        static::assertSame('1111:2222:3333:4444::12:0/112', (string) $splitRange[18]);
        static::assertSame('1111:2222:3333:4444::13:0/112', (string) $splitRange[19]);
        static::assertSame('1111:2222:3333:4444::14:0/112', (string) $splitRange[20]);
        static::assertSame('1111:2222:3333:4444::15:0/112', (string) $splitRange[21]);
        static::assertSame('1111:2222:3333:4444::16:0/112', (string) $splitRange[22]);
        static::assertSame('1111:2222:3333:4444::17:0/112', (string) $splitRange[23]);
        static::assertSame('1111:2222:3333:4444::18:0/112', (string) $splitRange[24]);
        static::assertSame('1111:2222:3333:4444::19:0/112', (string) $splitRange[25]);
        static::assertSame('1111:2222:3333:4444::1a:0/112', (string) $splitRange[26]);
        static::assertSame('1111:2222:3333:4444::1b:0/112', (string) $splitRange[27]);
        static::assertSame('1111:2222:3333:4444::1c:0/112', (string) $splitRange[28]);
        static::assertSame('1111:2222:3333:4444::1d:0/112', (string) $splitRange[29]);
        static::assertSame('1111:2222:3333:4444::1e:0/112', (string) $splitRange[30]);
        static::assertSame('1111:2222:3333:4444::1f:0/112', (string) $splitRange[31]);
    }

    public function testGetFirstHost(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.0/24');
        $splitRange = $ip->split(4);
        static::assertCount(4, $splitRange);
        static::assertSame('192.168.1.0/26', (string) $splitRange[0]);
        static::assertSame('192.168.1.1', $splitRange[0]->firstHost());
        static::assertSame('192.168.1.64/26', (string) $splitRange[1]);
        static::assertSame('192.168.1.65', $splitRange[1]->firstHost());
        static::assertSame('192.168.1.128/26', (string) $splitRange[2]);
        static::assertSame('192.168.1.129', $splitRange[2]->firstHost());
        static::assertSame('192.168.1.192/26', (string) $splitRange[3]);
        static::assertSame('192.168.1.193', $splitRange[3]->firstHost());

        $ip = Ip::fromIpPrefix('192.168.1.5/24');
        static::assertSame('192.168.1.1', $ip->firstHost());
    }

    public function testGetFirstHost6(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::/64');
        $splitRange = $ip->split(4);
        static::assertCount(4, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
        static::assertSame('1111:2222:3333:4444::1', $splitRange[0]->firstHost());
        static::assertSame('1111:2222:3333:4444::1:0/112', (string) $splitRange[1]);
        static::assertSame('1111:2222:3333:4444::1:1', $splitRange[1]->firstHost());
        static::assertSame('1111:2222:3333:4444::2:0/112', (string) $splitRange[2]);
        static::assertSame('1111:2222:3333:4444::2:1', $splitRange[2]->firstHost());
        static::assertSame('1111:2222:3333:4444::3:0/112', (string) $splitRange[3]);
        static::assertSame('1111:2222:3333:4444::3:1', $splitRange[3]->firstHost());

        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::5/64');
        static::assertSame('1111:2222:3333:4444::1', $ip->firstHost());
    }

    public function testIPv4NonFirstTwo(): void
    {
        $ip = Ip::fromIpPrefix('192.168.1.128/24');
        $splitRange = $ip->split(2);
        static::assertCount(2, $splitRange);
        static::assertSame('192.168.1.0/25', (string) $splitRange[0]);
        static::assertSame('192.168.1.128/25', (string) $splitRange[1]);
    }

    public function testIPv6NonFirstTwo(): void
    {
        $ip = Ip::fromIpPrefix('1111:2222:3333:4444::ffff/64');
        $splitRange = $ip->split(2);
        static::assertCount(2, $splitRange);
        static::assertSame('1111:2222:3333:4444::/112', (string) $splitRange[0]);
        static::assertSame('1111:2222:3333:4444::1:0/112', (string) $splitRange[1]);
    }

    public function testHostIpListFour(): void
    {
        $ip = Ip::fromIpPrefix('10.42.42.0/29');
        $hostIpList = $ip->clientIpListFour();
        static::assertCount(5, $hostIpList);
        static::assertSame(
            [
                '10.42.42.2',
                '10.42.42.3',
                '10.42.42.4',
                '10.42.42.5',
                '10.42.42.6',
            ],
            $hostIpList
        );

        $ip = Ip::fromIpPrefix('10.42.42.0/30');
        $hostIpList = $ip->clientIpListFour();
        static::assertCount(1, $hostIpList);
        static::assertSame(
            [
                '10.42.42.2',
            ],
            $hostIpList
        );
    }

    public function testHostIpListFourNonNull(): void
    {
        $ip = Ip::fromIpPrefix('10.42.42.8/29');
        $hostIpList = $ip->clientIpListFour();
        static::assertCount(5, $hostIpList);
        static::assertSame(
            [
                '10.42.42.10',
                '10.42.42.11',
                '10.42.42.12',
                '10.42.42.13',
                '10.42.42.14',
            ],
            $hostIpList
        );
    }

    public function testHostIpListSix(): void
    {
        $ip = Ip::fromIpPrefix('fd42::/64');
        $hostIpList = $ip->clientIpListSix(5);
        static::assertCount(5, $hostIpList);
        static::assertSame(
            [
                'fd42::2',
                'fd42::3',
                'fd42::4',
                'fd42::5',
                'fd42::6',
            ],
            $hostIpList
        );

        $ip = Ip::fromIpPrefix('fd42::/126');
        $hostIpList = $ip->clientIpListSix(1);
        static::assertCount(1, $hostIpList);
        static::assertSame(
            [
                'fd42::2',
            ],
            $hostIpList
        );
    }

    public function testHostIpListSixOverLimit(): void
    {
        static::expectException(IpException::class);
        static::expectExceptionMessage('prefix "/124" does not contain "20" hosts');
        Ip::fromIpPrefix('fd42::/124')->clientIpListSix(20);
    }

    public function testContainsTrue(): void
    {
        static::assertTrue(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.5.5/32')));
        static::assertTrue(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.5.0/24')));
        static::assertTrue(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.5.0/25')));
        static::assertTrue(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.5.0/24')));
        static::assertTrue(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.5.128/25')));
        static::assertTrue(Ip::fromIpPrefix('192.168.5.5/32')->contains(Ip::fromIpPrefix('192.168.5.5/32')));
        static::assertTrue(Ip::fromIpPrefix('0.0.0.0/0')->contains(Ip::fromIpPrefix('192.168.5.5/32')));
        static::assertTrue(Ip::fromIpPrefix('192.168.0.0/16')->contains(Ip::fromIpPrefix('192.168.5.0/24')));
        static::assertTrue(Ip::fromIpPrefix('::/0')->contains(Ip::fromIpPrefix('fd42::/64')));
        static::assertTrue(Ip::fromIpPrefix('fd42::/64')->contains(Ip::fromIpPrefix('fd42::/64')));
        static::assertTrue(Ip::fromIpPrefix('fd42::/32')->contains(Ip::fromIpPrefix('fd42::/64')));
    }

    public function testContainsFalse(): void
    {
        static::assertFalse(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.6.5/32')));
        static::assertFalse(Ip::fromIpPrefix('192.168.5.5/32')->contains(Ip::fromIpPrefix('192.168.5.0/24')));
        static::assertFalse(Ip::fromIpPrefix('192.168.5.0/24')->contains(Ip::fromIpPrefix('192.168.4.0/22')));
        static::assertFalse(Ip::fromIpPrefix('fd42::/64')->contains(Ip::fromIpPrefix('fd43::/64')));
    }

    public function testSplitInHalfIpFour(): void
    {
        $splitIp = Ip::fromIpPrefix('0.0.0.0/0')->splitInHalf();
        static::assertCount(2, $splitIp);
        static::assertSame('0.0.0.0/1', (string) $splitIp[0]);
        static::assertSame('128.0.0.0/1', (string) $splitIp[1]);

        $splitIp = Ip::fromIpPrefix('192.168.5.5/24')->splitInHalf();
        static::assertCount(2, $splitIp);
        static::assertSame('192.168.5.0/25', (string) $splitIp[0]);
        static::assertSame('192.168.5.128/25', (string) $splitIp[1]);

        $splitIp = Ip::fromIpPrefix('192.168.5.0/31')->splitInHalf();
        static::assertCount(2, $splitIp);
        static::assertSame('192.168.5.0/32', (string) $splitIp[0]);
        static::assertSame('192.168.5.1/32', (string) $splitIp[1]);
    }

    public function testSplitInHalfIpSix(): void
    {
        $splitIp = Ip::fromIpPrefix('::/0')->splitInHalf();
        static::assertCount(2, $splitIp);
        static::assertSame('::/1', (string) $splitIp[0]);
        static::assertSame('8000::/1', (string) $splitIp[1]);
    }

    public function testUnsplittableIpFour(): void
    {
        static::expectException(IpException::class);
        static::expectExceptionMessage('can not split prefix "/32"');
        $splitIp = Ip::fromIpPrefix('192.168.5.5/32')->splitInHalf();
    }

    public function testUnsplittableIpSix(): void
    {
        static::expectException(IpException::class);
        static::expectExceptionMessage('can not split prefix "/128"');
        $splitIp = Ip::fromIpPrefix('fd99::1/128')->splitInHalf();
    }
}
