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

try {
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $keyPairData = base64_encode(sodium_crypto_sign_keypair());
    $keyPairFile = sprintf('%s/OAuth.key', $dataDir);
    FileIO::writeFile($keyPairFile, $keyPairData);

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
