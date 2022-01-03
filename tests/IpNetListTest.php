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
use Vpn\Portal\Ip;
use Vpn\Portal\IpNetList;

/**
 * @internal
 * @coversNothing
 */
final class IpNetListTest extends TestCase
{
    public function testSingle(): void
    {
        $ipList = new IpNetList([Ip::fromIpPrefix('0.0.0.0/0')]);
        $ipList->remove(Ip::fromIpPrefix('192.168.5.0/24'));

        static::assertSame(
            '[0.0.0.0/1 128.0.0.0/2 192.0.0.0/9 192.128.0.0/11 192.160.0.0/13 192.168.0.0/22 192.168.4.0/24 192.168.6.0/23 192.168.8.0/21 192.168.16.0/20 192.168.32.0/19 192.168.64.0/18 192.168.128.0/17 192.169.0.0/16 192.170.0.0/15 192.172.0.0/14 192.176.0.0/12 192.192.0.0/10 193.0.0.0/8 194.0.0.0/7 196.0.0.0/6 200.0.0.0/5 208.0.0.0/4 224.0.0.0/3]',
            (string) $ipList
        );
    }

    public function testMultiple(): void
    {
        $ipList = new IpNetList([Ip::fromIpPrefix('0.0.0.0/0')]);
        $ipList->remove(Ip::fromIpPrefix('192.168.5.0/24'));
        $ipList->remove(Ip::fromIpPrefix('10.5.0.0/16'));
        $ipList->remove(Ip::fromIpPrefix('8.8.8.8/32'));

        static::assertSame(
            '[0.0.0.0/5 8.0.0.0/13 8.8.0.0/21 8.8.8.0/29 8.8.8.9/32 8.8.8.10/31 8.8.8.12/30 8.8.8.16/28 8.8.8.32/27 8.8.8.64/26 8.8.8.128/25 8.8.9.0/24 8.8.10.0/23 8.8.12.0/22 8.8.16.0/20 8.8.32.0/19 8.8.64.0/18 8.8.128.0/17 8.9.0.0/16 8.10.0.0/15 8.12.0.0/14 8.16.0.0/12 8.32.0.0/11 8.64.0.0/10 8.128.0.0/9 9.0.0.0/8 10.0.0.0/14 10.4.0.0/16 10.6.0.0/15 10.8.0.0/13 10.16.0.0/12 10.32.0.0/11 10.64.0.0/10 10.128.0.0/9 11.0.0.0/8 12.0.0.0/6 16.0.0.0/4 32.0.0.0/3 64.0.0.0/2 128.0.0.0/2 192.0.0.0/9 192.128.0.0/11 192.160.0.0/13 192.168.0.0/22 192.168.4.0/24 192.168.6.0/23 192.168.8.0/21 192.168.16.0/20 192.168.32.0/19 192.168.64.0/18 192.168.128.0/17 192.169.0.0/16 192.170.0.0/15 192.172.0.0/14 192.176.0.0/12 192.192.0.0/10 193.0.0.0/8 194.0.0.0/7 196.0.0.0/6 200.0.0.0/5 208.0.0.0/4 224.0.0.0/3]',
            (string) $ipList
        );
    }

    public function testAdd(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/24'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }

    public function testAddExisting(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/24'));
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/24'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }

    public function testAddNonNormalized(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.5/24'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }

    public function testAddExistingSameNet(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.4/24'));
        $ipList->add(Ip::fromIpPrefix('192.168.5.5/24'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }

    public function testAddSubPrefixOfExisting(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/24'));
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/25'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }

    public function testAddSuperPrefixOfExisting(): void
    {
        $ipList = new IpNetList();
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/25'));
        $ipList->add(Ip::fromIpPrefix('192.168.5.0/24'));
        static::assertSame(
            '[192.168.5.0/24]',
            (string) $ipList
        );
    }
}
