<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\SodiumSigner;
use fkooman\OAuth\Server\Storage;
use ParagonIE\ConstantTime\Base64;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\ClientFetcher;
use SURFnet\VPN\Portal\OAuthTokenModule;

$logger = new Logger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    if (false === $instanceId = getenv('VPN_INSTANCE_ID')) {
        $instanceId = $request->getServerName();
    }

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    if (!file_exists($dataDir)) {
        if (false === @mkdir($dataDir, 0700, true)) {
            throw new RuntimeException(sprintf('unable to create folder "%s"', $dataDir));
        }
    }

    $config = Config::fromFile(sprintf('%s/config/%s/config.php', $baseDir, $instanceId));
    $service = new Service();

    // OAuth tokens
    $storage = new Storage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
    $storage->init();

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

        $oauthServer->setRefreshTokenExpiry(
            new DateInterval(
                $config->getSection('Api')->hasItem('refreshTokenExpiry') ? $config->getSection('Api')->getItem('refreshTokenExpiry') : 'P1Y'
            )
        );
        $oauthServer->setAccessTokenExpiry(
            new DateInterval(
                sprintf('PT%dS', $config->getSection('Api')->getItem('tokenExpiry'))
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
