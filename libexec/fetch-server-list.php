#!/usr/bin/php
<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\Crypto\Minisign\Verifier;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\ServerList;
use Vpn\Portal\SysLogger;

$logger = new SysLogger('vpn-user-portal');

try {
    $config = Config::fromFile($baseDir.'/config/config.php');
    $apiConfig = $config->apiConfig();
    if (!$apiConfig->enableGuestAccess()) {
        // "Guest Access" disabled, no need to fetch discovery file
        exit(0);
    }
    $serverList = new ServerList($baseDir.'/data', $apiConfig);
    $serverList->update(
        new CurlHttpClient(),
        new Verifier($apiConfig->guestAccessPublicKeyList())
    );
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    $logger->error($e->getMessage());

    exit(1);
}
