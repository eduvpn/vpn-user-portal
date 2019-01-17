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

use LetsConnect\Common\CliParser;
use LetsConnect\Common\Config;
use LetsConnect\Common\FileIO;
use LetsConnect\Common\Http\PdoAuth;

try {
    $p = new CliParser(
        'Add a user to the portal',
        [
            'user' => ['the username', true, false],
            'pass' => ['the password', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    if ($opt->hasItem('user')) {
        $userId = $opt->getItem('user');
    } else {
        echo 'User ID: ';
        $userId = trim(fgets(STDIN));
    }

    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
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

    if (empty($userPass)) {
        throw new RuntimeException('Password cannot be empty');
    }

    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);

    switch ($config->getItem('authMethod')) {
        case 'FormPdoAuthentication':
            // users/hashes stored in DB
            $dbFile = sprintf('%s/data/userdb.sqlite', $baseDir);
            FileIO::createDir(dirname($dbFile), 0700);
            $pdoAuth = new PdoAuth(
                new PDO(
                   sprintf('sqlite://%s', $dbFile)
                )
            );
            $pdoAuth->init();
            $pdoAuth->add($userId, $userPass);
            break;
        default:
            throw new RuntimeException(sprintf('backend "%s" not supported for adding users', $config->getItem('authMethod')));
    }
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
