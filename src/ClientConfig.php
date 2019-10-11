<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Portal;

class ClientConfig
{
    /**
     * @param array $profileConfig
     * @param array $serverInfo
     * @param array $clientCertificate
     * @param bool  $shufflePorts
     *
     * @return string
     */
    public static function get(array $profileConfig, array $serverInfo, array $clientCertificate, $shufflePorts)
    {
        // make a list of ports/proto to add to the configuration file
        $hostName = $profileConfig['hostName'];

        $vpnProtoPorts = $profileConfig['vpnProtoPorts'];
        if (\array_key_exists('exposedVpnProtoPorts', $profileConfig)) {
            if (0 !== \count($profileConfig['exposedVpnProtoPorts'])) {
                $vpnProtoPorts = $profileConfig['exposedVpnProtoPorts'];
            }
        }

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

            'verb 3',

            // wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            // CRYPTO (CONTROL CHANNEL)
            // @see RFC 7525
            // @see https://bettercrypto.org
            // @see https://community.openvpn.net/openvpn/wiki/Hardening
            'tls-version-min 1.2',
            'tls-cipher TLS-ECDHE-RSA-WITH-AES-256-GCM-SHA384',

            // only allow AES-256-GCM
            'ncp-ciphers AES-256-GCM',
            'cipher AES-256-GCM',

            '<ca>',
            trim($serverInfo['ca']),
            '</ca>',
        ];

        // API 1, if clientCertificate is provided, we add it directly to the
        // configuration file, XXX can be removed for API 2
        if (0 !== \count($clientCertificate)) {
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

        if ('tls-crypt' === $profileConfig['tlsProtection']) {
            $clientConfig = array_merge(
                $clientConfig,
                [
                    '<tls-crypt>',
                    trim($serverInfo['ta']),
                    '</tls-crypt>',
                ]
            );
        }

        // remote entries
        foreach ($remoteProtoPortList as $remoteProtoPort) {
            $clientConfig[] = sprintf('remote %s %d %s', $hostName, $remoteProtoPort['port'], $remoteProtoPort['proto']);
        }

        return implode(PHP_EOL, $clientConfig);
    }

    /**
     * @param array $vpnProtoPorts
     * @param bool  $shufflePorts
     *
     * @return array
     */
    public static function remotePortProtoList(array $vpnProtoPorts, $shufflePorts)
    {
        // if these ports are listed in vpnProtoPorts they are ALWAYS added to
        // the client configuration file
        $specialUdpPorts = ['udp/53', 'udp/443'];
        $specialTcpPorts = ['tcp/80', 'tcp/443'];

        $udpPorts = [];
        $tcpPorts = [];
        $normalUdpPorts = [];
        $normalTcpPorts = [];

        foreach ($vpnProtoPorts as $vpnProtoPort) {
            if (0 === strpos($vpnProtoPort, 'udp')) {
                // UDP
                if (!\in_array($vpnProtoPort, $specialUdpPorts, true)) {
                    $normalUdpPorts[] = $vpnProtoPort;
                } else {
                    $udpPorts[] = $vpnProtoPort;
                }
            }

            if (0 === strpos($vpnProtoPort, 'tcp')) {
                // TCP
                if (!\in_array($vpnProtoPort, $specialTcpPorts, true)) {
                    $normalTcpPorts[] = $vpnProtoPort;
                } else {
                    $tcpPorts[] = $vpnProtoPort;
                }
            }
        }

        // pick one normal UDP port, if available
        if (0 !== \count($normalUdpPorts)) {
            if ($shufflePorts) {
                $udpPorts[] = $normalUdpPorts[random_int(0, \count($normalUdpPorts) - 1)];
            } else {
                $udpPorts[] = reset($normalUdpPorts);
            }
        }

        // pick one normal TCP port, if available
        if (0 !== \count($normalTcpPorts)) {
            if ($shufflePorts) {
                $tcpPorts[] = $normalTcpPorts[random_int(0, \count($normalTcpPorts) - 1)];
            } else {
                $tcpPorts[] = reset($normalTcpPorts);
            }
        }

        if ($shufflePorts) {
            // this is only "really" random in PHP >= 7.1
            shuffle($udpPorts);
            shuffle($tcpPorts);
        }

        $protoPortList = [];
        foreach ($udpPorts as $udpPort) {
            $protoPortList[] = ['proto' => 'udp', 'port' => (int) substr($udpPort, 4)];
        }
        foreach ($tcpPorts as $tcpPort) {
            $protoPortList[] = ['proto' => 'tcp', 'port' => (int) substr($tcpPort, 4)];
        }

        return $protoPortList;
    }
}
