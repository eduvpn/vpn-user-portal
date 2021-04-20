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
use LC\Portal\FileIO;
use LC\Portal\Http\ApiService;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\VpnApiModule;
use LC\Portal\Json;
use LC\Portal\OAuth\BearerValidator;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\Random;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\TlsCrypt;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = new Request($_SERVER, $_GET, $_POST);
    FileIO::createDir($baseDir.'/data', 0700);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $storage = new Storage(
        new PDO(
            $config->s('Db')->requireString('dbDsn', 'sqlite://'.$baseDir.'/data/db.sqlite'),
            $config->s('Db')->optionalString('dbUser'),
            $config->s('Db')->optionalString('dbPass')
        ),
        $baseDir.'/schema'
    );
    $storage->update();

    $keyInstanceMapping = [];
    if ($config->s('Api')->requireBool('remoteAccess', false)) {
        $keyInstanceMappingFile = $baseDir.'/data/key_instance_mapping.json';
        if (FileIO::exists($keyInstanceMappingFile)) {
            $keyInstanceMapping = Json::decode(FileIO::readFile($keyInstanceMappingFile));
        }
    }

    $secretKey = SecretKey::fromEncodedString(FileIO::readFile($baseDir.'/config/oauth.key'));
    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $config->requireString('vpnCaPath', '/usr/bin/vpn-ca'));

    $bearerValidator = new BearerValidator(
        $storage,
        new ClientDb(),
        $secretKey->getPublicKey(),
        $keyInstanceMapping
    );
    $service = new ApiService($bearerValidator);

    // api module
    $vpnApiModule = new VpnApiModule(
        $config,
        $storage,
        new TlsCrypt($baseDir.'/data'),
        new Random(),
        $ca
    );
    $service->addModule($vpnApiModule);
    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
