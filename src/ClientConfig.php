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

            // only allow AES-256-GCM
            'ncp-ciphers AES-256-GCM',
            'cipher AES-256-GCM',

            '<ca>',
            trim($serverInfo['ca']),
            '</ca>',
        ];

        if (\array_key_exists('tlsOneThree', $profileConfig) && $profileConfig['tlsOneThree']) {
            // for TLSv1.3 we don't care about the tls-ciphers, they are all
            // fine, let the client choose
            $clientConfig[] = 'tls-version-min 1.3';
        } else {
            // CRYPTO (CONTROL CHANNEL)
            // @see RFC 7525
            // @see https://bettercrypto.org
            // @see https://community.openvpn.net/openvpn/wiki/Hardening
            $clientConfig[] = 'tls-version-min 1.2';
            $clientConfig[] = 'tls-cipher TLS-ECDHE-RSA-WITH-AES-256-GCM-SHA384';
        }

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
            $clientConfig[] = sprintf('remote %s %d %s', $hostName, (int) substr($remoteProtoPort, 4), substr($remoteProtoPort, 0, 3));
        }

        return implode(PHP_EOL, $clientConfig);
    }

    /**
     * Pick a "normal" UDP and TCP port. Pick a "special" UDP and TCP
     * port. Take one at random if $pickAtRandom is true.
     *
     * @param array $vpnProtoPorts
     * @param bool  $pickAtRandom
     *
     * @return array<string>
     */
    public static function remotePortProtoList(array $vpnProtoPorts, $pickAtRandom)
    {
        $specialUdpPorts = [];
        $specialTcpPorts = [];
        $normalUdpPorts = [];
        $normalTcpPorts = [];

        foreach ($vpnProtoPorts as $vpnProtoPort) {
            if (0 === strpos($vpnProtoPort, 'udp')) {
                if (\in_array($vpnProtoPort, ['udp/53', 'udp/443'], true)) {
                    $specialUdpPorts[] = $vpnProtoPort;
                    continue;
                }
                $normalUdpPorts[] = $vpnProtoPort;
                continue;
            }

            if (0 === strpos($vpnProtoPort, 'tcp')) {
                if (\in_array($vpnProtoPort, ['tcp/80', 'tcp/443'], true)) {
                    $specialTcpPorts[] = $vpnProtoPort;
                    continue;
                }
                $normalTcpPorts[] = $vpnProtoPort;
                continue;
            }
        }

        $clientPortList = [];
        self::getItem($clientPortList, $normalUdpPorts, $pickAtRandom);
        self::getItem($clientPortList, $normalTcpPorts, $pickAtRandom);
        self::getItem($clientPortList, $specialUdpPorts, $pickAtRandom);
        self::getItem($clientPortList, $specialTcpPorts, $pickAtRandom);

        return $clientPortList;
    }

    /**
     * @param array<string> &$clientPortList
     * @param array<string> $pickFrom
     * @param bool          $pickAtRandom
     *
     * @return void
     */
    private static function getItem(array &$clientPortList, array $pickFrom, $pickAtRandom)
    {
        if (0 === \count($pickFrom)) {
            return;
        }
        if ($pickAtRandom) {
            $clientPortList[] = $pickFrom[random_int(0, \count($pickFrom) - 1)];

            return;
        }
        $clientPortList[] = reset($pickFrom);
    }
}
