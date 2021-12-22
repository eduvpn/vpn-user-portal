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

use fkooman\OAuth\Server\Signer;
use Vpn\Portal\FileIO;
use Vpn\Portal\WireGuard\KeyPair;

try {
    // OAuth key
    $apiKeyFile = $baseDir.'/config/oauth.key';
    if (!FileIO::exists($apiKeyFile)) {
        FileIO::writeFile($apiKeyFile, Signer::generateSecretKey());
    }

    // Node Key
    $nodeKeyFile = $baseDir.'/config/node.key';
    if (!FileIO::exists($nodeKeyFile)) {
        $secretKey = random_bytes(32);
        FileIO::writeFile($nodeKeyFile, sodium_bin2hex($secretKey));
    }

    // WireGuard Key
    $wgSecretKeyFile = $baseDir.'/config/wireguard.secret.key';
    $wgPublicKeyFile = $baseDir.'/config/wireguard.public.key';
    if (!FileIO::exists($wgSecretKeyFile) && !FileIO::exists($wgPublicKeyFile)) {
        $keyPair = KeyPair::generate();
        FileIO::writeFile($wgSecretKeyFile, $keyPair['secret_key']);
        FileIO::writeFile($wgPublicKeyFile, $keyPair['public_key']);
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
