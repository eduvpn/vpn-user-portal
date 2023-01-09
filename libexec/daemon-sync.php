<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2023, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionHooks;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\Storage;
use Vpn\Portal\SysLogger;
use Vpn\Portal\VpnDaemon;

function showHelp(): void
{
    echo '  --clean'.PHP_EOL;
    echo '        Remove certificates and peers from the database that should no longer'.PHP_EOL;
    echo '        be there'.PHP_EOL;
    echo PHP_EOL;
}

$logger = new SysLogger('vpn-user-portal');

try {
    $cleanDb = false;
    foreach ($argv as $arg) {
        if ('--clean' === $arg) {
            $cleanDb = true;
        }
        if ('--help' === $arg || '-h' === $arg) {
            showHelp();
            exit(0);
        }
    }
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));
    $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), $logger);
    $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage, ConnectionHooks::init($config, $storage, $logger), $logger);
    if ($cleanDb) {
        // remove all certificates/peers from the database that should no
        // longer be there
        $connectionManager->cleanDb();
    }
    $connectionManager->sync();
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
