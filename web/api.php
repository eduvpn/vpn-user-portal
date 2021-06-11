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
use LC\Common\Config;
use LC\Common\FileIO;
use LC\Common\Http\JsonResponse;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\HttpClient\CurlHttpClient;
use LC\Common\HttpClient\ServerClient;
use LC\Common\Logger;
use LC\Portal\BearerAuthenticationHook;
use LC\Portal\ClientFetcher;
use LC\Portal\Expiry;
use LC\Portal\OAuth\BearerValidator;
use LC\Portal\Storage;
use LC\Portal\VpnApiModule;

$logger = new Logger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $service = new Service();

    $serverClient = new ServerClient(
        new CurlHttpClient($config->requireString('apiUser'), $config->requireString('apiPass')),
        $config->requireString('apiUri')
    );

    $sessionExpiry = new DateInterval($config->requireString('sessionExpiry', 'P90D'));
    $caInfo = $serverClient->getRequireArray('ca_info');
    $caExpiresAt = new DateTime($caInfo['valid_to']);
    $sessionExpiry = Expiry::doNotOutliveCa($caExpiresAt, $sessionExpiry);

    $storage = new Storage(
        new PDO(sprintf('sqlite://%s/db.sqlite', $dataDir)),
        sprintf('%s/schema', $baseDir),
        $sessionExpiry
    );
    $storage->update();

    $clientFetcher = new ClientFetcher($config);

    $keyInstanceMapping = [];
    if ($config->s('Api')->requireBool('remoteAccess', false)) {
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

    // api module
    $vpnApiModule = new VpnApiModule(
        $config,
        $serverClient,
        $sessionExpiry
    );
    $service->addModule($vpnApiModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
