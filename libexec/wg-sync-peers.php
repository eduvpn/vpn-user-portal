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
use LC\Portal\WireGuard\WgDaemon;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        $baseDir.'/schema'
    );
    $wgDaemon = new WgDaemon(new CurlHttpClient());
    foreach ($config->profileConfigList() as $profileConfig) {
        if ('wireguard' === $profileConfig->vpnType()) {
            $wgDevice = 'wg'.(string) ($profileConfig->profileNumber() - 1);
            // extract the peers from the DB per profile
            $wgDaemon->syncPeers('http://'.$profileConfig->nodeIp().':8080', $wgDevice, $storage->wgGetAllPeers($profileConfig->profileId()));
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
