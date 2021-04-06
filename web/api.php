<?php

declare(strict_types=1);

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2021, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

require_once dirname(__DIR__).'/vendor/autoload.php';
$baseDir = dirname(__DIR__);

use fkooman\Jwt\Keys\EdDSA\SecretKey;
use LC\Portal\CA\VpnCa;
use LC\Portal\Config;
use LC\Portal\Expiry;
use LC\Portal\FileIO;
use LC\Portal\Http\Auth\BearerAuthenticationHook;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\Service;
use LC\Portal\Http\VpnApiModule;
use LC\Portal\OAuth\BearerValidator;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\Random;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\TlsCrypt;

$logger = new SysLogger('vpn-user-api');

try {
    $request = new Request($_SERVER, $_GET, $_POST);

    $dataDir = sprintf('%s/data', $baseDir);
    FileIO::createDir($dataDir, 0700);

    $config = Config::fromFile(sprintf('%s/config/config.php', $baseDir));

    $service = new Service();
    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$dataDir.'/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        sprintf('%s/schema', $baseDir)
    );
    $storage->update();

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
        new ClientDb(),
        $secretKey->getPublicKey(),
        $keyInstanceMapping
    );

    $service->addBeforeHook(
        'auth',
        new BearerAuthenticationHook(
            $bearerValidator
        )
    );

    $vpnCaDir = sprintf('%s/ca', $dataDir);
    $vpnCaPath = $config->requireString('vpnCaPath', '/usr/bin/vpn-ca');
    $ca = new VpnCa($vpnCaDir, 'EdDSA', $vpnCaPath);

    // api module
    $vpnApiModule = new VpnApiModule(
        $config,
        $storage,
        Expiry::calculate(new DateInterval($config->requireString('sessionExpiry', 'P90D'))),
        new TlsCrypt($dataDir),
        new Random(),
        $ca
    );
    $service->addModule($vpnApiModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], 500);
    $response->send();
}
