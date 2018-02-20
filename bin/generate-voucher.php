#!/usr/bin/env php
<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Random;
use SURFnet\VPN\Portal\Voucher;

try {
    $p = new CliParser(
        'Add a user to the portal',
        [
            'instance' => ['the VPN instance', true, false],
            'user' => ['the username to bind the voucher to', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    if ($opt->hasItem('user')) {
        $userId = $opt->getItem('user');
    } else {
        echo 'User ID: ';
        $userId = trim(fgets(STDIN));
    }

    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    $voucher = new Voucher(
        new PDO(
            sprintf('sqlite://%s/data/%s/vouchers.sqlite', $baseDir, $instanceId)
        )
    );
    $random = new Random();
    $voucher->init();
    $voucher->addVoucher(
        $userId,
        $random->get(16)
    );
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
