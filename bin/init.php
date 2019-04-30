<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\CA\EasyRsaCa;
use LC\Portal\FileIO;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;

try {
    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/data/easy-rsa', $baseDir);

    // implicit CA init
    $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);

    // initialize database
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
    $storage->init();

    $tlsCrypt = new TlsCrypt($dataDir);
    $tlsCrypt->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
