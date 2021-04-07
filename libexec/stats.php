<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Json;
use LC\Portal\Stats;
use LC\Portal\Storage;

$baseDir = dirname(__DIR__);
$configFile = $baseDir.'/config/config.php';
$statsFile = $baseDir.'/data/stats.json';
$dbDsn = 'sqlite://'.$baseDir.'/data/db.sqlite';
$schemaDir = $baseDir.'/schema';

try {
    $config = Config::fromFile($configFile);
    $storage = new Storage(new PDO($dbDsn), $schemaDir);
    $stats = new Stats($storage, new DateTimeImmutable());
    $statsData = $stats->get(array_keys($config->requireArray('vpnProfiles')));
    FileIO::writeFile($statsFile, Json::encode($statsData));
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
