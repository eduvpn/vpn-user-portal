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

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\Storage;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\BearerAuthenticationHook;
use SURFnet\VPN\Portal\ForeignKeyListFetcher;
use SURFnet\VPN\Portal\VpnApiModule;

$logger = new Logger('vpn-user-api');

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

    if ($config->hasSection('Api')) {
        $storage = new Storage(new PDO(sprintf('sqlite://%s/tokens.sqlite', $dataDir)));
        $storage->init();

        $bearerValidator = new BearerValidator(
            $storage,
            FileIO::readFile(sprintf('%s/OAuth.key', $dataDir))
        );

        $foreignKeys = [];
        if ($config->getSection('Api')->hasItem('foreignKeys')) {
            $foreignKeys = array_merge($foreignKeys, $config->getSection('Api')->getItem('foreignKeys'));
        }

        if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
            $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/%s/foreign_key_list.json', $baseDir, $instanceId));
            $foreignKeys = array_merge($foreignKeys, $foreignKeyListFetcher->extract());
        }
        $bearerValidator->setForeignKeys($foreignKeys);

        $service->addBeforeHook(
            'auth',
            new BearerAuthenticationHook(
                $bearerValidator
            )
        );

        $serverClient = new ServerClient(
            new CurlHttpClient([$config->getItem('apiUser'), $config->getItem('apiPass')]),
            $config->getItem('apiUri')
        );

        // api module
        $vpnApiModule = new VpnApiModule(
            $serverClient
        );
        if ($config->hasItem('addVpnProtoPorts')) {
            $vpnApiModule->setAddVpnProtoPorts($config->getItem('addVpnProtoPorts'));
        }

        $service->addModule($vpnApiModule);
    }
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
