<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests;

use LC\Portal\ClientConfig;
use PHPUnit\Framework\TestCase;

class ClientConfigTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefault()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testOne()
    {
        $this->assertSame(
            ['udp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194'],
                ClientConfig::STRATEGY_RANDOM,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testOneSpecial()
    {
        $this->assertSame(
            ['udp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/443'],
                ClientConfig::STRATEGY_RANDOM,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testNone()
    {
        $this->assertSame(
            [],
            ClientConfig::remotePortProtoList(
                [],
                ClientConfig::STRATEGY_RANDOM,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testFourPorts()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testFourPortsWithTcp443()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/443'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testFourPortsWithUdp53()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194', 'udp/53'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/53', 'tcp/1195'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testEightPorts()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/1197'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testTwoSpecial()
    {
        $this->assertSame(
            ['udp/1194', 'tcp/1194', 'udp/443', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/443', 'tcp/443'],
                ClientConfig::STRATEGY_FIRST,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testAll()
    {
        $this->assertSame(
            ['udp/1194', 'udp/1195', 'udp/1196', 'udp/1197', 'tcp/1194', 'tcp/1195', 'tcp/1196', 'tcp/443'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/443'],
                ClientConfig::STRATEGY_ALL,
                false
            )
        );
    }

    /**
     * @return void
     */
    public function testPreferTcp()
    {
        $this->assertSame(
            ['tcp/1194', 'tcp/1195', 'tcp/1196', 'tcp/443', 'udp/1194', 'udp/1195', 'udp/1196', 'udp/1197'],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/443'],
                ClientConfig::STRATEGY_ALL,
                true
            )
        );
    }
}
