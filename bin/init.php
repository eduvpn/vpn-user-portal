<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LetsConnect\Common\FileIO;
use LetsConnect\Portal\Storage;
use ParagonIE\ConstantTime\Base64;

try {
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $keyData = Base64::encode(random_bytes(32));
    $keyFile = sprintf('%s/local.key', $dataDir);
    FileIO::writeFile($keyFile, $keyData);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateTime()
    );
    $storage->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
