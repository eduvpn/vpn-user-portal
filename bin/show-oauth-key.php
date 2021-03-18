<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Common\FileIO;

try {
    // generate OAuth key
    $configDir = sprintf('%s/config', $baseDir);
    $keyFile = sprintf('%s/oauth.key', $configDir);
    if (!FileIO::exists($keyFile)) {
        throw new Exception('unable to find "'.$keyFile.'"');
    }
    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($keyFile));
    echo 'Public Key: '.$secretKey->getPublicKey()->encode().\PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
