<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\SimpleSigner;
use LC\Portal\FileIO;

try {
    // OAuth key
    $apiKeyFile = $baseDir.'/config/oauth.simple.key';
    if (!FileIO::exists($apiKeyFile)) {
        FileIO::writeFile($apiKeyFile, SimpleSigner::generateKey(), 0644);
    }

    // Node Key
    $nodeKeyFile = $baseDir.'/config/node.key';
    if (!FileIO::exists($nodeKeyFile)) {
        $secretKey = random_bytes(32);
        FileIO::writeFile($nodeKeyFile, sodium_bin2hex($secretKey), 0644);
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
