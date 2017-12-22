#!/usr/bin/env php
<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;

try {
    $p = new CliParser(
        'Add a user to the portal',
        [
            'instance' => ['the VPN instance', true, false],
            'user' => ['the username', true, false],
            'pass' => ['the password', true, false],
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

    if ($opt->hasItem('pass')) {
        $userPass = $opt->getItem('pass');
    } else {
        echo sprintf('Setting password for user "%s"', $userId).PHP_EOL;
        // ask for password
        exec('stty -echo');
        echo 'Password: ';
        $userPass = trim(fgets(STDIN));
        echo PHP_EOL.'Password (repeat): ';
        $userPassRepeat = trim(fgets(STDIN));
        exec('stty echo');
        echo PHP_EOL;
        if ($userPass !== $userPassRepeat) {
            throw new RuntimeException('specified passwords do not match');
        }
    }

    $configFile = sprintf('%s/config/%s/config.php', $baseDir, $instanceId);
    $config = Config::fromFile($configFile);
    $configData = $config->toArray();
    $passwordHash = password_hash($userPass, PASSWORD_DEFAULT);
    $configData['FormAuthentication'][$userId] = $passwordHash;
    Config::toFile($configFile, $configData, 0644);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
