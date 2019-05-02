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
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\FileIO;
use LC\Portal\Storage;
use LC\Portal\TlsCrypt;
use ParagonIE\ConstantTime\Base64UrlSafe;

try {
    $configDir = sprintf('%s/config', $baseDir);

    // ca
    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/data/easy-rsa', $baseDir);
    $ca = new EasyRsaCa($easyRsaDir, $easyRsaDataDir);
    $ca->init();

    // database
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
    $storage->init();

    // tls-crypt
    $tlsCrypt = new TlsCrypt($dataDir);
    $tlsCrypt->init();

    // OAuth Key
    $oauthKeyFile = sprintf('%s/oauth.key', $configDir);
    if (!FileIO::exists($oauthKeyFile)) {
        $secretKey = SecretKey::generate();
        FileIO::writeFile($oauthKeyFile, $secretKey->encode(), 0640);
    }

    // Node API Key
    $apiKeyFile = sprintf('%s/node-api.key', $configDir);
    if (!FileIO::exists($apiKeyFile)) {
        $apiKey = Base64UrlSafe::encodeUnpadded(random_bytes(32));
        FileIO::writeFile($apiKeyFile, $apiKey, 0640);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
