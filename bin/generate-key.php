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
use ParagonIE\ConstantTime\Base64UrlSafe;

try {
    // generate OAuth key
    $configDir = sprintf('%s/config', $baseDir);
    $keyFile = sprintf('%s/local.key', $configDir);
    if (FileIO::exists($keyFile)) {
        echo '[INFO] OAuth key already exists!'.PHP_EOL;
        exit(0);
    }
    // only create key when there is no key yet
    $keyData = Base64UrlSafe::encodeUnpadded(random_bytes(32));
    FileIO::writeFile($keyFile, $keyData);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
