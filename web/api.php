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

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\ClientInfo;
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
use SURFnet\VPN\Portal\OAuthClientInfo;
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

        $getClientInfo = function ($clientId) use ($config) {
            if (false === $config->getSection('Api')->getSection('consumerList')->hasItem($clientId)) {
                // if not in configuration file, check if it is in the hardcoded list
                return OAuthClientInfo::getClient($clientId);
            }

            // XXX switch to only support 'redirect_uri_list' for 2.0
            $clientInfoData = $config->getSection('Api')->getSection('consumerList')->getItem($clientId);
            $redirectUriList = [];
            if (array_key_exists('redirect_uri_list', $clientInfoData)) {
                $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri_list']);
            }
            if (array_key_exists('redirect_uri', $clientInfoData)) {
                $redirectUriList = array_merge($redirectUriList, (array) $clientInfoData['redirect_uri']);
            }
            $clientInfoData['redirect_uri_list'] = $redirectUriList;

            return new ClientInfo($clientInfoData);
        };

        $bearerValidator = new BearerValidator(
            $storage,
            $getClientInfo,
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
