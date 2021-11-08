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
use LC\Portal\FileIO;
use LC\Portal\OpenVpn\CA\VpnCa;
use LC\Portal\Storage;

try {
    FileIO::createDir($baseDir.'/data');
    $config = Config::fromFile($baseDir.'/config/config.php');

    // initialize the DB
    $storage = new Storage(
        new PDO(
            $config->dbConfig($baseDir)->dbDsn(),
            $config->dbConfig($baseDir)->dbUser(),
            $config->dbConfig($baseDir)->dbPass()
        ),
        $baseDir.'/schema'
    );
    $storage->init();

    // initialize the CA for OpenVPN
    $vpnCa = new VpnCa($baseDir.'/data/ca', $config->vpnCaPath());
    $vpnCa->initCa($config->caExpiry());
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
