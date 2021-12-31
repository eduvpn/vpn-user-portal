<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Config;
use Vpn\Portal\Migration;
use Vpn\Portal\Storage;

try {
    $doInit = false;
    $doMigrate = false;
    $dbDsn = null;
    $dbUser = null;
    $dbPass = null;
    for ($i = 1; $i < $argc; ++$i) {
        if ('--init' === $argv[$i]) {
            $doInit = true;

            continue;
        }

        if ('--migrate' === $argv[$i]) {
            $doMigrate = true;

            continue;
        }

        if ('--dsn' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $dbDsn = $argv[$i + 1];
            }

            continue;
        }

        if ('--user' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $dbUser = $argv[$i + 1];
            }

            continue;
        }
        if ('--pass' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $dbPass = $argv[$i + 1];
            }

            continue;
        }
        if ('--help' === $argv[$i]) {
            echo 'SYNTAX: '.$argv[0].' [--init] [--migrate] [--dsn DSN] [--user USER] [--pass PASS]'.\PHP_EOL;

            exit(0);
        }
    }

    if (!$doInit && !$doMigrate) {
        throw new Exception('need to specify either --init or --migrate');
    }

    $config = Config::fromFile($baseDir.'/config/config.php');
    $dbConfig = $config->dbConfig($baseDir);
    $db = new PDO(
        $dbDsn ?? $dbConfig->dbDsn(),
        $dbUser ?? $dbConfig->dbUser(),
        $dbPass ?? $dbConfig->dbPass()
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ('sqlite' === $db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        throw new Exception('no need to run this command for SQLite database');
    }

    Migration::run(
        $db,
        $dbConfig->schemaDir(),
        Storage::CURRENT_SCHEMA_VERSION,
        $doInit,
        $doMigrate
    );
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
