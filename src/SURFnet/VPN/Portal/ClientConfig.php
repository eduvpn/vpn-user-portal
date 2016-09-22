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

class ClientConfig
{
    public static function get(array $poolConfig, array $clientCertificate, $shuffleRemoteHostList)
    {
        // make a list of ports/proto to add to the configuration file
        $hostName = $poolConfig['hostName'];
        $processCount = $poolConfig['processCount'];

        $remoteProtoPortList = self::remotePortProtoList($processCount, $shuffleRemoteHostList);

        $clientConfig = [
            sprintf('# OpenVPN Client Configuration for %s', $clientCertificate['cn']),

            // XXX fix date format to be in UTC
            sprintf('# Valid From: %s', date('Y-m-d', $clientCertificate['valid_from'])),
            sprintf('# Valid To: %s', date('Y-m-d', $clientCertificate['valid_to'])),

            'dev tun',
            'client',
            'nobind',
            'persist-key',
            'persist-tun',
            'remote-cert-tls server',

            // adaptive compression, allow server to override using push
            'comp-lzo',

            'verb 3',
            // tell the server more about this client (version, OS)
            'push-peer-info',

            // wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            // allow the server to dictate the reneg-sec, by default it will be
            // 3600 seconds, but when 2FA is enable we'd like to increase this
            // to e.g. 8 hours to avoid asking for the OTP every hour
            'reneg-sec 0',

            // CRYPTO (DATA CHANNEL)
            'auth SHA256',
            'cipher AES-256-CBC',

            // CRYPTO (CONTROL CHANNEL)
            // @see RFC 7525
            // @see https://bettercrypto.org
            // @see https://community.openvpn.net/openvpn/wiki/Hardening
            'tls-version-min 1.2',

            // To work with default configuration in iOS OpenVPN with
            // "Force AES-CBC ciphersuites" enabled, we need to accept an
            // additional cipher "TLS_DHE_RSA_WITH_AES_256_CBC_SHA"
            'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',

            '<ca>',
            $clientCertificate['ca'],
            '</ca>',

            '<cert>',
            $clientCertificate['cert'],
            '</cert>',

            '<key>',
            $clientCertificate['key'],
            '</key>',

            'key-direction 1',

            '<tls-auth>',
            $clientCertificate['ta'],
            '</tls-auth>',
        ];

        // 2FA
        if ($poolConfig['twoFactor']) {
            $clientConfig[] = 'auth-user-pass';
        }

        // remote entries
        foreach ($remoteProtoPortList as $remoteProtoPort) {
            $clientConfig[] = sprintf('remote %s %d %s', $hostName, $remoteProtoPort['port'], $remoteProtoPort['proto']);
        }

        return implode(PHP_EOL, $clientConfig);
    }

    public static function remotePortProtoList($processCount, $shuffleRemoteHostList)
    {
        // processCount can be 1, 2, 4, 8, ...
        if (1 === $processCount) {
            return [
                ['proto' => 'udp', 'port' => 1194],
            ];
        }

        if (2 === $processCount) {
            return [
                ['proto' => 'udp', 'port' => 1194],
                ['proto' => 'tcp', 'port' => 443],
            ];
        }

        $remoteHostList = [];
        for ($i = 0; $i < $processCount - 1; ++$i) {
            $remoteHostList[] = ['port' => 1194 + $i, 'proto' => 'udp'];
        }

        if ($shuffleRemoteHostList) {
            shuffle($remoteHostList);
        }

        // insert TCP at position 2
        array_splice(
            $remoteHostList, 2, 0, [['port' => 443, 'proto' => 'tcp']]
        );

        return $remoteHostList;
    }
}
