<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\UserPortal;

use RuntimeException;

class ClientConfig
{
    public function get(array $clientConfig)
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
        ];

        // XXX verify the parameters and types

        foreach ($requiredParameters as $p) {
            if (!array_key_exists($p, $clientConfig)) {
                throw new RuntimeException(sprintf('missing parameter "%s"', $p));
            }
        }

        // we want to put the UDP entries first, randomize them and then 
        // add the TCP entries afterwards so we have a nice fallthrough to
        // eventual TCP but still get "load balancing"
        $udpRemotes = [];
        $tcpRemotes = [];
        foreach ($clientConfig['remote'] as $remoteEntry) {
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

        $mergedRemotes = array_merge($udpRemotes, $tcpRemotes);

        $remoteEntries = [];
        foreach ($mergedRemotes as $remoteEntry) {
            $remoteEntries[] = sprintf('remote %s %d %s', $remoteEntry['host'], intval($remoteEntry['port']), $remoteEntry['proto']);
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

            # disable compression, but allow server to override using push
            'comp-lzo no',

            'verb 3',

            #redirect-gateway

            # do not pull route/DNS information from server
            #route-nopull

            # tell the server more about this client (version, OS)
            'push-peer-info',

            # REMOTES 
            # wait this long (seconds) before trying the next server in the list
            'server-poll-timeout 10',

            'auth-user-pass',

            # allow the server to dictate the reneg-sec, by default it will be
            # 3600 seconds, but when 2FA is enable we'd like to increase this
            # to e.g. 8 hours to avoid asking for the OTP every hour
            'reneg-sec 0',

            # remote
            implode(PHP_EOL, $remoteEntries),

            # CRYPTO (DATA CHANNEL)
            'auth SHA256',
            'cipher AES-256-CBC',

            # CRYPTO (CONTROL CHANNEL)
            # @see RFC 7525  
            # @see https://bettercrypto.org
            # @see https://community.openvpn.net/openvpn/wiki/Hardening
            'tls-version-min 1.2',

            # To work with default configuration in iOS OpenVPN with
            # "Force AES-CBC ciphersuites" enabled, we need to accept an 
            # additional cipher "TLS_DHE_RSA_WITH_AES_256_CBC_SHA"
            'tls-cipher TLS-DHE-RSA-WITH-AES-128-GCM-SHA256:TLS-DHE-RSA-WITH-AES-256-GCM-SHA384:TLS-DHE-RSA-WITH-AES-256-CBC-SHA',

            sprintf('<ca>%s</ca>', PHP_EOL.$clientConfig['ca'].PHP_EOL),
            sprintf('<cert>%s</cert>', PHP_EOL.$clientConfig['cert'].PHP_EOL),
            sprintf('<key>%s</key>', PHP_EOL.$clientConfig['key'].PHP_EOL),

            'key-direction 1',

            sprintf('<tls-auth>%s</tls-auth>', PHP_EOL.$clientConfig['ta'].PHP_EOL),
        ];
    }
}
