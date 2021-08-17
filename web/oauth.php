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

use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer\EdDSA;
use LC\Portal\Config;
use LC\Portal\Expiry;
use LC\Portal\FileIO;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\OAuthTokenModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\VpnOAuthServer;
use LC\Portal\OpenVpn\CA\VpnCa;
use LC\Portal\Storage;
use LC\Portal\SysLogger;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::createDir($baseDir.'/data', 0700);

    $config = Config::fromFile($baseDir.'/config/config.php');
    $service = new Service();

    $db = new PDO(
        $config->dbConfig($baseDir)->dbDsn(),
        $config->dbConfig($baseDir)->dbUser(),
        $config->dbConfig($baseDir)->dbPass()
    );
    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    // OAuth module
    $oauthServer = new VpnOAuthServer(
        new OAuthStorage($db, 'oauth_'),
        new ClientDb(),
        new EdDSA(FileIO::readFile($baseDir.'/config/oauth.key'))
    );

    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $config->vpnCaPath(), $config->caExpiry());

    $oauthServer->setAccessTokenExpiry($config->apiConfig()->tokenExpiry());
    $oauthServer->setRefreshTokenExpiry(
        Expiry::calculate(
            $config->sessionExpiry(),
            $ca->caCert()->validTo()
        )
    );

    $oauthModule = new OAuthTokenModule(
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
