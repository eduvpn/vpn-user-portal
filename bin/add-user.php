<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Portal\Config\PortalConfig;
use LC\Portal\Storage;

try {
    $dataDir = sprintf('%s/data', $baseDir);

    $configFile = sprintf('%s/config/config.php', $baseDir);
    $portalConfig = PortalConfig::fromFile($configFile);

    if ('DbAuthentication' !== $portalConfig->getAuthMethod()) {
        throw new RuntimeException('Only "DbAuthentication" backend is supported');
    }

    // if there are parameters, interpret them as username & password
    $userId = null;
    $userPass = null;
    if ($argc > 1) {
        $userId = $argv[1];
    }
    if ($argc > 2) {
        $userPass = $argv[2];
    }

    if (null === $userId) {
        echo 'User ID: ';
        $userId = trim(fgets(STDIN));
    }
    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    if (null === $userPass) {
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

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );
    $storage->add($userId, $userPass);
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
