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
use ParagonIE\ConstantTime\Base64UrlSafe;

try {
    // generate OAuth key
    $configDir = sprintf('%s/config', $baseDir);
    $keyFile = sprintf('%s/local.key', $configDir);
    if (!FileIO::exists($keyFile)) {
        // only create key when there is no key yet
        $keyData = Base64UrlSafe::encodeUnpadded(random_bytes(32));
        FileIO::writeFile($keyFile, $keyData);
    }

    // initialize database
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
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
