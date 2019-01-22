<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\LocalSigner;
use fkooman\OAuth\Server\OAuthServer;
use LetsConnect\Common\Config;
use LetsConnect\Common\FileIO;
use LetsConnect\Common\Http\JsonResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\CurlHttpClient;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\Logger;
use LetsConnect\Portal\ClientFetcher;
use LetsConnect\Portal\OAuthTokenModule;
use LetsConnect\Portal\Storage;
use ParagonIE\ConstantTime\Base64;

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
    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        new DateTime()
    );
    $storage->update();

    $clientFetcher = new ClientFetcher($config);

    // OAuth module
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            $clientFetcher,
            new LocalSigner(
                Base64::decode(
                    FileIO::readFile(
                        sprintf('%s/local.key', $dataDir)
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
