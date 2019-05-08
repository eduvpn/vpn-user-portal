<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\OpenVpn\ManagementSocket;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Logger;
use LC\Portal\OpenVpn\ServerManager;
use LC\Portal\Storage;

try {
    $dateTime = new DateTime();
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $portalConfig = PortalConfig::fromFile($configFile);

    $dataDir = sprintf('%s/data', $baseDir);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        sprintf('%s/schema', $baseDir)
    );

    $serverManager = new ServerManager(
        $portalConfig->getProfileConfigList(),
        new Logger($argv[0]),
        new ManagementSocket()
    );

    foreach ($serverManager->connections() as $profile) {
        foreach ($profile['connections'] as $connection) {
            // get information about the certificate based on commonName
            $commonName = $connection['common_name'];
            $userCertificateInfo = $storage->getUserCertificateInfo($commonName);
            if (false === $userCertificateInfo) {
                // the certificate was not found (anymore), disconnect!
                $serverManager->kill($commonName);
                continue;
            }

            // check expiry of certificate
            $expiresAt = new DateTime($userCertificateInfo['valid_to']);
            if ($dateTime > $expiresAt) {
                // certificate expired, disconnect!
                $serverManager->kill($commonName);
            }
        }
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
