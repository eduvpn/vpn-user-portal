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

use RuntimeException;

class ClientConfig
{
    public static function get(array $profileConfig, array $serverInfo, array $clientCertificate, $shufflePorts)
    {
        // make a list of ports/proto to add to the configuration file
        $hostName = $profileConfig['hostName'];
        $remoteProtoPortList = self::remotePortProtoList($profileConfig['vpnProtoPorts'], $shufflePorts);

        $clientConfig = [
            '# OpenVPN Client Configuration',
            'dev tun',
            'client',
            'nobind',

            // the server can also push these if needed, and it should be up
            // to the client anyway if this is a good idea or not, e.g. running
            // in a chroot
            //'persist-key',
            //'persist-tun',
            'remote-cert-tls server',

            // adaptive compression, allow server to override using push
            'comp-lzo',

            'verb 3',

            // wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            // CRYPTO (DATA CHANNEL)
            'auth SHA256',
            'cipher AES-256-CBC',

            // CRYPTO (CONTROL CHANNEL)
            // @see RFC 7525
            // @see https://bettercrypto.org
            // @see https://community.openvpn.net/openvpn/wiki/Hardening
            'tls-version-min 1.2',

            'tls-cipher TLS-ECDHE-RSA-WITH-AES-256-GCM-SHA384:TLS-ECDHE-ECDSA-WITH-AES-256-GCM-SHA384:TLS-ECDHE-RSA-WITH-AES-256-CBC-SHA384:TLS-ECDHE-ECDSA-WITH-AES-256-CBC-SHA384:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-128-GCM-SHA256',

            '<ca>',
            trim($serverInfo['ca']),
            '</ca>',

            'key-direction 1',

            '<tls-auth>',
            trim($serverInfo['ta']),
            '</tls-auth>',
        ];

        // API 1, if clientCertificate is provided, we add it directly to the
        // configuration file, XXX can be removed for API 2
        if (0 !== count($clientCertificate)) {
            $clientConfig = array_merge(
                $clientConfig,
                [
                    '<cert>',
                    $clientCertificate['certificate'],
                    '</cert>',

                    '<key>',
                    $clientCertificate['private_key'],
                    '</key>',
                ]
            );
        }

        // 2FA
        if ($profileConfig['twoFactor']) {
            $clientConfig[] = 'auth-user-pass';
        }

        // remote entries
        foreach ($remoteProtoPortList as $remoteProtoPort) {
            $clientConfig[] = sprintf('remote %s %d %s', $hostName, $remoteProtoPort['port'], $remoteProtoPort['proto']);
        }

        return implode(PHP_EOL, $clientConfig);
    }

    public static function remotePortProtoList(array $vpnProtoPorts, $shufflePorts)
    {
        $udpPorts = [];
        $tcpPorts = [];
        foreach ($vpnProtoPorts as $vpnProtoPort) {
            list($proto, $port) = explode('/', $vpnProtoPort);
            if ('udp' === $proto) {
                $udpPorts[] = (int) $port;
                continue;
            }
            if ('tcp' === $proto) {
                $tcpPorts[] = (int) $port;
                continue;
            }

            throw new RuntimeException('invalid protocol');
        }

        if ($shufflePorts) {
            shuffle($udpPorts);
            shuffle($tcpPorts);
        }

        $protoPortList = [];
        // take the first 2 UDP entries, if they are there
        for ($i = 0; $i < 2; ++$i) {
            if (null !== $udpPort = array_shift($udpPorts)) {
                $protoPortList[] = ['proto' => 'udp', 'port' => $udpPort];
            }
        }

        // then take the first TCP entry
        if (null !== $tcpPort = array_shift($tcpPorts)) {
            $protoPortList[] = ['proto' => 'tcp', 'port' => $tcpPort];
        }

        // then take the rest of the UDP entries
        while (null !== $udpPort = array_shift($udpPorts)) {
            $protoPortList[] = ['proto' => 'udp', 'port' => $udpPort];
        }

        // then take the rest of the TCP entries
        while (null !== $tcpPort = array_shift($tcpPorts)) {
            $protoPortList[] = ['proto' => 'tcp', 'port' => $tcpPort];
        }

        return $protoPortList;
    }
}
