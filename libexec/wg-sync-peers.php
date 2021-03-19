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

use LC\Common\Config;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Common\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\WireGuard\WgDaemon;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
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
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
