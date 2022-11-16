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
use Vpn\Portal\WireGuard\ClientConfig;
use DateTimeImmutable;
use Vpn\Portal\Cfg\ProfileConfig;

/**
 * @internal
 *
 * @coversNothing
 */
final class ClientConfigTest extends TestCase
{
    public function testDnsTemplate(): void
    {
        $c = new ClientConfig(
            'https://vpn.example.org/vpn-user-portal',
            0,
            new ProfileConfig(
                [
                    'profileId' => 'default',
                    'displayName' => 'Default',
                    'hostName' => 'vpn.example.org',
                    'wRangeFour' => '10.42.42.0/24',
                    'wRangeSix' => 'fd42::/64',
                    'dnsServerList' => ['@GW4@', '9.9.9.9', '@GW6@'],
                ],
            ),
            '10.42.42.5',
            'fd42::5',
            'Ul2qef/xiidFPn8Wi8+3rvzpHLG4irsrUOxmAXTXWFw=',
            443,
            new DateTimeImmutable('2022-11-11T11:11:11+00:00')
        );

        $this->assertSame(
            <<<EOF
                # Portal: https://vpn.example.org/vpn-user-portal
                # Profile: Default (default)
                # Expires: 2022-11-11T11:11:11+00:00

                [Interface]
                Address = 10.42.42.5/24,fd42::5/64
                DNS = 10.42.42.1,9.9.9.9,fd42::1

                [Peer]
                PublicKey = Ul2qef/xiidFPn8Wi8+3rvzpHLG4irsrUOxmAXTXWFw=
                AllowedIPs = 0.0.0.0/0,::/0
                Endpoint = vpn.example.org:443
                EOF,
            $c->get()
        );
    }
}
