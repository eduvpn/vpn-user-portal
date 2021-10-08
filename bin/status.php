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

use LC\Portal\Config;
use LC\Portal\ConnectionManager;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\Json;
use LC\Portal\ProfileConfig;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\VpnDaemon;

function getMaxClientLimit(ProfileConfig $profileConfig): int
{
    // OpenVPN can have multiple processes, WireGuard has no processes, but
    // counts as 1...
    $processCount = 'openvpn' === $profileConfig->vpnProto() ? count($profileConfig->vpnProtoPorts()) : 1;

    return ((int) 2 ** (32 - $profileConfig->range()->prefix())) - 3 * $processCount;
}

function showHelp(array $argv): void
{
    echo 'SYNTAX: '.$argv[0].\PHP_EOL.\PHP_EOL;
    echo '--json                use JSON output format'.\PHP_EOL;
    echo '--alert [percentage]  only show entries where IP space use is over specified'.\PHP_EOL;
    echo '                      percentage. The default percentage for --alert is 90 '.\PHP_EOL;
    echo '--connections         include connected clients (only with --json and when'.\PHP_EOL;
    echo '                      using vpn-daemon)'.\PHP_EOL;
}

function outputConversion(array $outputData, bool $asJson): void
{
    // JSON
    if ($asJson) {
        echo Json::encode($outputData);

        return;
    }

    // CSV
    if (0 === count($outputData)) {
        return;
    }
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
        showHelp($argv);

        return;
    }

    $db = new PDO(
        $config->dbConfig($baseDir)->dbDsn(),
        $config->dbConfig($baseDir)->dbUser(),
        $config->dbConfig($baseDir)->dbPass()
    );
    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $connectionManager = new ConnectionManager(
        $config,
        new VpnDaemon(new CurlHttpClient(), $logger),
        $storage
    );

    $outputData = [];
    foreach ($connectionManager->get() as $profileId => $connectionInfoList) {
        $displayConnectionInfo = [];
        foreach ($connectionInfoList as $connectionInfo) {
            $displayConnectionInfo[] = [
                'user_id' => $connectionInfo['user_id'],
                'ip_list' => $connectionInfo['ip_list'],
            ];
        }
        $activeConnectionCount = count($displayConnectionInfo);
        $profileMaxClientLimit = getMaxClientLimit($config->profileConfig($profileId));
        $percentInUse = floor($activeConnectionCount / $profileMaxClientLimit * 100);
        if ($alertOnly && $alertPercentage > $percentInUse) {
            continue;
        }
        $outputRow = [
            'profile_id' => $profileId,
            'active_connection_count' => $activeConnectionCount,
            'max_connection_count' => $profileMaxClientLimit,
            'percentage_in_use' => $percentInUse,
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
