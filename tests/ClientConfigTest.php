<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\ClientConfig;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class ClientConfigTest extends TestCase
{
    public function testDefault(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testOne(): void
    {
        static::assertSame(
            ['udp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194'],
                ClientConfig::STRATEGY_RANDOM
            )
        );
    }

    public function testOneSpecial(): void
    {
        static::assertSame(
            ['udp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/443'],
                ClientConfig::STRATEGY_RANDOM
            )
        );
    }

    public function testNone(): void
    {
        static::assertSame(
            [],
            ClientConfig::remotePortProtoList(
                [],
                ClientConfig::STRATEGY_RANDOM
            )
        );
    }

    public function testFourPorts(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testFourPortsWithTcp443(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/443'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testFourPortsWithUdp53(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194', 'udp/53'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/53', 'tcp/1195'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testEightPorts(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/1197'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testTwoSpecial(): void
    {
        static::assertSame(
            ['udp/1194', 'tcp/1194', 'udp/443', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/443', 'tcp/443'],
                ClientConfig::STRATEGY_FIRST
            )
        );
    }

    public function testAll(): void
    {
        static::assertSame(
            ['udp/1194', 'udp/1195', 'udp/1196', 'udp/1197', 'tcp/1194', 'tcp/1195', 'tcp/1196', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/443'],
                ClientConfig::STRATEGY_ALL
            )
        );
    }
}
