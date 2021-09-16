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

use fkooman\OAuth\Server\BearerValidator;
use fkooman\OAuth\Server\PdoStorage as OAuthStorage;
use fkooman\OAuth\Server\Signer\EdDSA;
use LC\Portal\Config;
use LC\Portal\FileIO;
use LC\Portal\Http\ApiService;
use LC\Portal\Http\JsonResponse;
use LC\Portal\Http\Request;
use LC\Portal\Http\VpnApiThreeModule;
use LC\Portal\HttpClient\CurlHttpClient;
use LC\Portal\OAuth\ClientDb;
use LC\Portal\OpenVpn\CA\VpnCa;
use LC\Portal\OpenVpn\TlsCrypt;
use LC\Portal\Random;
use LC\Portal\Storage;
use LC\Portal\SysLogger;
use LC\Portal\WireGuard\Wg;
use LC\Portal\WireGuard\WgDaemon;
use LC\Portal\WireGuard\WgServerConfig;

$logger = new SysLogger('vpn-user-portal');

try {
    $request = Request::createFromGlobals();
    FileIO::createDir($baseDir.'/data', 0700);
    $config = Config::fromFile($baseDir.'/config/config.php');
    $db = new PDO(
        $config->dbConfig($baseDir)->dbDsn(),
        $config->dbConfig($baseDir)->dbUser(),
        $config->dbConfig($baseDir)->dbPass()
    );

    $storage = new Storage($db, $baseDir.'/schema');
    $storage->update();

    $oauthStorage = new OAuthStorage($db, 'oauth_');
    $ca = new VpnCa($baseDir.'/data/ca', 'EdDSA', $config->vpnCaPath(), $config->caExpiry());

    $bearerValidator = new BearerValidator(
        $oauthStorage,
        new ClientDb(),
        new EdDSA(FileIO::readFile($baseDir.'/config/oauth.key')),
    );
    $service = new ApiService($bearerValidator);

    $wgServerConfig = new WgServerConfig($baseDir.'/data');

    // API v3
    $service->addModule(
        new VpnApiThreeModule(
            $config,
            $storage,
            new TlsCrypt($baseDir.'/data'),
            new Random(),
            $ca,
            new Wg(new WgDaemon(new CurlHttpClient()), $storage, $wgServerConfig->publicKey())
        )
    );

    $service->run($request)->send();
} catch (Exception $e) {
    $logger->error($e->getMessage());
    $response = new JsonResponse(['error' => $e->getMessage()], [], 500);
    $response->send();
}
