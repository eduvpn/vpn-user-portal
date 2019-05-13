<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal\Tests\Http;

use LC\OpenVpn\ManagementSocketInterface;

/**
 * Abstraction to use the OpenVPN management interface using a socket
 * connection.
 */
class TestSocket implements ManagementSocketInterface
{
    /**
     * @param string $socketAddress
     * @param int    $timeOut
     *
     * @return void
     */
    public function open($socketAddress, $timeOut = 5)
    {
        // NOP
    }

    /**
     * @param string $command
     *
     * @return array<string>
     */
    public function command($command)
    {
        return [
            0 => 'TITLE,OpenVPN 2.4.2 x86_64-redhat-linux-gnu [Fedora EPEL patched] [SSL (OpenSSL)] [LZO] [LZ4] [EPOLL] [PKCS11] [MH/PKTINFO] [AEAD] built on May 11 2017',
            1 => 'TIME,Mon Jun 19 15:15:31 2017,1497885331',
            2 => 'HEADER,CLIENT_LIST,Common Name,Real Address,Virtual Address,Virtual IPv6 Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username,Client ID,Peer ID',
            3 => 'CLIENT_LIST,f3bb6f8efb4dc64be35e1044cf1b5e76,80.78.70.3,10.128.7.3,fd60:4a08:2f59:ba0::1001,11072,11568,Mon Jun 19 15:14:05 2017,1497885245,UNDEF,1,0',
            4 => 'CLIENT_LIST,78f4a3c26062a434b01892e2b23126d1,80.78.70.3,10.128.7.4,fd60:4a08:2f59:ba0::1002,9006,5770,Mon Jun 19 15:15:22 2017,1497885322,UNDEF,2,1',
            5 => 'HEADER,ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)',
            6 => 'ROUTING_TABLE,10.128.7.3,f3bb6f8efb4dc64be35e1044cf1b5e76,80.78.70.3,Mon Jun 19 15:15:31 2017,1497885331',
            7 => 'ROUTING_TABLE,10.128.7.4,78f4a3c26062a434b01892e2b23126d1,80.78.70.3,Mon Jun 19 15:15:30 2017,1497885330',
            8 => 'ROUTING_TABLE,fd60:4a08:2f59:ba0::1001,f3bb6f8efb4dc64be35e1044cf1b5e76,80.78.70.3,Mon Jun 19 15:15:30 2017,1497885330',
            9 => 'ROUTING_TABLE,fd60:4a08:2f59:ba0::1002,78f4a3c26062a434b01892e2b23126d1,80.78.70.3,Mon Jun 19 15:15:23 2017,1497885323',
            10 => 'GLOBAL_STATS,Max bcast/mcast queue length,0',
            11 => 'END',
        ];
    }

    /**
     * @return void
     */
    public function close()
    {
        // NOP
    }
}
