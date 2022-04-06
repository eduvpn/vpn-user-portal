<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use LC\Common\Config;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Common\HttpClient\ServerClient;
use LC\Portal\Storage;

/**
 * @return void
 */
function showHelp()
{
    echo '  --enable USER-ID'.\PHP_EOL;
    echo '        (Re)enable user account(*)'.\PHP_EOL;
    echo '  --disable USER-ID'.\PHP_EOL;
    echo '        Disable user account(*)'.\PHP_EOL;
    echo '  --delete USER-ID [--force]'.\PHP_EOL;
    echo '        Delete user account (data)'.\PHP_EOL;
    echo \PHP_EOL;
    echo '(*) Only has effect for accounts that have logged in at least once!'.\PHP_EOL;
}

try {
    $disableUser = false;
    $enableUser = false;
    $deleteUser = false;
    $forceAction = false;
    $userId = null;

    // parse CLI flags
    for ($i = 1; $i < $argc; ++$i) {
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
        if ('--help' === $argv[$i] || '-h' === $argv[$i]) {
            showHelp();

            exit(0);
        }
    }

    if (!$disableUser && !$enableUser && !$deleteUser) {
        showHelp();

        throw new RuntimeException('operation must be specified');
    }

    if (null === $userId || empty($userId)) {
        showHelp();

        throw new RuntimeException('USER-ID must be specified');
    }

    $configFile = sprintf('%s/config/config.php', $baseDir);
    $config = Config::fromFile($configFile);
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/data/db.sqlite', $baseDir)),
        sprintf('%s/schema', $baseDir),
        new DateInterval('P90D')    // XXX code smell, not needed here!
    );
    $serverClient = new ServerClient(
        new CurlHttpClient($config->requireString('apiUser'), $config->requireString('apiPass')),
        $config->requireString('apiUri')
    );
    $authMethod = $config->requireString('authMethod', 'FormPdoAuthentication');

    if ($enableUser) {
        // we only need to enable the user, no other steps required
        $serverClient->post('enable_user', ['user_id' => $userId]);
    }

    if ($deleteUser) {
        if (!$forceAction) {
            echo 'Are you sure you want to DELETE user "'.$userId.'"? [y/N]: ';
            if ('y' !== trim(fgets(\STDIN))) {
                exit(0);
            }
        }

        // delete OAuth authorizations
        $storage->deleteAuthorizationsOfUserId($userId);
        // delete all certificates of user and associated data
        $serverClient->post('delete_user', ['user_id' => $userId]);

        if ('FormPdoAuthentication' === $authMethod) {
            // also delete the user account when the user is local
            $storage->deleteUser($userId);
        }

        // get active connections for this user
        $connectionList = $serverClient->getRequireArray('client_connections', ['user_id' => $userId]);
        // kill all active connections for this user
        foreach ($connectionList as $profileId => $clientConnectionList) {
            foreach ($clientConnectionList as $clientInfo) {
                $serverClient->post('kill_client', ['common_name' => $clientInfo['common_name']]);
            }
        }
    }

    if ($disableUser) {
        // get active connections for this user
        $connectionList = $serverClient->getRequireArray('client_connections', ['user_id' => $userId]);

        // disable the user
        $serverClient->post('disable_user', ['user_id' => $userId]);
        // * revoke all OAuth clients of this user
        // * delete all client certificates associated with the OAuth clients of this user
        $clientAuthorizations = $storage->getAuthorizations($userId);
        foreach ($clientAuthorizations as $clientAuthorization) {
            $storage->deleteAuthorization($clientAuthorization['auth_key']);
            $serverClient->post('delete_client_certificates_of_client_id', ['user_id' => $userId, 'client_id' => $clientAuthorization['client_id']]);
        }

        // kill all active connections for this user
        foreach ($connectionList as $profileId => $clientConnectionList) {
            foreach ($clientConnectionList as $clientInfo) {
                $serverClient->post('kill_client', ['common_name' => $clientInfo['common_name']]);
            }
        }
    }
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage().\PHP_EOL;

    exit(1);
}
