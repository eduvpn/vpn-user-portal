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
    public function get(array $clientConfig, $shuffleRemoteHosts = true)
    {
        $requiredParameters = [
            'cn',
            'valid_from',
            'valid_to',
            'ca',
            'cert',
            'key',
            'ta',
            'remote',
            'twoFactor',
        ];

        // XXX verify the parameters and types

        foreach ($requiredParameters as $p) {
            if (!array_key_exists($p, $clientConfig)) {
                throw new RuntimeException(sprintf('missing parameter "%s"', $p));
            }
        }

        $remoteHosts = $clientConfig['remote'];
        if ($shuffleRemoteHosts) {
            $remoteHosts = self::shuffleRemoteHosts($remoteHosts);
        }

        $remoteEntries = [];
        foreach ($remoteHosts as $remoteEntry) {
            $host = $remoteEntry['host'];
            $proto = $remoteEntry['proto'];
            if ('tcp' === $proto) {
                $port = 443;
            } else {
                $port = intval($remoteEntry['port']);
            }

            $remoteEntries[] = sprintf('remote %s %d %s', $host, $port, $proto);
        }

        $twoFactorEntries = [];
        if ($clientConfig['twoFactor']) {
            $twoFactorEntries[] = 'auth-user-pass';
        }

        return [
            sprintf('# OpenVPN Client Configuration for %s', $clientConfig['cn']),

            sprintf('# Valid From: %s', date('c', $clientConfig['valid_from'])),
            sprintf('# Valid To: %s', date('c', $clientConfig['valid_to'])),

            'dev tun',
            'client',
            'nobind',
            'persist-key',
            'persist-tun',
            'remote-cert-tls server',

            // adaptive compression, allow server to override using push, it
            // cannot be no here because that would confuse NetworkManager
            'comp-lzo',

            'verb 3',

            //redirect-gateway

            // do not pull route/DNS information from server
            //route-nopull

            // tell the server more about this client (version, OS)
            'push-peer-info',

            // REMOTES
            // wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            // 2FA
            implode(PHP_EOL, $twoFactorEntries),

            // allow the server to dictate the reneg-sec, by default it will be
            // 3600 seconds, but when 2FA is enable we'd like to increase this
            // to e.g. 8 hours to avoid asking for the OTP every hour
            'reneg-sec 0',

            // remote
            implode(PHP_EOL, $remoteEntries),

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

            sprintf('<ca>%s</ca>', PHP_EOL.$clientConfig['ca'].PHP_EOL),
            sprintf('<cert>%s</cert>', PHP_EOL.$clientConfig['cert'].PHP_EOL),
            sprintf('<key>%s</key>', PHP_EOL.$clientConfig['key'].PHP_EOL),

            'key-direction 1',

            sprintf('<tls-auth>%s</tls-auth>', PHP_EOL.$clientConfig['ta'].PHP_EOL),
        ];
    }

    public static function shuffleRemoteHosts(array $remoteHosts)
    {
        // we want to put the UDP entries first, randomize them and then
        // add the TCP entries afterwards so we have a nice fallthrough to
        // eventual TCP but still get "load balancing"
        $udpRemotes = [];
        $tcpRemotes = [];
        foreach ($remoteHosts as $remoteEntry) {
            if ('udp' === $remoteEntry['proto'] || 'udp6' === $remoteEntry['proto']) {
                $udpRemotes[] = $remoteEntry;
            }
            if ('tcp' === $remoteEntry['proto'] || 'tcp6' === $remoteEntry['proto']) {
                $tcpRemotes[] = $remoteEntry;
            }
            // ignore other protocols
        }

        shuffle($udpRemotes);
        shuffle($tcpRemotes);

        // crazy ahead
        if (2 < count($udpRemotes)) {
            // if there are > 2 UDP entries
            if (1 < count($tcpRemotes)) {
                // and > 1 TCP entry
                $mergedRemotes = array_merge(
                    array_slice($udpRemotes, 0, 2),
                    array_slice($tcpRemotes, 0, 1),
                    array_slice($udpRemotes, 2),
                    array_slice($tcpRemotes, 1)
                );
            } else {
                // <= 1 TCP entry
                $mergedRemotes = array_merge(
                    array_slice($udpRemotes, 0, 2),
                    $tcpRemotes,
                    array_slice($udpRemotes, 2)
                );
            }
        } else {
            // <= 2 UDP entries
            $mergedRemotes = array_merge($udpRemotes, $tcpRemotes);
        }

        return $mergedRemotes;
    }
}
