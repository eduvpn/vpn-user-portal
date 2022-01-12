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
    $cleanBefore = Dt::get('now -32 days');

    // remove old entries from the connection log
    $storage->cleanConnectionLog($cleanBefore);

    // delete expired WireGuard peers and OpenVPN certificates
    $storage->cleanExpiredConfigurations($cleanBefore);

    // delete expires OAuth authorizations
    $storage->cleanExpiredOAuthAuthorizations($cleanBefore);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
