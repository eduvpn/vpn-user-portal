<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\LocalSigner;
use LetsConnect\Common\Config;
use LetsConnect\Common\FileIO;
use LetsConnect\Common\Http\JsonResponse;
use LetsConnect\Common\Http\Request;
use LetsConnect\Common\Http\Service;
use LetsConnect\Common\HttpClient\CurlHttpClient;
use LetsConnect\Common\HttpClient\ServerClient;
use LetsConnect\Common\Logger;
use LetsConnect\Portal\BearerAuthenticationHook;
use LetsConnect\Portal\ClientFetcher;
use LetsConnect\Portal\ForeignKeyListFetcher;
use LetsConnect\Portal\Storage;
use LetsConnect\Portal\VpnApiModule;
use ParagonIE\ConstantTime\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;

$logger = new Logger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $service = new Service();

    if ($config->hasSection('Api')) {
        $serverClient = new ServerClient(
            new CurlHttpClient([$config->getItem('apiUser'), $config->getItem('apiPass')]),
            $config->getItem('apiUri')
        );

        $storage = new Storage(
            new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
            sprintf('%s/schema', $baseDir),
            new DateTime()
        );
        $storage->update();

        $clientFetcher = new ClientFetcher($config);

        $foreignKeys = [];
        if ($config->getSection('Api')->hasItem('foreignKeys')) {
            foreach ($config->getSection('Api')->getItem('foreignKeys') as $keyId => $publicKey) {
                $foreignKeys[$keyId] = Base64::decode($publicKey);
            }
        }

        if ($config->getSection('Api')->hasItem('foreignKeyListSource')) {
            $foreignKeyListFetcher = new ForeignKeyListFetcher(sprintf('%s/data/foreign_key_list.json', $baseDir));
            $foreignKeys = array_merge($foreignKeys, $foreignKeyListFetcher->extract());
        }

        $bearerValidator = new BearerValidator(
            $storage,
            $clientFetcher,
            new LocalSigner(
                Base64UrlSafe::decode(
                    FileIO::readFile(
                        sprintf('%s/config/local.key', $baseDir)
                    )
                )
            )
        );

        $service->addBeforeHook(
            'auth',
            new BearerAuthenticationHook(
                $bearerValidator
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

        // api module
        $vpnApiModule = new VpnApiModule(
            $config,
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
