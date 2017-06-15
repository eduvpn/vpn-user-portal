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
    public static function get(array $profileConfig, array $serverInfo, array $clientCertificate, $shufflePorts, array $addVpnProtoPorts)
    {
        // make a list of ports/proto to add to the configuration file
        $hostName = $profileConfig['hostName'];
        $vpnProtoPorts = array_merge(
            array_values($profileConfig['vpnProtoPorts']),
            array_values($addVpnProtoPorts)
        );

        $remoteProtoPortList = self::remotePortProtoList($vpnProtoPorts, $shufflePorts);

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
            'tls-cipher TLS-ECDHE-RSA-WITH-AES-256-GCM-SHA384',

            '<ca>',
            trim($serverInfo['ca']),
            '</ca>',
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

        if ($profileConfig['tlsCrypt']) {
            $clientConfig = array_merge(
                $clientConfig,
                [
                    '<tls-crypt>',
                    trim($serverInfo['ta']),
                    '</tls-crypt>',
                ]
            );
        } else {
            $clientConfig = array_merge(
                $clientConfig,
                [
                    'key-direction 1',
                    '<tls-auth>',
                    trim($serverInfo['ta']),
                    '</tls-auth>',
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
        $hasUdp53 = false;
        $hasTcp443 = false;

        foreach ($vpnProtoPorts as $vpnProtoPort) {
            list($proto, $port) = explode('/', $vpnProtoPort);
            if ('udp' === $proto) {
                $port = (int) $port;
                if (53 === $port) {
                    $hasUdp53 = true;
                } else {
                    $udpPorts[] = $port;
                }
                continue;
            }
            if ('tcp' === $proto) {
                $port = (int) $port;
                if (443 === $port) {
                    $hasTcp443 = true;
                } else {
                    $tcpPorts[] = $port;
                }
                continue;
            }

            throw new RuntimeException('invalid protocol');
        }

        $udpIndex = 0;
        $tcpIndex = 0;
        if ($shufflePorts) {
            $udpIndex = 0 !== count($udpPorts) ? random_int(0, count($udpPorts) - 1) : 0;
            $tcpIndex = 0 !== count($tcpPorts) ? random_int(0, count($tcpPorts) - 1) : 0;
        }

        $protoPortList = [];
        if (0 !== count($udpPorts)) {
            $protoPortList[] = ['proto' => 'udp', 'port' => $udpPorts[$udpIndex]];
        }
        if ($hasUdp53) {
            $protoPortList[] = ['proto' => 'udp', 'port' => 53];
        }

        if (0 !== count($tcpPorts)) {
            $protoPortList[] = ['proto' => 'tcp', 'port' => $tcpPorts[$tcpIndex]];
        }
        if ($hasTcp443) {
            $protoPortList[] = ['proto' => 'tcp', 'port' => 443];
        }

        return $protoPortList;
    }
}
