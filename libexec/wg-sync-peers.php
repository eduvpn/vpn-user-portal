<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\Storage;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->dbConfig($baseDir)->dbDsn(),
            $config->dbConfig($baseDir)->dbUser(),
            $config->dbConfig($baseDir)->dbPass()
        ),
        $baseDir.'/schema'
    );

    $connectionManager = new ConnectionManager(
        $config,
        new CurlHttpClient(),
        $storage
    );

    // all configured (also offline and never seen) WG peers for all nodes
    // (sorted by profile_id)
    $daemonPeerList = $connectionManager->get(true);

    foreach ($config->profileConfigList() as $profileConfig) {
        // for all WireGuard profiles...
        if ('wireguard' === $profileConfig->vpnProto()) {
            // XXX cache response from /peer_list per unique nodeBaseUrl
            // 1. get list of peers for this profile
            // 2. get list of all peers known to WG for this nodebaseurl
            // 3. figure out which ones are missing, add those to WG
            // 4. figure out which ones are no longer there, and remove those
            // 5. no need for syncPeers

            //    /**
            //     * @return array<array{user_id:string,display_name:string,public_key:string,ip_four:string,ip_six:string,expires_at:\DateTimeImmutable,auth_key:?string}>
            //     */
            $peerList = $storage->wPeerListByProfileId($profileConfig->profileId());
            $publicKeyList = [];
            // make the public_key the key of the array for easy locating
            foreach ($peerList as $v) {
                $publicKeyList[$v['public_key']] = $v;
            }

            //{
            //  "peer_list": [
            //    {
            //      "public_key": "x4Hg7IpFlsAUwsxtWh8xlPBmdQDhoQmZSC1i/nmuswE=",
            //      "ip_net": [
            //        "10.43.43.2/32",
            //        "fd43::2/128"
            //      ],
            //      "last_handshake_time": "2021-09-16T19:52:57+02:00",
            //      "bytes_transferred": 14384
            //    }
            //  ]
            //}

            $daemonPublicKeyList = [];
            // make the public_key the key of the array for easy locating
            foreach ($daemonPeerList[$profileId] as $v) {
                $daemonPublicKeyList[$v['public_key']] = $v;
            }

            // find the peers that are in the database, but not know by the daemon
            foreach (array_diff(array_keys($publicKeyList), array_keys($daemonPublicKeyList)) as $peerToAdd) {
                // add peer XXX maybe "restore peer" option is better?!
                $connectionManager->connect(
                    $daemonPublicKeyList[$peerToRemove]['user_id'],
                    $profileId,
                    'XYZ'
                );
            }

            // find the peers that are NOT in the database, but know by the daemon
            foreach (array_diff(array_keys($daemonPublicKeyList), array_keys($publicKeyList)) as $peerToRemove) {
                // remove peer
                $connectionManager->disconnect(
                    $daemonPublicKeyList[$peerToRemove]['user_id'],
                    $profileId,
                    $peerToRemove
                );
            }
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
