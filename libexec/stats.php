<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHooks;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));
    $connectionManager = new ConnectionManager(
        $config,
        new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger),
        $storage,
        ConnectionHooks::init($config, $storage, $logger),
        $logger
    );

    $dateTime = Dt::get();
    foreach ($connectionManager->get() as $profileId => $connectionInfoList) {
        $storage->statsAdd($dateTime, $profileId, count($connectionInfoList));
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;

    exit(1);
}
