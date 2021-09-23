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
use LC\Portal\WireGuard\Wg;
use LC\Portal\WireGuard\WgServerConfig;

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

    $wgServerConfig = new WgServerConfig($baseDir.'/data');

    $wg = new Wg(new CurlHttpClient(), $storage, $wgServerConfig->publicKey(), $config->wgPort());
    foreach ($config->profileConfigList() as $profileConfig) {
        if ('wireguard' === $profileConfig->vpnProto()) {
            // extract the peers from the DB per profile
            $wg->syncPeers($profileConfig, $storage->wgGetAllPeers($profileConfig->profileId()));
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
