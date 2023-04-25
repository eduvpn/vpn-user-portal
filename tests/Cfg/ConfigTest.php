<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace Vpn\Portal\Tests;

use PHPUnit\Framework\TestCase;
use Vpn\Portal\Cfg\Config;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConfigTest extends TestCase
{
    public function testNodeNumberUrlList(): void
    {
        $c = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'foo',
                        'nodeUrl' => 'http://n1.home.arpa:41194',
                        'onNode' => 0,
                    ],
                    [
                        'profileId' => 'bar',
                        'nodeUrl' => ['http://n2.home.arpa:41194', 'http://n3.home.arpa:41194'],
                        'onNode' => [1, 2],
                    ],
                ],
            ]
        );

        static::assertSame(
            [
                0 => 'http://n1.home.arpa:41194',
                1 => 'http://n2.home.arpa:41194',
                2 => 'http://n3.home.arpa:41194',
            ],
            $c->nodeNumberUrlList()
        );
    }

    public function testNodeNumberUrlListOverlap(): void
    {
        $c = new Config(
            [
                'ProfileList' => [
                    [
                        'profileId' => 'foo',
                        'nodeUrl' => ['http://n1.home.arpa:41194', 'http://n2.home.arpa:41194'],
                    ],
                    [
                        'profileId' => 'bar',
                        'nodeUrl' => ['http://n1.home.arpa:41194', 'http://n2.home.arpa:41194'],
                    ],
                ],
            ]
        );

        static::assertSame(
            [
                0 => 'http://n1.home.arpa:41194',
                1 => 'http://n2.home.arpa:41194',
            ],
            $c->nodeNumberUrlList()
        );
    }

    public function testDefaultSupportedSessionExpiry(): void
    {
        $c = new Config([]);
        $this->assertSame(['P90D'], $c->supportedSessionExpiry());
    }

    public function testSupportedSessionExpiry(): void
    {
        $c = new Config(
            [
                'supportedSessionExpiry' => ['PT12H', 'P1Y'],
            ]
        );
        $this->assertSame(
            [
                'P90D',
                'PT12H',
                'P1Y',
            ],
            $c->supportedSessionExpiry()
        );
    }

    public function testDuplicateSupportedSessionExpiry(): void
    {
        $c = new Config(
            [
                'supportedSessionExpiry' => ['PT12H', 'P90D', 'P1Y'],
            ]
        );
        $this->assertSame(
            [
                'P90D',
                'PT12H',
                'P1Y',
            ],
            $c->supportedSessionExpiry()
        );
    }
}
