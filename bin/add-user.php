<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\CliParser;
use LC\Common\Config;
use LC\Portal\Storage;

try {
    $dataDir = sprintf('%s/data', $baseDir);

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

    if ('FormPdoAuthentication' !== $config->getItem('authMethod')) {
        echo sprintf('WARNING: backend "%s" does NOT support adding users!', $config->getItem('authMethod')).PHP_EOL;
    }

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
    $storage->add($userId, $userPass);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
