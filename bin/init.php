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
use LetsConnect\Common\HttpClient\CurlHttpClient;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Portal\OAuthStorage;

try {
    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir);
    $keyPairData = base64_encode(sodium_crypto_sign_keypair());
    $keyPairFile = sprintf('%s/OAuth.key', $dataDir);
    FileIO::writeFile($keyPairFile, $keyPairData);

    // OAuth tokens
    $storage = new OAuthStorage(
        new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        // here we create a "fake" ServerClient because the OAuthStorage
        // class needs it (unfortunately)
        new ServerClient(new CurlHttpClient([]), 'http://localhost')
    );
    $storage->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
