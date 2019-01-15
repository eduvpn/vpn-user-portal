<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SodiumSigner;
use ParagonIE\ConstantTime\Base64;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\ClientFetcher;
use SURFnet\VPN\Portal\OAuthStorage;
use SURFnet\VPN\Portal\OAuthTokenModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));
    $service = new Service();

    $serverClient = new ServerClient(
        new CurlHttpClient([$config->getItem('apiUser'), $config->getItem('apiPass')]),
        $config->getItem('apiUri')
    );

    // OAuth tokens
    $storage = new OAuthStorage(
        new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        $serverClient
    );
    $storage->update();

    $clientFetcher = new ClientFetcher($config);

    // OAuth module
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            [$clientFetcher, 'get'],
            new SodiumSigner(
                Base64::decode(
                    FileIO::readFile(
                        sprintf('%s/OAuth.key', $dataDir)
                    )
                )
            )
        );

        // determine sessionExpiry, use the new configuration option if it is there
        // or fall back to Api 'refreshTokenExpiry', or "worst case" fall back to
        // hard coded 90 days
        if ($config->hasItem('sessionExpiry')) {
            $sessionExpiry = new DateInterval($config->getItem('sessionExpiry'));
        } elseif ($config->getSection('Api')->hasItem('refreshTokenExpiry')) {
            $sessionExpiry = new DateInterval($config->getSection('Api')->getItem('refreshTokenExpiry'));
        } else {
            $sessionExpiry = new DateInterval('P90D');
        }

        $oauthServer->setRefreshTokenExpiry($sessionExpiry);
        $oauthServer->setAccessTokenExpiry(
            new DateInterval(
                $config->getSection('Api')->hasItem('tokenExpiry') ? sprintf('PT%dS', $config->getSection('Api')->getItem('tokenExpiry')) : 'PT1H'
            )
        );

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
