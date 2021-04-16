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

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Portal\FileIO;

try {
    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($baseDir.'/config/oauth.key'));
    echo 'Public Key: '.$secretKey->getPublicKey()->encode().\PHP_EOL;
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
