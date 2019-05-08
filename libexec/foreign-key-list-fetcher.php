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
use LC\Portal\ForeignKeyListFetcher;
use LC\Portal\HttpClient\CurlHttpClient;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $portalConfig = PortalConfig::fromFile($configFile);

    if (false !== $apiConfig = $portalConfig->getApiConfig()) {
        if (false !== $apiConfig->getRemoteAccess()) {
            $dataDir = sprintf('%s/data', $baseDir);
            $foreignKeyListFetcher = new ForeignKeyListFetcher($dataDir);
            $foreignKeyListFetcher->update(
                new CurlHttpClient(),
                $apiConfig->getRemoteAccessList()
            );
        }
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
