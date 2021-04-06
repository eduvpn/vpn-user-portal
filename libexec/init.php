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
use LC\Portal\Storage;

// XXX Move this to web/index.php, web/api.php and web/node-api.php so it
// only does this on first run
try {
    // initialize database
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$dataDir.'/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
