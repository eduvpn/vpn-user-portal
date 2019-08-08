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
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194'],
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
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195'],
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
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 443,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/443'],
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
            [
                [
                    'proto' => 'udp',
                    'port' => 53,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/53', 'tcp/1195'],
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
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(
                ['udp/1194', 'tcp/1194', 'udp/1195', 'tcp/1195', 'udp/1196', 'tcp/1196', 'udp/1197', 'tcp/1197'],
                false
            )
        );
    }
}