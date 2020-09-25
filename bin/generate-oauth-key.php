<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Common\FileIO;

try {
    $configDir = sprintf('%s/config', $baseDir);
    $keyFile = sprintf('%s/oauth.key', $configDir);
    if (FileIO::exists($keyFile)) {
        throw new Exception('"'.$keyFile.'" already exists');
    }
    $secretKey = SecretKey::generate();
    FileIO::writeFile($keyFile, $secretKey->encode(), 0644);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
