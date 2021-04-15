<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';

use LC\Portal\Storage;

$baseDir = dirname(__DIR__);
$configFile = $baseDir.'/config/config.php';
$dbDsn = 'sqlite://'.$baseDir.'/data/db.sqlite';
$schemaDir = $baseDir.'/schema';

try {
    $userId = null;
    $userPass = null;
    for ($i = 1; $i < $argc; ++$i) {
        if ('--user' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $userId = $argv[$i + 1];
            }
            continue;
        }
        if ('--pass' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $userPass = $argv[$i + 1];
            }
            continue;
        }
        if ('--help' === $argv[$i]) {
            echo 'SYNTAX: '.$argv[0].' [--user USER] [--pass PASS]'.\PHP_EOL;
            exit(0);
        }
    }

    if (null === $userId) {
        echo 'User ID: ';
        $userId = trim(fgets(\STDIN));
    }

    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    if (null === $userPass) {
        echo sprintf('Setting password for user "%s"', $userId).\PHP_EOL;
        // ask for password
        exec('stty -echo');
        echo 'Password: ';
        $userPass = trim(fgets(\STDIN));
        echo \PHP_EOL.'Password (repeat): ';
        $userPassRepeat = trim(fgets(\STDIN));
        exec('stty echo');
        echo \PHP_EOL;
        if ($userPass !== $userPassRepeat) {
            throw new RuntimeException('specified passwords do not match');
        }
    }

    if (empty($userPass)) {
        throw new RuntimeException('Password cannot be empty');
    }

    $passwordHash = password_hash($userPass, \PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('unable to calculate password hash');
    }
    $storage = new Storage(new PDO($dbDsn), $schemaDir);
    $storage->localUserAdd($userId, $passwordHash, new DateTimeImmutable());
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;
    exit(1);
}
