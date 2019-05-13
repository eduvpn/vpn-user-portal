<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use DateTime;
use LC\Portal\Config\PortalConfig;
use LC\Portal\Logger;
use LC\Portal\OpenVpn\Connection;
use LC\Portal\Storage;
use RuntimeException;

$logger = new Logger(
    basename($argv[0])
);

$envData = [];
try {
    $envKeys = [
        'PROFILE_ID',
        'common_name',
        'time_unix',
        'ifconfig_pool_remote_ip',
        'ifconfig_pool_remote_ip6',
    ];

    // read environment variables
    foreach ($envKeys as $envKey) {
        if (false === $envValue = getenv($envKey)) {
            throw new RuntimeException(sprintf('environment variable "%s" not available', $envKey));
        }
        $envData[$envKey] = $envValue;
    }

    $configDir = sprintf('%s/config', $baseDir);
    $dataDir = sprintf('%s/data', $baseDir);
    $portalConfig = PortalConfig::fromFile(sprintf('%s/config.php', $configDir));
    $storage = new Storage(new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)), sprintf('%s/schema', $baseDir));
    $connection = new Connection($portalConfig, $storage);
    $connection->connect(
        $envData['PROFILE_ID'],
        $envData['common_name'],
        $envData['ifconfig_pool_remote_ip'],
        $envData['ifconfig_pool_remote_ip6'],
        new DateTime(sprintf('@%d', $envData['time_unix']))
    );
} catch (Exception $e) {
    $logger->error($e->getMessage());
    exit(1);
}
