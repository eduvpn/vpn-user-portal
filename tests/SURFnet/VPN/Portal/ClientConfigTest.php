<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace SURFnet\VPN\Portal;

use PHPUnit_Framework_TestCase;

class ClientConfigTest extends PHPUnit_Framework_TestCase
{
    public function testRemotePortProtoList1()
    {
        $this->assertSame(
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
            ],
            ClientConfig::remotePortProtoList(1, false)
        );
    }

    public function testRemotePortProtoList2()
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
            ],
            ClientConfig::remotePortProtoList(2, false)
        );
    }

    public function testRemotePortProtoList4()
    {
        $this->assertSame(
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1195,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 443,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1196,
                ],
            ],
            ClientConfig::remotePortProtoList(4, false)
        );
    }

    public function testRemotePortProtoList8()
    {
        $this->assertSame(
            [
                [
                    'proto' => 'udp',
                    'port' => 1194,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1195,
                ],
                [
                    'proto' => 'tcp',
                    'port' => 443,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1196,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1197,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1198,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1199,
                ],
                [
                    'proto' => 'udp',
                    'port' => 1200,
                ],
            ],
            ClientConfig::remotePortProtoList(8, false)
        );
    }
}
