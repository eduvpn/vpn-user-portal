<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\FileIO;

try {
    $configDir = sprintf('%s/openvpn-config', $baseDir);
    $tlsDir = sprintf('%s/tls', $configDir);

    // find all profiles
    foreach (glob(sprintf('%s/*', $tlsDir), GLOB_ONLYDIR) as $profileDir) {
        $profileId = basename($profileDir);

        echo "\t$profileId".PHP_EOL;

        $serverCertFile = sprintf('%s/server.crt', $profileDir);
        if (!is_readable($serverCertFile)) {
            continue;
        }

        // strip junk before and after actual certificate
        $pattern = '/(-----BEGIN CERTIFICATE-----.*-----END CERTIFICATE-----)/msU';
        if (1 !== preg_match($pattern, FileIO::readFile($serverCertFile), $matches)) {
            echo 'ERROR!'.PHP_EOL;
        }

        $certInfo = openssl_x509_parse($matches[1]);
        $validFrom = new DateTime(sprintf('@%d', $certInfo['validFrom_time_t']));
        $validTo = new DateTime(sprintf('@%d', $certInfo['validTo_time_t']));

        echo sprintf("\t\tValid From: %s", $validFrom->format('Y-m-d H:i:s')).PHP_EOL;
        echo sprintf("\t\tValid To  : %s", $validTo->format('Y-m-d H:i:s')).PHP_EOL;
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
