<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\Stats;
use LC\Portal\Storage;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $portalConfig = PortalConfig::fromFile($configFile);

    $dataDir = sprintf('%s/data', $baseDir);
    $db = new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir));
    $storage = new Storage(
        $db,
        sprintf('%s/schema', $baseDir)
    );
    $outFile = sprintf('%s/stats.json', $dataDir);
    $stats = new Stats($storage);
    $statsData = $stats->get(
        array_keys($portalConfig->getProfileConfigList())
    );

    FileIO::writeJsonFile(
        $outFile,
        $statsData
    );
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
