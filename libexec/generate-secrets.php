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

    // OAuth Key
    $keyFile = sprintf('%s/oauth.key', $configDir);
    if (!FileIO::exists($keyFile)) {
        $secretKey = SecretKey::generate();
        FileIO::writeFile($keyFile, $secretKey->encode(), 0644);
    }

    // Node Key
    $keyFile = sprintf('%s/node.key', $configDir);
    if (!FileIO::exists($keyFile)) {
        $secretKey = random_bytes(32);
        FileIO::writeFile($keyFile, sodium_bin2hex($secretKey), 0644);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;
    exit(1);
}
