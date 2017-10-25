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

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Portal\SodiumCompat;

try {
    $p = new CliParser(
        'Initialize the user portal',
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
    FileIO::createDir(
        sprintf('%s/data/%s', $baseDir, $instanceId),
        0700
    );
    $keyPairData = base64_encode(SodiumCompat::crypto_sign_keypair());
    $keyPairFile = sprintf('%s/data/%s/OAuth.key', $baseDir, $instanceId);
    FileIO::writeFile($keyPairFile, $keyPairData);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
