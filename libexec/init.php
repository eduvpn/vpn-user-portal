<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\FileIO;
use LC\Portal\Storage;

// XXX Move this to web/index.php, web/api.php and web/node-api.php so it
// only does this on first run
try {
    // initialize database
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateInterval('P90D')    // XXX code smell, not needed here!
    );
    $storage->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
