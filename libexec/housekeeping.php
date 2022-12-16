<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Dt;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));

    $oneMonthAgo = Dt::get('today -1 month', new DateTimeZone('UTC'));
    $oneWeekAgo = Dt::get('today -1 week', new DateTimeZone('UTC'));
    $startOfTheDay = Dt::get('today', new DateTimeZone('UTC'));

    // aggregate old entries from the connection statistics
    $startTime = time();
    $storage->statsAggregate($oneWeekAgo);
    $elapsedTime = time() - $startTime;
    if ($elapsedTime > 5) {
        $logger->warning(sprintf('generating aggregate statistics took %ds', $elapsedTime));
    }

    // remove old entries from the connection statistics
    $storage->cleanLiveStats($oneWeekAgo);

    // remove old entries from the connection log
    $storage->cleanConnectionLog($oneMonthAgo);

    // delete expired WireGuard peers and OpenVPN certificates
    $storage->cleanExpiredConfigurations($startOfTheDay);

    // delete expires OAuth authorizations
    $storage->cleanExpiredOAuthAuthorizations($startOfTheDay);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
