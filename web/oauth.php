<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use fkooman\OAuth\Server\OAuthServer;
use LC\Portal\Config\PortalConfig;
use LC\Portal\FileIO;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\OAuthTokenModule;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Init;
use LC\Portal\Logger;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\Storage;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);

    $init = new Init($baseDir);
    $init->init();

    $portalConfig = PortalConfig::fromFile(sprintf('%s/config/config.php', $baseDir));
    $service = new Service();

    // OAuth tokens
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir)
    );

    if (false !== $portalConfig->getEnableApi()) {
        $apiConfig = $portalConfig->getApiConfig();
        $secretKey = SecretKey::fromEncodedString(
            FileIO::readFile(
                sprintf('%s/oauth.key', $dataDir)
            )
        );
        $oauthServer = new OAuthServer(
            $storage,
            new ClientDb(),
            new PublicSigner($secretKey->getPublicKey(), $secretKey)
        );

        $oauthServer->setAccessTokenExpiry($apiConfig->getTokenExpiry());
        $oauthModule = new OAuthTokenModule(
            $oauthServer
        );
        $service->addModule($oauthModule);
    }

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
