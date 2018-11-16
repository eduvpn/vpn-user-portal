<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\SodiumSigner;
use fkooman\OAuth\Server\Storage;
use ParagonIE\ConstantTime\Base64;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Common\FileIO;
use SURFnet\VPN\Common\Http\JsonResponse;
use SURFnet\VPN\Common\Http\Request;
use SURFnet\VPN\Common\Http\Service;
use SURFnet\VPN\Common\HttpClient\CurlHttpClient;
use SURFnet\VPN\Common\HttpClient\ServerClient;
use SURFnet\VPN\Common\Logger;
use SURFnet\VPN\Portal\BearerAuthenticationHook;
use SURFnet\VPN\Portal\ClientFetcher;
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

        $clientFetcher = new ClientFetcher($config);

        $foreignKeys = [];
        if ($config->getSection('Api')->hasItem('foreignKeys')) {
            foreach ($config->getSection('Api')->getItem('foreignKeys') as $keyId => $publicKey) {
                $foreignKeys[$keyId] = Base64::decode($publicKey);
            }
        }

        if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
            $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/%s/foreign_key_list.json', $baseDir, $instanceId));
            $foreignKeys = array_merge($foreignKeys, $foreignKeyListFetcher->extract());
        }

        $bearerValidator = new BearerValidator(
            $storage,
            [$clientFetcher, 'get'],
            new SodiumSigner(
                Base64::decode(
                    FileIO::readFile(
                        sprintf('%s/OAuth.key', $dataDir)
                    )
                ),
                $foreignKeys
            )
        );

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

        // api module
        $vpnApiModule = new VpnApiModule(
            $serverClient,
            $sessionExpiry
        );
        $service->addModule($vpnApiModule);
    }
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
