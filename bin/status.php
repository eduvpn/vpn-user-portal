<?php

declare(strict_types=1);

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
use LC\Portal\OpenVpn\ServerManager;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $portalConfig = PortalConfig::fromFile($configFile);

    $serverManager = new ServerManager(
        $portalConfig,
        new ManagementSocket()
    );

    $output = [];
    foreach ($serverManager->connections() as $profileId => $profileConnections) {
        $output[] = $profileId.PHP_EOL;
        foreach ($profileConnections as $connection) {
            $output[] = sprintf("\t%s\t%s", $connection['common_name'], implode(', ', $connection['virtual_address']));
        }
    }

    echo implode(PHP_EOL, $output).PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
