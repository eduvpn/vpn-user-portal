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
use LC\Portal\CA\EasyRsaCa;
use LC\Portal\ClientFetcher;
use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Http\BearerAuthenticationHook;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnApiModule;
use LC\Portal\Logger;
use LC\Portal\OAuth\BearerValidator;
use LC\Portal\Storage;
use LC\Portal\TlsAuth;

$logger = new Logger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $service = new Service();

    if ($config->hasSection('Api')) {
        $storage = new Storage(
            new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
            sprintf('%s/schema', $baseDir)
        );
        $storage->update();

        $clientFetcher = new ClientFetcher($config);

        $keyInstanceMapping = [];
        $remoteAccess = $config->getSection('Api')->getItem('remoteAccess');
        if ($remoteAccess) {
            $keyInstanceMappingFile = sprintf('%s/key_instance_mapping.json', $dataDir);
            if (FileIO::exists($keyInstanceMappingFile)) {
                $keyInstanceMapping = FileIO::readJsonFile($keyInstanceMappingFile);
            }
        }

        $secretKey = SecretKey::fromEncodedString(
            FileIO::readFile(
                sprintf('%s/config/oauth.key', $baseDir)
            )
        );

        $bearerValidator = new BearerValidator(
            $storage,
            $clientFetcher,
            $secretKey->getPublicKey(),
            $keyInstanceMapping
        );

        $service->addBeforeHook(
            'auth',
            new BearerAuthenticationHook(
                $bearerValidator
            )
        );

        $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
        $easyRsaDataDir = sprintf('%s/easy-rsa', $dataDir);
        $easyRsaCa = new EasyRsaCa(
            $easyRsaDir,
            $easyRsaDataDir
        );
        $tlsAuth = new TlsAuth($dataDir);

        // api module
        $vpnApiModule = new VpnApiModule(
            $storage,
            $config,
            $easyRsaCa,
            $tlsAuth,
            new DateInterval($config->getItem('sessionExpiry'))
        );
        $service->addModule($vpnApiModule);
    }
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
