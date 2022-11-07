<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2014-2022, The Commons Conservancy eduVPN Programme
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

function showHelp(): void
{
    echo '  --add USER-ID [--password PASSWORD]'.PHP_EOL;
    echo '        Add new *LOCAL* user account'.PHP_EOL;
    echo '  --enable USER-ID'.PHP_EOL;
    echo '        (Re)enable user account(*)'.PHP_EOL;
    echo '  --disable USER-ID'.PHP_EOL;
    echo '        Disable user account(*)'.PHP_EOL;
    echo '  --delete USER-ID [--force]'.PHP_EOL;
    echo '        Delete user account (data)'.PHP_EOL;
    echo '  --list'.PHP_EOL;
    echo '        List user accounts(*)'.PHP_EOL;
    echo PHP_EOL;
    echo '(*) Only for accounts that have logged in at least once!'.PHP_EOL;
}

function requireUserId(?string $userId): string
{
    if (null === $userId || empty($userId)) {
        showHelp();

        throw new RuntimeException('USER-ID must be specified');
    }

    return $userId;
}

try {
    $addUser = false;
    $disableUser = false;
    $enableUser = false;
    $deleteUser = false;
    $forceAction = false;
    $userId = null;
    $userPass = null;
    $listUsers = false;

    // parse CLI flags
    for ($i = 1; $i < $argc; ++$i) {
        if ('--add' === $argv[$i]) {
            $addUser = true;
            if ($i + 1 < $argc) {
                $userId = $argv[$i + 1];
            }

            continue;
        }
        if ('--enable' === $argv[$i]) {
            $enableUser = true;
            if ($i + 1 < $argc) {
                $userId = $argv[++$i];
            }

            continue;
        }
        if ('--disable' === $argv[$i]) {
            $disableUser = true;
            if ($i + 1 < $argc) {
                $userId = $argv[++$i];
            }

            continue;
        }
        if ('--delete' === $argv[$i]) {
            $deleteUser = true;
            if ($i + 1 < $argc) {
                $userId = $argv[++$i];
            }

            continue;
        }
        if ('--force' === $argv[$i]) {
            $forceAction = true;
        }
        if ('--password' === $argv[$i]) {
            if ($i + 1 < $argc) {
                $userPass = $argv[++$i];
            }

            continue;
        }
        if ('--list' === $argv[$i]) {
            $listUsers = true;

            continue;
        }

        if ('--help' === $argv[$i] || '-h' === $argv[$i]) {
            showHelp();

            exit(0);
        }
    }

    if (!$addUser && !$disableUser && !$enableUser && !$deleteUser && !$listUsers) {
        showHelp();

        throw new RuntimeException('operation must be specified');
    }

    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage($config->dbConfig($baseDir));

    if ($listUsers) {
        foreach ($storage->userList() as $userInfo) {
            echo $userInfo->userId().PHP_EOL;
        }

        exit(0);
    }

    if ($addUser) {
        $userId = requireUserId($userId);
        if ('DbAuthModule' !== $config->authModule()) {
            throw new RuntimeException('users can only be added when using DbAuthModule');
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
        $storage->localUserAdd($userId, $passwordHash, Dt::get());

        exit(0);
    }

    if ($enableUser) {
        $userId = requireUserId($userId);
        // we only need to enable the user, no other steps required
        $storage->userEnable($userId);

        exit(0);
    }

    if ($deleteUser) {
        $userId = requireUserId($userId);
        $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), new NullLogger());
        $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage, new NullLogger());
        if (!$forceAction) {
            echo 'Are you sure you want to DELETE user "'.$userId.'"? [y/N]: ';
            if ('y' !== trim(fgets(\STDIN))) {
                exit(0);
            }
        }

        // delete and disconnect all (active) VPN configurations
        // for this user
        $connectionManager->disconnectByUserId($userId);

        // delete all user data (except log)
        $storage->userDelete($userId);

        if ('DbAuthModule' === $config->authModule()) {
            // remove the user from the local database
            $storage->localUserDelete($userId);
        }

        exit(0);
    }

    if ($disableUser) {
        $userId = requireUserId($userId);
        $vpnDaemon = new VpnDaemon(new CurlHttpClient($baseDir.'/config/keys/vpn-daemon'), new NullLogger());
        $connectionManager = new ConnectionManager($config, $vpnDaemon, $storage, new NullLogger());
        $oauthStorage = new OAuthStorage($storage->dbPdo(), 'oauth_');
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

        exit(0);
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
