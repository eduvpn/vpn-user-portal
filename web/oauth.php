<?php

/**
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2017, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */
$baseDir = dirname(__DIR__);

// find the autoloader (package installs, composer)
foreach (['src', 'vendor'] as $autoloadDir) {
    if (@file_exists(sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir))) {
        require_once sprintf('%s/%s/autoload.php', $baseDir, $autoloadDir);
        break;
    }
}

use fkooman\OAuth\Server\ClientInfo;
use fkooman\OAuth\Server\OAuthServer;
use fkooman\OAuth\Server\Storage;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\Logger;
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

    $getClientInfo = function ($clientId) use ($config) {
        if (false === $config->getSection('Api')->getSection('consumerList')->hasItem($clientId)) {
            return false;
        }

        return new ClientInfo($config->getSection('Api')->getSection('consumerList')->getItem($clientId));
    };

    // OAuth module
    if ($config->hasSection('Api')) {
        $oauthServer = new OAuthServer(
            $storage,
            $getClientInfo,
            FileIO::readFile(sprintf('%s/OAuth.key', $dataDir))
        );
        $oauthServer->setExpiresIn($config->getSection('Api')->getItem('tokenExpiry'));
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
