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
}
