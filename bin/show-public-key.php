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

try {
    $keyPairFile = sprintf('%s/data/OAuth.key', $baseDir);
    echo base64_encode(
        sodium_crypto_sign_publickey(
            base64_decode(
                FileIO::readFile($keyPairFile), true
            )
        )
    );
    echo PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
