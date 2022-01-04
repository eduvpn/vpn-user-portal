<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2022, The Commons Conservancy eduVPN Programme
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

    if (!$doInit && !$doMigrate) {
        // show database status information
        $currentVersion = Migration::getCurrentVersion($db);
        $latestVersion = Storage::CURRENT_SCHEMA_VERSION;
        echo 'Current Schema Version : '.($currentVersion ?? 'N/A').PHP_EOL;
        echo 'Required Schema Version: '.$latestVersion.PHP_EOL;

        if ($currentVersion === $latestVersion) {
            echo 'Status                 : **OK**'.PHP_EOL;

            exit(0);
        }
        if (null === $currentVersion) {
            echo 'Status                 : **Initialization Required** (use --init)'.PHP_EOL;

            exit(1);
        }

        echo 'Status                     : **Migration Required** (use --migrate)'.PHP_EOL;

        exit(1);
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
