<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\ServerConfigCheck;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ServerConfigCheckTest extends TestCase
{
    public function testcheckOverlap(): void
    {
        // IPv4
        static::assertEmpty(ServerConfigCheck::checkOverlap(['192.168.0.0/24', '10.0.0.0/8']));
        static::assertEmpty(ServerConfigCheck::checkOverlap(['192.168.0.0/24', '192.168.1.0/24']));
        static::assertEmpty(ServerConfigCheck::checkOverlap(['192.168.0.0/25', '192.168.0.128/25']));

        static::assertSame(
            [
                [
                    '192.168.0.0/24',
                    '192.168.0.0/24',
                ],
            ],
            ServerConfigCheck::checkOverlap(['192.168.0.0/24', '192.168.0.0/24'])
        );

        static::assertSame(
            [
                [
                    '192.168.0.0/25',
                    '192.168.0.0/24',
                ],
            ],
            ServerConfigCheck::checkOverlap(['192.168.0.0/24', '192.168.0.0/25'])
        );

        // IPv6
        static::assertEmpty(ServerConfigCheck::checkOverlap(['fd00::/8', 'fc00::/8']));
        static::assertEmpty(ServerConfigCheck::checkOverlap(['fd11:1111:1111:1111::/64', 'fd11:1111:1111:1112::/64']));

        static::assertSame(
            [
                [
                    'fd11:1111:1111:1111::/64',
                    'fd11:1111:1111:1111::/64',
                ],
            ],
            ServerConfigCheck::checkOverlap(['fd11:1111:1111:1111::/64', 'fd11:1111:1111:1111::/64'])
        );

        static::assertSame(
            [
                [
                    'fd11:1111:1111:1111::/100',
                    'fd11:1111:1111:1111::/64',
                ],
            ],
            ServerConfigCheck::checkOverlap(['fd11:1111:1111:1111::/64', 'fd11:1111:1111:1111::/100'])
        );
    }
}
