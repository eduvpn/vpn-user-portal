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

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use Vpn\Portal\Cfg\Config;
use Vpn\Portal\ConnectionManager;
use Vpn\Portal\Dt;
use Vpn\Portal\HttpClient\CurlHttpClient;
use Vpn\Portal\NullLogger;
use Vpn\Portal\Storage;
use Vpn\Portal\VpnDaemon;

try {
    $addUser = false;
    $disableUser = false;
    $enableUser = false;

    $userId = null;
    $userPass = null;
    for ($i = 1; $i < $argc; ++$i) {
        if ('--add' === $argv[$i]) {
            $addUser = true;
        }
        if ('--enable' === $argv[$i]) {
            $enableUser = true;
        }
        if ('--disable' === $argv[$i]) {
            $disableUser = true;
        }
        if ('--user' === $argv[$i] || '-u' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $userId = $argv[$i + 1];
            }

            continue;
        }
        if ('--password' === $argv[$i] || '-p' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $userPass = $argv[$i + 1];
            }

            continue;
        }
        if ('--help' === $argv[$i] || '-h' === $argv[$i]) {
            echo 'SYNTAX: '.$argv[0].\PHP_EOL.\PHP_EOL;
            echo 'Add a new user'.PHP_EOL;
            echo '  --add [--user USER-ID] [--password PASSWORD]'.PHP_EOL.PHP_EOL;
            echo 'Enable an Account'.PHP_EOL;
            echo '  --enable [--user USER-ID]'.PHP_EOL.PHP_EOL;
            echo 'Disable an Account'.PHP_EOL;
            echo '  --disable [--user USER-ID]'.PHP_EOL;

            exit(0);
        }
    }

    if (!$addUser && !$disableUser && !$enableUser) {
        throw new RuntimeException('no action provided, see --help');
    }

    // all commands require userId
    if (null === $userId) {
        echo 'User ID: ';
        $userId = trim(fgets(\STDIN));
    }

    if (empty($userId)) {
        throw new RuntimeException('User ID cannot be empty');
    }

    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));

    if ($addUser) {
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
        $storage->localUserAdd($userId, $passwordHash, Dt::get());
    }

    if ($enableUser) {
        // we only need to enable the user, no other steps required
        $storage->userEnable($userId);
    }

    if ($disableUser) {
        $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
        $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), new NullLogger());
        $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage, new NullLogger());

        $storage->userDisable($userId);
        $clientAuthorizations = $oauthStorage->getAuthorizations($userId);
        foreach ($clientAuthorizations as $clientAuthorization) {
            // delete and disconnect all (active) configurations
            // for this OAuth client authorization
            $connectionManager->disconnectByAuthKey($clientAuthorization->authKey());
            $oauthStorage->deleteAuthorization($clientAuthorization->authKey());
        }

        // disconnect all connections from manually downloaded VPN
        // configurations
        //
        // OpenVPN: connection will be terminated, and with OpenVPN a
        // check whether the user is disabled is performed before
        // allowing a connection
        //
        // WireGuard: connection will be terminated, i.e. removed from
        // daemons, and the peer configuration of disabled users will
        // NOT be synced with daemon-sync
        $connectionManager->disconnectByUserId($userId, ConnectionManager::DO_NOT_DELETE);
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
