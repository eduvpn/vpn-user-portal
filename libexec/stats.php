<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\NullLogger;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));

    $logger = new NullLogger();
    $connectionManager = new ConnectionManager(
        $config,
        new VpnDaemon(new CurlHttpClient($baseDir.'/config/vpn-daemon'), $logger),
        $storage,
        $logger
    );

    $dateTime = Dt::get();
    foreach ($connectionManager->get() as $profileId => $connectionInfoList) {
        // suboptimal way to determine the number of unique users connected to
        // the VPN profile (the same user can have multiple clients connected)
        $userIdList = [];
        foreach ($connectionInfoList as $connectionInfo) {
            $userIdList[] = $connectionInfo['user_id'];
        }
        $storage->statsAdd($dateTime, $profileId, count(array_unique($userIdList)), count($connectionInfoList));
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;

    exit(1);
}
