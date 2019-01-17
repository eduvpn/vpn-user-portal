#!/usr/bin/env php
<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LetsConnect\Common\Config;
use LetsConnect\Portal\ForeignKeyListFetcher;
use LetsConnect\Portal\HttpClient\CurlHttpClient;

try {
    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);

    if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
        $publicKeysSource = $config->getSection('Api')->getItem('foreignKeyListSource');
        $publicKeysSourcePublicKey = $config->getSection('Api')->getItem('foreignKeyListPublicKey');

        $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/foreign_key_list.json', $baseDir));
        $foreignKeyListFetcher->update(new CurlHttpClient(), $publicKeysSource, $publicKeysSourcePublicKey);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
