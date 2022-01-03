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
    // XXX remove WG/OpenVPN peer/certificate configurations for profiles that no longer exist
    // XXX remove WG peer configurations when IP range no longer matches profile range(s)
    $cleanBefore = Dt::get('now -32 days');
    $storage->cleanConnectionLog($cleanBefore);
    $storage->cleanExpiredCertificates($cleanBefore);
    $storage->cleanExpiredOAuthAuthorizations($cleanBefore);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
