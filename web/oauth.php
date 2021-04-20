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

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Portal\CA\VpnCa;
use LC\Portal\Config;
use LC\Portal\Expiry;
use LC\Portal\FileIO;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\OAuthTokenModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OAuth\VpnOAuthServer;
use LC\Portal\Storage;
use LC\Portal\SysLogger;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);
    FileIO::createDir($baseDir.'/data', 0700);

    $config = Config::fromFile($baseDir.'/config/config.php');
    $service = new Service();

    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        $baseDir.'/schema'
    );
    $storage->update();

    // OAuth module
    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($baseDir.'/config/oauth.key'));
    $oauthServer = new VpnOAuthServer(
        $storage,
        new ClientDb(),
        new PublicSigner($secretKey->getPublicKey(), $secretKey)
    );

    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $vpnCaPath);

    $oauthServer->setAccessTokenExpiry(new DateInterval($config->s('Api')->requireString('tokenExpiry', 'PT1H')));
    $oauthServer->setRefreshTokenExpiry(
        Expiry::calculate(
            new DateInterval($config->requireString('sessionExpiry', 'P90D')),
            $ca->caExpiresAt()
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
