#!/usr/bin/env php
<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
$baseDir = dirname(__DIR__);

// find the autoloader (package installs, composer)
foreach (['src', 'vendor'] as $autoloadDir) {
    if (@file_exists(sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir))) {
        require_once sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir);
        break;
    }
}

use fkooman\OAuth\Client\Http\CurlHttpClient;
use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Portal\ForeignKeyListFetcher;

try {
    $p = new CliParser(
        'Fetch foreign key list.',
        [
            'instance' => ['the VPN instance', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    $configFile = sprintf('%s/config/%s/config.php', $baseDir, $instanceId);
    $config = Config::fromFile($configFile);

    if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
        $publicKeysSource = $config->getSection('Api')->getItem('foreignKeyListSource');
        $publicKeysSourcePublicKey = $config->getSection('Api')->getItem('foreignKeyListPublicKey');

        $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/%s/foreign_key_list.json', $baseDir, $instanceId));
        $foreignKeyListFetcher->update(new CurlHttpClient(), $publicKeysSource, $publicKeysSourcePublicKey);
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
