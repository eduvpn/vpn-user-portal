<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHooks;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\Json;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;

function showHelp(): void
{
    echo '  --csv'.PHP_EOL;
    echo '        use CSV output format (DEFAULT)'.\PHP_EOL;
    echo '  --json'.PHP_EOL;
    echo '        use JSON output format'.\PHP_EOL;
    echo '  --alert [PERCENTAGE]'.PHP_EOL;
    echo '        only show entries where IP space use is over specified'.\PHP_EOL;
    echo '        percentage. The default percentage for --alert is 90 '.\PHP_EOL;
    echo '  --connections'.PHP_EOL;
    echo '        list connected clients (only with --json)'.\PHP_EOL;
}

function outputConversion(array $outputData, bool $asJson): void
{
    if (0 === count($outputData)) {
        return;
    }

    // JSON
    if ($asJson) {
        echo Json::encodePretty($outputData);

        return;
    }

    // CSV
    $headerKeys = array_keys($outputData[0]);
    echo implode(',', $headerKeys).\PHP_EOL;
    foreach ($outputData as $outputRow) {
        echo implode(',', array_values($outputRow)).\PHP_EOL;
    }
}

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $alertOnly = false;
    $asJson = false;
    $alertPercentage = 90;
    $includeConnections = false; // only for JSON
    $searchForPercentage = false;
    $showHelp = false;
    foreach ($argv as $arg) {
        if ('--alert' === $arg) {
            $alertOnly = true;
            $searchForPercentage = true;

            continue;
        }
        if ($searchForPercentage) {
            // capture parameter after "--alert" and use that as percentage
            if (is_numeric($arg) && 0 <= $arg && 100 >= $arg) {
                $alertPercentage = (int) $arg;
            }
            $searchForPercentage = false;
        }
        if ('--json' === $arg) {
            $asJson = true;
        }
        if ('--connections' === $arg) {
            $includeConnections = true;
        }
        if ('--help' === $arg || '-h' === $arg || '-help' === $arg) {
            $showHelp = true;
        }
    }

    if ($showHelp) {
        showHelp();

        return;
    }

    $storage = new Storage($config->dbConfig($baseDir));
    $connectionManager = new ConnectionManager(
        $config,
        new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger),
        $storage,
        ConnectionHooks::init($config, $storage, $logger),
        $logger
    );

    $outputData = [];
    foreach ($connectionManager->get() as $profileId => $connectionInfoList) {
        $profileConfig = $config->profileConfig($profileId);
        $displayConnectionInfo = [];
        foreach ($connectionInfoList as $connectionInfo) {
            $displayConnectionInfo[] = [
                'user_id' => $connectionInfo['user_id'],
                'ip_list' => $connectionInfo['ip_list'],
                'vpn_proto' => $connectionInfo['vpn_proto'],
            ];
        }
        $activeConnectionCount = count($displayConnectionInfo);
        $profileMaxClientLimit = $profileConfig->maxClientLimit();
        $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);

        // determine the number of WireGuard IPs that are already allocated for
        // a profile
        $wAllocatedIpCount = 0;
        if ($profileConfig->wSupport()) {
            foreach ($profileConfig->onNode() as $nodeNumber) {
                $wAllocatedIpCount += count($storage->wAllocatedIpFourList($profileId, $nodeNumber));
            }
        }

        if ($alertOnly && $alertPercentage > $percentInUse) {
            continue;
        }
        $outputRow = [
            'profile_id' => $profileId,
            'active_connection_count' => $activeConnectionCount,
            // the name is not super helpful, should be
            // max_allowed_connection_count or something...
            'max_connection_count' => $profileMaxClientLimit,
            'percentage_in_use' => $percentInUse,
            'wireguard_allocated_ip_count' => $wAllocatedIpCount,
            'wireguard_free_ip_count' => $profileConfig->wMaxClientLimit() - $wAllocatedIpCount,
        ];
        if ($asJson) {
            if ($includeConnections) {
                $outputRow['connection_list'] = $displayConnectionInfo;
            }
        }
        $outputData[] = $outputRow;
    }
    outputConversion($outputData, $asJson);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).\PHP_EOL;

    exit(1);
}
