<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use LC\Portal\FileIO;
use LC\Portal\Storage;

$baseDir = dirname(__DIR__);
$dataDir = $baseDir.'/data';
$dbDsn = 'sqlite://'.$baseDir.'/data/db.sqlite';
$schemaDir = $baseDir.'/schema';

// XXX Move this to web/index.php, web/api.php and web/node-api.php so it
// only does this on first run
try {
    FileIO::createDir($dataDir);
    $storage = new Storage(new PDO($dbDsn), $schemaDir);
    $storage->init();
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
