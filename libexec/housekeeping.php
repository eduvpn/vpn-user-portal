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
use LC\Portal\Dt;
use LC\Portal\Storage;

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

    // XXX remove WG/OpenVPN peer/certificate configurations for profiles that no longer exist
    // XXX remove WG peer configurations when IP range no longer matches profile range(s)
    $cleanBefore = Dt::get('now -32 days');
    $storage->cleanConnectionLog($cleanBefore);
    $storage->cleanExpiredCertificates($cleanBefore);
    $storage->cleanExpiredOAuthAuthorizations($cleanBefore);
    $storage->cleanUserLog($cleanBefore);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
