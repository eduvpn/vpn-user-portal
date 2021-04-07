<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use LC\Portal\Config;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\WireGuard\WgDaemon;

$baseDir = dirname(__DIR__);
$configFile = $baseDir.'/config/config.php';
$dbDsn = 'sqlite://'.$baseDir.'/data/db.sqlite';
$schemaDir = $baseDir.'/schema';

try {
    $config = Config::fromFile($configFile);
    $storage = new Storage(new PDO($dbDsn), $schemaDir);
    $wgDaemon = new WgDaemon(new CurlHttpClient());
    foreach ($config->requireArray('vpnProfiles') as $profileId => $profileData) {
        $profileConfig = new ProfileConfig(new Config($profileData));
        if ('wireguard' === $profileConfig->vpnType()) {
            $wgDevice = 'wg'.(string) $profileConfig->profileNumber();
            // extract the peers from the DB per profile
            $wgDaemon->syncPeers($wgDevice, $storage->wgGetAllPeers($profileId));
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
