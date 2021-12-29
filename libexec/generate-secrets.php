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
use Vpn\Portal\Config;
use Vpn\Portal\FileIO;
use Vpn\Portal\OpenVpn\CA\VpnCa;

// allow group to read the created files/folders
umask(0027);

try {
    $config = Config::fromFile($baseDir.'/config/config.php');

    // OAuth key
    $apiKeyFile = $baseDir.'/config/oauth.key';
    if (!FileIO::exists($apiKeyFile)) {
        FileIO::writeFile($apiKeyFile, Signer::generateSecretKey());
    }

    // Node Key
    $nodeKeyFile = $baseDir.'/config/node.0.key';
    if (!FileIO::exists($nodeKeyFile)) {
        $secretKey = random_bytes(32);
        FileIO::writeFile($nodeKeyFile, sodium_bin2hex($secretKey));
    }

    // OpenVPN CA
    // NOTE: only created when the ca.crt and ca.key do not yet exist...
    $vpnCa = new VpnCa($baseDir.'/config/ca', $config->vpnCaPath());
    $vpnCa->initCa($config->caExpiry());
    // the CA files have 0600 permissions, but should be 0640 so PHP can read
    // them, requires update to vpn-ca to use mode 0660 or similar so our
    // umask takes care of it
    chmod($baseDir.'/config/ca/ca.crt', 0640);
    chmod($baseDir.'/config/ca/ca.key', 0640);
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
