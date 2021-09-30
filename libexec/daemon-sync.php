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
use LC\Portal\VpnDaemon;

/*
 * This script is responsible for three things:
 * 1. (Re)add WireGuard peers when they are missing, e.g. after a node reboot
 * 2. Delete WireGuard peers with expired configurations
 * 3. Disconnect OpenVPN clients with expired certificates
 *
 * This script interfaces with `vpn-daemon` running on the node(s). It will
 * first figure out which peers/clients should be there and remove/disconnect
 * the ones that should NOT be there (anymore).
 *
 * Due to the architecture, e.g. multiple profiles can use the same vpn-daemon
 * and the vpn-daemon has no concept of "profiles" the administration is a bit
 * complicated. A list of peers/clients is created linked to a "nodeBaseUrl",
 * i.e. the URL for connecting to vpn-daemon that belongs to a profile and
 * afterwards perform add/remove/disconnect what is necessary.
 */

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
    $vpnDaemon = new VpnDaemon(new CurlHttpClient());
    foreach ($config->profileConfigList() as $profileConfig) {
        if ('wireguard' === $profileConfig->vpnProto()) {
            $wPeerListByProfileId = $storage->wPeerListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED);
            $wPeerList = $vpnDaemon->wPeerList($profileConfig->nodeBaseUrl(), true);

            // register peers in WG that are (still) missing
            $publicKeyList = array_diff(array_keys($wPeerListByProfileId), array_keys($wPeerList));
            foreach ($publicKeyList as $publicKey) {
                $vpnDaemon->wPeerAdd(
                    $profileConfig->nodeBaseUrl(),
                    $publicKey,
                    $wPeerListByProfileId[$publicKey]['ip_four'],
                    $wPeerListByProfileId[$publicKey]['ip_six']
                );
            }

            // remove peers from WG that are no longer there (or expired)
            // **XXX**
        }

        if ('openvpn' === $profileConfig->vpnProto()) {
            // disconnect OpenVPN clients with expired certificate
            $oCertListByProfileId = $storage->oCertListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED);
            $oConnectionList = $vpnDaemon->oConnectionList($profileConfig->nodeBaseUrl());

            // disconnect all CNs not in $oCertListByProfileId
            // but the daemon will portentially return more than just this profile...
            // **XXX**
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
