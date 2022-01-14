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
use Vpn\Portal\Dt;
use Vpn\Portal\Storage;

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));

    // XXX for testing purposes we set ito "-1 day" instead of "-1 month"
    $oneMonthAgo = Dt::get('today -1 day', new DateTimeZone('UTC'));
    $threeDaysAgo = Dt::get('now -3 days', new DateTimeZone('UTC'));

    // aggregate old entries from the connection statistics
    $storage->statsAggregate($oneMonthAgo);

    // remove old entries from the connection log
    $storage->cleanConnectionLog($oneMonthAgo);

    // remove old entries from the connection statistics
    $storage->cleanLiveStats($oneMonthAgo);

    // delete expired WireGuard peers and OpenVPN certificates
    $storage->cleanExpiredConfigurations($threeDaysAgo);

    // delete expires OAuth authorizations
    $storage->cleanExpiredOAuthAuthorizations($threeDaysAgo);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
