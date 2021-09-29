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

        $wPeerListByProfileId = $storage->wPeerListByProfileId($profileConfig->profileId());
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
