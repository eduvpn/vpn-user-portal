#!/usr/bin/env php
<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
require_once sprintf('%s/vendor/autoload.php', dirname(__DIR__));

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\FileIO;

try {
    $p = new CliParser(
        'Show the OAuth server\'s public key',
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
    $keyPairFile = sprintf('%s/data/%s/OAuth.key', dirname(__DIR__), $instanceId);
    echo base64_encode(
        \Sodium\crypto_sign_publickey(
            base64_decode(
                FileIO::readFile($keyPairFile), true
            )
        )
    );
    echo PHP_EOL;
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
