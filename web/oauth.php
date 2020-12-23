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
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\JsonResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Logger;
use LC\Portal\ClientFetcher;
use LC\Portal\OAuth\PublicSigner;
use LC\Portal\OAuthTokenModule;
use LC\Portal\Storage;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));
    $service = new Service();

    // OAuth tokens
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateInterval($config->requireString('sessionExpiry', 'P90D'))
    );
    $storage->update();

    $clientFetcher = new ClientFetcher($config);

    // OAuth module
    $secretKey = SecretKey::fromEncodedString(
        FileIO::readFile(
            sprintf('%s/config/oauth.key', $baseDir)
        )
    );
    $oauthServer = new OAuthServer(
        $storage,
        $clientFetcher,
        new PublicSigner($secretKey->getPublicKey(), $secretKey)
    );

    $oauthServer->setAccessTokenExpiry(new DateInterval($config->s('Api')->requireString('tokenExpiry', 'PT1H')));
    $oauthModule = new OAuthTokenModule(
        $oauthServer
    );
    $service->addModule($oauthModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
