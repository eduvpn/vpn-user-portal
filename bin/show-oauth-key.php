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
use LC\Portal\FileIO;
use LC\Portal\OAuth\PublicSigner;

try {
    // generate OAuth key
    $configDir = sprintf('%s/config', $baseDir);
    $keyFile = sprintf('%s/oauth.key', $configDir);
    if (!FileIO::exists($keyFile)) {
        echo '[INFO] OAuth key does not (ye) exist!'.PHP_EOL;
        exit(1);
    }
    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($keyFile));
    echo 'OAuth Key'.PHP_EOL;
    echo '    Public Key: '.$secretKey->getPublicKey()->encode().PHP_EOL;
    echo '    Key ID    : '.PublicSigner::calculateKeyId($secretKey->getPublicKey()).PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
