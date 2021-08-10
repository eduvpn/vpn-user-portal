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
use LC\Portal\Json;
use LC\Portal\OpenVpn\DaemonSocket;
use LC\Portal\OpenVpn\DaemonWrapper;
use LC\Portal\Storage;
use LC\Portal\SysLogger;

/**
 * @return array<string,int>
 */
function getMaxClientLimit(Config $config): array
{
    $maxConcurrentConnectionLimitList = [];
    foreach ($config->profileConfigList() as $profileConfig) {
        if ('openvpn' !== $profileConfig->vpnProto()) {
            continue;
        }
        [$ipFour, $ipFourPrefix] = explode('/', $profileConfig->range());
        $vpnProtoPortsCount = count($profileConfig->vpnProtoPorts());
        $maxConcurrentConnectionLimitList[$profileConfig->profileId()] = ((int) 2 ** (32 - (int) $ipFourPrefix)) - 3 * $vpnProtoPortsCount;
    }

    return $maxConcurrentConnectionLimitList;
}

/**
 * @return array<string,array{vpnProtoPorts:array<string>,profileNumber:int}>
 */
function getProfilePortMapping(Config $config): array
{
    $profilePortMapping = [];
    foreach ($config->profileConfigList() as $profileConfig) {
        if ('openvpn' !== $profileConfig->vpnProto()) {
            continue;
        }
        $profileNumber = $profileConfig->profileNumber();
        $vpnProtoPorts = $profileConfig->vpnProtoPorts();
        $profilePortMapping[$profileConfig->profileId()] = ['vpnProtoPorts' => $vpnProtoPorts, 'profileNumber' => $profileNumber];
    }

    return $profilePortMapping;
}

function convertToProtoPort(array $profilePortMapping, array $portClientCount): array
{
    $profileNumber = $profilePortMapping['profileNumber'];
    $vpnProtoPorts = $profilePortMapping['vpnProtoPorts'];
    $protoPortCount = [];
    foreach ($vpnProtoPorts as $k => $vpnProtoPort) {
        $managementPort = 11940 + DaemonWrapper::toPort($profileNumber, $k);
        $clientCount = 0;
        if (array_key_exists($managementPort, $portClientCount)) {
            $clientCount = $portClientCount[$managementPort];
        }
        $protoPortCount[$vpnProtoPort] = $clientCount;
    }

    return $protoPortCount;
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

try {
    // XXX implement WireGuard status
    // XXX use argv[0] for SysLogger param?
    $logger = new SysLogger('vpn-user-portal');
    $configDir = sprintf('%s/config', $baseDir);
    $configFile = sprintf('%s/config.php', $configDir);
    $config = Config::fromFile($configFile);
    $dataDir = sprintf('%s/data', $baseDir);

    $alertOnly = false;
    $asJson = false;
    $alertPercentage = 90;
    $includeConnections = false;    // only for JSON
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

    $maxClientLimit = getMaxClientLimit($config);

    $db = new PDO(
        $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
        $config->s('Db')->optionalString('dbUser'),
        $config->s('Db')->optionalString('dbPass')
    );
    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $daemonWrapper = new DaemonWrapper(
        $config,
        $storage,
        new DaemonSocket($baseDir.'/config/vpn-daemon', $config->vpnDaemonTls()),
        $logger
    );

    $outputData = [];
    foreach ($daemonWrapper->getConnectionList(null) as $profileId => $connectionInfoList) {
        // extract only stuff we need
        $displayConnectionInfo = [];
        $portClientCount = [];
        foreach ($connectionInfoList as $connectionInfo) {
            $managementPort = $connectionInfo['management_port'];
            if (!array_key_exists($managementPort, $portClientCount)) {
                $portClientCount[$managementPort] = 0;
            }
            ++$portClientCount[$connectionInfo['management_port']];

            $displayConnectionInfo[] = [
                'user_id' => $connectionInfo['user_id'],
                'virtual_address' => $connectionInfo['virtual_address'],
            ];
        }
        $activeConnectionCount = count($displayConnectionInfo);
        $profileMaxClientLimit = $maxClientLimit[$profileId];
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
            $outputRow['port_client_count'] = convertToProtoPort(getProfilePortMapping($config)[$profileId], $portClientCount);
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
