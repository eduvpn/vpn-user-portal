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

// XXX also disconnect *openvpn* clients, not just WG, but that is mostly in
// order to kick clients that are currently connected with expired certificates?
// should this script also delete/disconnect *expired* certificates/public keys from the database? that sounds smart
// then we can get rid of libexec/disconnect-expired-certificates and not longer need housekeeping to delete certs/public keys
// housekeeping could then run daily to remove oauth session and log entries
// this sync script could run every 5 minutes for example taking care of:
// 1. make sure wg is in sync with db
// 2. remove old certificates / peer configs from db, and then also
// 3. disconnect openvpn clients with certs that are no longer valid and remove peers no longer valid
//
// or maybe simply have 1 housekeeping cron job that runs every 5 minutes that
// does everything? that may make more sense and it is easier to have only 1
// script instead of 2, or 3
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
        if ('wireguard' !== $profileConfig->vpnProto()) {
            continue;
        }

        // 1. get list of peers for this profile
        // 2. get list of all peers known to WG for this nodebaseurl
        // 3. figure out which ones are missing, add those to WG
        // 4. figure out which ones are no longer there, and remove those <-- XXX todo

        $wPeerListByProfileId = $storage->wPeerListByProfileId($profileConfig->profileId(), Storage::EXCLUDE_EXPIRED);
        $wPeerList = $vpnDaemon->wPeerList($profileConfig->nodeBaseUrl(), true);

        // find the peers that are in the database, but not know by the daemon
        foreach (array_diff(array_keys($wPeerListByProfileId), array_keys($wPeerList)) as $publicKey) {
            $vpnDaemon->wPeerAdd(
                $profileConfig->nodeBaseUrl(),
                $publicKey,
                $wPeerListByProfileId[$publicKey]['ip_four'],
                $wPeerListByProfileId[$publicKey]['ip_six']
            );
        }

        // XXX we also need to *remove* peers that are known by the daemon, but
        // that we don't have in the database...
        // however, as one nodeBaseUrl() can have multiple profiles on it, we
        // need to keep track which ones really need to be removed (somehow)
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
