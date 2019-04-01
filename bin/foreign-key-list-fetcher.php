<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Portal\ForeignKeyListFetcher;
use LC\Portal\HttpClient\CurlHttpClient;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);

    if (false !== $config->getSection('Api')->getItem('remoteAccess')) {
        $config->getSection('Api')->getItem('remoteAccessList');
        $dataDir = sprintf('%s/data', $baseDir);
        $foreignKeyListFetcher = new ForeignKeyListFetcher($dataDir);
        $foreignKeyListFetcher->update(
            new CurlHttpClient(),
            $config->getSection('Api')->getSection('remoteAccessList')->toArray()
        );
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
